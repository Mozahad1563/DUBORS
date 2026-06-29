<?php
declare(strict_types=1);

namespace BrainStation23\Dubors\Observer;

use BrainStation23\Dubors\Api\Data\RecommendationInterface;
use BrainStation23\Dubors\Model\ResourceModel\Recommendation\CollectionFactory;
use BrainStation23\Dubors\Model\RecommendationRepository;
use BrainStation23\Dubors\Service\MlServiceClient;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Psr\Log\LoggerInterface;

class TrackCouponRedemption implements ObserverInterface
{
    private const COUPON_PREFIX = 'DUBORS-';

    public function __construct(
        private readonly CollectionFactory $recommendationCollectionFactory,
        private readonly RecommendationRepository $recommendationRepository,
        private readonly MlServiceClient $mlClient,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(Observer $observer): void
    {
        try {
            $order = $observer->getEvent()->getData('order');
            if (!$order) {
                return;
            }

            $couponCode = $order->getCouponCode();
            if (!$couponCode || !str_starts_with($couponCode, self::COUPON_PREFIX)) {
                return;
            }

            $collection = $this->recommendationCollectionFactory->create();
            $collection->addFieldToFilter('coupon_code', $couponCode);
            $collection->addFieldToFilter('status', RecommendationInterface::STATUS_APPROVED);
            $collection->setPageSize(1);

            $recommendation = $collection->getFirstItem();
            if (!$recommendation->getId()) {
                return;
            }

            $recommendation->setRedeemedAt(date('Y-m-d H:i:s'));
            $recommendation->setStatus(RecommendationInterface::STATUS_REDEEMED);
            $this->recommendationRepository->save($recommendation);

            try {
                $this->mlClient->sendFeedback(
                    (string)$recommendation->getEntityId(),
                    (int)$recommendation->getCustomerId(),
                    (float)$recommendation->getDiscountPercent(),
                    true,
                    null
                );
                $this->logger->info('DUBORS: Sent conversion feedback', [
                    'recommendation_id' => $recommendation->getEntityId(),
                    'coupon' => $couponCode,
                    'order_id' => $order->getIncrementId(),
                ]);
            } catch (\Exception $e) {
                $this->logger->warning('DUBORS: ML conversion feedback failed', [
                    'error' => $e->getMessage(),
                ]);
            }
        } catch (\Exception $e) {
            $this->logger->error('DUBORS: Error tracking coupon redemption: ' . $e->getMessage());
        }
    }
}
