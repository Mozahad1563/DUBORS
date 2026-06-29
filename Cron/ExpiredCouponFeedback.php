<?php
declare(strict_types=1);

namespace BrainStation23\Dubors\Cron;

use BrainStation23\Dubors\Api\Data\RecommendationInterface;
use BrainStation23\Dubors\Model\RecommendationRepository;
use BrainStation23\Dubors\Model\ResourceModel\Recommendation\CollectionFactory;
use BrainStation23\Dubors\Service\MlServiceClient;
use Psr\Log\LoggerInterface;

class ExpiredCouponFeedback
{
    public function __construct(
        private readonly CollectionFactory $recommendationCollectionFactory,
        private readonly RecommendationRepository $recommendationRepository,
        private readonly MlServiceClient $mlClient,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(): void
    {
        $collection = $this->recommendationCollectionFactory->create();
        $collection->addFieldToFilter('status', RecommendationInterface::STATUS_APPROVED);
        $collection->addFieldToFilter('coupon_code', ['notnull' => true]);
        $collection->addFieldToFilter('redeemed_at', ['null' => true]);
        $collection->addFieldToFilter('expires_at', ['lt' => date('Y-m-d H:i:s')]);
        $collection->setPageSize(200);

        $count = 0;
        foreach ($collection as $recommendation) {
            try {
                $recommendation->setStatus('expired');
                $this->recommendationRepository->save($recommendation);

                $this->mlClient->sendFeedback(
                    (string)$recommendation->getEntityId(),
                    (int)$recommendation->getCustomerId(),
                    (float)$recommendation->getDiscountPercent(),
                    true,
                    null
                );
                $count++;
            } catch (\Exception $e) {
                $this->logger->warning('DUBORS: Expired feedback failed', [
                    'recommendation_id' => $recommendation->getEntityId(),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($count > 0) {
            $this->logger->info('DUBORS: Processed expired coupon feedback', ['count' => $count]);
        }
    }
}
