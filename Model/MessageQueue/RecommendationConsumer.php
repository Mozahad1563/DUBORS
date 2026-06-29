<?php
/**
 * BrainStation23
 *
 * @category    BrainStation23
 * @package     BrainStation23_Dubors
 * @copyright   Copyright (c) 2026 BrainStation23
 */

declare(strict_types=1);

namespace BrainStation23\Dubors\Model\MessageQueue;

use BrainStation23\Dubors\Helper\Config;
use BrainStation23\Dubors\Model\Recommendation;
use BrainStation23\Dubors\Model\RecommendationFactory;
use BrainStation23\Dubors\Model\RecommendationRepository;
use BrainStation23\Dubors\Service\CouponGenerator;
use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface;

class RecommendationConsumer
{
    /**
     * @param RecommendationFactory $recommendationFactory
     * @param RecommendationRepository $recommendationRepository
     * @param Config $config
     * @param CouponGenerator $couponGenerator
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly RecommendationFactory $recommendationFactory,
        private readonly RecommendationRepository $recommendationRepository,
        private readonly Config $config,
        private readonly CouponGenerator $couponGenerator,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Process recommendation generation from queue
     *
     * @param string $message
     * @return void
     */
    public function process(string $message): void
    {
        try {
            $data = \json_decode($message, true);

            if (!isset($data['customer_id']) || !isset($data['product_id'])) {
                throw new LocalizedException(
                    __('Invalid recommendation event: missing required fields')
                );
            }

            // Check if auto-approve is enabled
            $autoApprove = $this->config->isAutoApproveEnabled();
            $discountPercent = (float)($data['discount_percent'] ?? 0);

            /** @var Recommendation $recommendation */
            $recommendation = $this->recommendationFactory->create();
            $recommendation->setCustomerId((int)$data['customer_id']);
            $recommendation->setProductId((int)$data['product_id']);
            $recommendation->setRecommendationType($data['recommendation_type'] ?? 'hybrid');
            $recommendation->setConfidenceScore((int)($data['confidence_score'] ?? 0));
            $recommendation->setDiscountPercent($discountPercent);
            $recommendation->setStatus($autoApprove ? 'approved' : 'pending');

            if ($autoApprove && $discountPercent > 0) {
                try {
                    $couponCode = $this->couponGenerator->generateForRecommendation($recommendation);
                    $recommendation->setCouponCode($couponCode);
                    $recommendation->setApprovedAt((string)date('Y-m-d H:i:s'));
                } catch (\Exception $e) {
                    $this->logger->warning('Auto-approval failed to generate coupon in consumer', [
                        'error' => $e->getMessage()
                    ]);
                    $recommendation->setStatus('pending');
                }
            }

            $this->recommendationRepository->save($recommendation);

            $this->logger->info(
                sprintf(
                    'Generated recommendation for customer %d, product %d (confidence: %d%%, discount: %.2f%%)',
                    $data['customer_id'],
                    $data['product_id'],
                    (int)($data['confidence_score'] ?? 0),
                    $discountPercent
                )
            );
        } catch (\Exception $e) {
            $this->logger->error(
                sprintf('Error processing recommendation event: %s', $e->getMessage())
            );
            throw $e;
        }
    }
}
