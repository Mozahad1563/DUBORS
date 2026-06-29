<?php
/**
 * BrainStation23
 *
 * @category    BrainStation23
 * @package     BrainStation23_Dubors
 * @copyright   Copyright (c) 2026 BrainStation23
 */

declare(strict_types=1);

namespace BrainStation23\Dubors\Service;

use BrainStation23\Dubors\Helper\Config;
use BrainStation23\Dubors\Api\Data\RecommendationInterface;
use BrainStation23\Dubors\Model\RecommendationFactory;
use BrainStation23\Dubors\Model\RecommendationRepository;
use BrainStation23\Dubors\Model\ResourceModel\Recommendation\CollectionFactory as RecommendationCollectionFactory;
use BrainStation23\Dubors\Model\ResourceModel\UserBehavior\CollectionFactory;
use BrainStation23\Dubors\Service\Exception\MlServiceException;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\MessageQueue\PublisherInterface;
use Psr\Log\LoggerInterface;

/**
 * Recommendation engine orchestrator
 *
 * @api
 */
class RecommendationEngine
{
    /**
     * Fallback strategy when ML service is unavailable
     */
    private const FALLBACK_STRATEGY = 'trending';

    /**
     * Minimum behavioral data points required
     */
    private const MIN_BEHAVIOR_POINTS = 2;

    /**
     * @param Config $config
     * @param MlServiceClient $mlClient
     * @param FeatureExtractor $featureExtractor
     * @param RecommendationFactory $recommendationFactory
     * @param RecommendationRepository $recommendationRepository
     * @param RecommendationCollectionFactory $recommendationCollectionFactory
     * @param CollectionFactory $behaviorCollectionFactory
     * @param CouponGenerator $couponGenerator
     * @param PublisherInterface $publisher
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly Config $config,
        private readonly MlServiceClient $mlClient,
        private readonly FeatureExtractor $featureExtractor,
        private readonly RecommendationFactory $recommendationFactory,
        private readonly RecommendationRepository $recommendationRepository,
        private readonly RecommendationCollectionFactory $recommendationCollectionFactory,
        private readonly CollectionFactory $behaviorCollectionFactory,
        private readonly CouponGenerator $couponGenerator,
        private readonly PublisherInterface $publisher,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly ResourceConnection $resourceConnection,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Generate recommendations for customer
     *
     * @param int $customerId
     * @param array $options
     * @return array
     */
    public function generate(int $customerId, array $options = []): array
    {
        try {
            $this->logger->info('Starting recommendation generation', ['customer_id' => $customerId]);

            // Check minimum behavioral data
            $behaviors = $this->getBehaviorData($customerId);
            if (count($behaviors) < self::MIN_BEHAVIOR_POINTS) {
                $this->logger->info(
                    'Insufficient behavior data for recommendations',
                    ['customer_id' => $customerId, 'data_points' => count($behaviors)]
                );
                return [];
            }

            // Extract features
            $features = $this->featureExtractor->extractCustomerFeatures($customerId);

            // Try ML service first
            try {
                return $this->generateFromMlService($customerId, $behaviors, $features, $options);
            } catch (MlServiceException $e) {
                $this->logger->warning(
                    'ML service failed, using fallback strategy',
                    [
                        'customer_id' => $customerId,
                        'error_code' => $e->getErrorCode(),
                        'strategy' => self::FALLBACK_STRATEGY
                    ]
                );

                // Use fallback strategy
                return $this->generateWithFallback($customerId, $behaviors, $features);
            }
        } catch (\Exception $e) {
            $this->logger->error(
                'Recommendation generation failed',
                [
                    'customer_id' => $customerId,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]
            );
            return [];
        }
    }

    /**
     * Publish a training job to the message queue and return immediately.
     * The actual ML service call happens in ModelTrainConsumer — no gateway timeout.
     *
     * @param int $limit
     * @param int $daysBack
     * @return void
     * @throws LocalizedException
     */
    public function queueTraining(int $limit = 1000, int $daysBack = 90): void
    {
        if (!$this->config->isMlServiceEnabled()) {
            throw new LocalizedException(
                __('ML Service is disabled. Enable it under DUBORS > Configuration > ML Service Connection.')
            );
        }

        if (!$this->config->getMlServiceUrl()) {
            throw new LocalizedException(
                __('ML Service URL is not configured. Set it under DUBORS > Configuration > ML Service Connection.')
            );
        }

        $this->publisher->publish(
            'dubors.model.train',
            json_encode(['limit' => $limit, 'days_back' => $daysBack])
        );

        $this->logger->info('Model training queued', ['limit' => $limit, 'days_back' => $daysBack]);
    }

    /**
     * Train model with historical data
     *
     * @param int $limit
     * @param int $daysBack
     * @return bool
     * @throws LocalizedException
     */
    public function trainModel(int $limit = 1000, int $daysBack = 90): bool
    {
        if (!$this->config->isMlServiceEnabled()) {
            throw new LocalizedException(
                __('ML Service is disabled. Enable it under DUBORS > Configuration > ML Service Connection.')
            );
        }

        $serviceUrl = $this->config->getMlServiceUrl();
        if (!$serviceUrl) {
            throw new LocalizedException(
                __('ML Service URL is not configured. Set it under DUBORS > Configuration > ML Service Connection.')
            );
        }

        $this->logger->info('Starting model training', ['limit' => $limit, 'days_back' => $daysBack]);

        $trainingData = $this->featureExtractor->extractTrainingData($limit, $daysBack);

        if (empty($trainingData)) {
            $this->logger->info('No Magento-side training data yet — sending empty payload so ML service trains on its internal data');
        }

        try {
            $response = $this->mlClient->trainModel($trainingData);
        } catch (MlServiceException $e) {
            $this->logger->error('Model training failed', ['error_code' => $e->getErrorCode(), 'message' => $e->getMessage()]);
            throw new LocalizedException(
                __('ML service error: %1 (code: %2)', $e->getMessage(), $e->getErrorCode())
            );
        }

        $this->logger->info(
            'Model training completed',
            [
                'model_id' => $response['model_id'] ?? 'unknown',
                'accuracy' => $response['accuracy'] ?? 'unknown'
            ]
        );

        return true;
    }

    /**
     * Check ML service health
     *
     * @return array
     */
    public function checkServiceHealth(): array
    {
        $isAvailable = $this->mlClient->isServiceAvailable();
        $stats = $this->mlClient->getStats();

        $health = [
            'available' => $isAvailable,
            'status' => $isAvailable ? 'healthy' : 'unavailable',
            'timestamp' => date('Y-m-d H:i:s'),
            'stats' => $stats
        ];

        $level = $isAvailable ? 'info' : 'warning';
        $this->logger->log(
            $level,
            'ML service health check',
            $health
        );

        return $health;
    }

    /**
     * Generate recommendations from ML service
     *
     * @param int $customerId
     * @param array $behaviors
     * @param array $features
     * @param array $options
     * @return array
     * @throws MlServiceException
     */
    private function generateFromMlService(
        int $customerId,
        array $behaviors,
        array $features,
        array $options
    ): array {
        $this->logger->debug('Calling ML service', ['customer_id' => $customerId]);

        $productIds = $this->extractTopProductIds($behaviors);
        $options['product_ids'] = $productIds;
        $options['product_data'] = $this->loadProductData($productIds);

        $segmentData = $this->loadCustomerSegment($customerId);
        $options['segment'] = $segmentData['segment'];
        $options['rfm_score'] = $segmentData['rfm_score'];

        $discountHistory = $this->loadDiscountHistory($customerId);
        $options['discounts_received_30d'] = $discountHistory['received'];
        $options['discounts_redeemed_30d'] = $discountHistory['redeemed'];

        $response = $this->mlClient->generateRecommendations(
            $customerId,
            $behaviors,
            $options
        );

        if (!isset($response['recommendations'])) {
            throw new MlServiceException(
                'Invalid response from ML service',
                MlServiceException::ERROR_INVALID_RESPONSE,
                ['response_keys' => array_keys($response)]
            );
        }

        $recommendations = [];
        foreach ($response['recommendations'] as $rec) {
            $productId = (int)($rec['product_id'] ?? 0);
            if ($productId <= 0) {
                continue;
            }

            if ($this->hasActiveRecommendation($customerId, $productId)) {
                $this->logger->debug('Skipping duplicate recommendation', [
                    'customer_id' => $customerId,
                    'product_id' => $productId,
                ]);
                continue;
            }

            $recommendation = $this->createRecommendation(
                $customerId,
                $productId,
                $rec['type'] ?? 'hybrid',
                (float)($rec['confidence_score'] ?? 0),
                (float)($rec['discount_percent'] ?? 0),
                $rec['reason'] ?? '',
                $features
            );

            if ($recommendation) {
                $recommendations[] = $recommendation;
                $this->publishRecommendationNotification($recommendation);
            }
        }

        $this->logger->info(
            'Generated recommendations from ML service',
            [
                'customer_id' => $customerId,
                'count' => count($recommendations)
            ]
        );

        return $recommendations;
    }

    /**
     * Generate recommendations with fallback strategy
     *
     * @param int $customerId
     * @param array $behaviors
     * @param array $features
     * @return array
     */
    private function generateWithFallback(
        int $customerId,
        array $behaviors,
        array $features
    ): array {
        $this->logger->debug(
            'Using fallback strategy for recommendations',
            ['customer_id' => $customerId, 'strategy' => self::FALLBACK_STRATEGY]
        );

        $recommendations = [];

        // Fallback: Extract product preferences from behaviors
        $productScores = $this->scoreProductsFromBehavior($behaviors);

        // Get top products
        arsort($productScores);
        $maxRecs = $this->config->getMaxRecommendationsPerCustomer();
        $topProducts = array_slice($productScores, 0, $maxRecs, true);

        foreach ($topProducts as $productId => $score) {
            $confidence = min($score, 100.0);

            if ($confidence >= $this->config->getMinConfidenceScore()) {
                $productId = (int)$productId;

                if ($this->hasActiveRecommendation($customerId, $productId)) {
                    continue;
                }

                $discountPercent = 10.0;
                $reason = sprintf(
                    '%.0f%% standard offer — product scored %.0f based on your browsing activity.',
                    $discountPercent,
                    $score
                );

                $recommendation = $this->createRecommendation(
                    $customerId,
                    $productId,
                    'fallback_' . self::FALLBACK_STRATEGY,
                    $confidence,
                    $discountPercent,
                    $reason,
                    $features
                );

                if ($recommendation) {
                    $recommendations[] = $recommendation;
                }
            }
        }

        $this->logger->info(
            'Generated recommendations using fallback',
            [
                'customer_id' => $customerId,
                'count' => count($recommendations),
                'strategy' => self::FALLBACK_STRATEGY
            ]
        );

        return $recommendations;
    }

    /**
     * Score products based on behavior
     *
     * @param array $behaviors
     * @return array
     */
    private function scoreProductsFromBehavior(array $behaviors): array
    {
        $scores = [];
        $weights = [
            'purchase' => 100,
            'add_to_cart' => 50,
            'product_view' => 10,
            'wishlist_add' => 30,
            'search' => 5
        ];

        foreach ($behaviors as $behavior) {
            $productId = $behavior->getProductId();
            if (!$productId) {
                continue;
            }

            $weight = $weights[$behavior->getBehaviorType()] ?? 0;
            $scores[$productId] = ($scores[$productId] ?? 0) + $weight;
        }

        return $scores;
    }

    /**
     * @param int $customerId
     * @param int $productId
     * @param string $type
     * @param float $confidence
     * @param float $discountPercent
     * @param string $reason
     * @param array $features
     * @return mixed
     */
    private function createRecommendation(
        int $customerId,
        int $productId,
        string $type,
        float $confidence,
        float $discountPercent,
        string $reason,
        array $features
    ) {
        try {
            /** @var \BrainStation23\Dubors\Model\Recommendation $recommendation */
            $recommendation = $this->recommendationFactory->create();
            $recommendation->setCustomerId($customerId);
            $recommendation->setProductId($productId);
            $recommendation->setRecommendationType($type);
            $recommendation->setConfidenceScore($confidence);
            $recommendation->setDiscountPercent($discountPercent);
            $recommendation->setReason($reason);

            $validityDays = $this->config->getRecommendationValidityDays();
            $recommendation->setExpiresAt(date('Y-m-d H:i:s', strtotime("+{$validityDays} days")));

            $autoApprove = $this->config->isAutoApproveEnabled();
            $recommendation->setStatus($autoApprove ? 'approved' : 'pending');

            if ($autoApprove && $discountPercent > 0) {
                try {
                    $couponCode = $this->couponGenerator->generateForRecommendation($recommendation);
                    $recommendation->setCouponCode($couponCode);
                    $recommendation->setApprovedAt(date('Y-m-d H:i:s'));
                } catch (\Exception $e) {
                    $this->logger->warning('Auto-approval failed to generate coupon', [
                        'error' => $e->getMessage()
                    ]);
                    $recommendation->setStatus('pending');
                }
            }

            $this->recommendationRepository->save($recommendation);

            return $recommendation;
        } catch (\Exception $e) {
            $this->logger->error(
                'Failed to create recommendation',
                [
                    'customer_id' => $customerId,
                    'product_id' => $productId,
                    'error' => $e->getMessage()
                ]
            );
            return null;
        }
    }

    /**
     * Publish recommendation notification
     *
     * @param mixed $recommendation
     * @return void
     */
    private function publishRecommendationNotification($recommendation): void
    {
        try {
            $this->publisher->publish('dubors.notification.send', \json_encode([
                'recipient_id' => $recommendation->getCustomerId(),
                'recommendation_id' => $recommendation->getEntityId(),
                'type' => 'new_recommendation'
            ]));
        } catch (\Exception $e) {
            $this->logger->warning(
                'Failed to publish notification',
                ['error' => $e->getMessage()]
            );
        }
    }

    /**
     * @param int[] $productIds
     * @return array<int, array{price: float, category_id: int}>
     */
    private function loadProductData(array $productIds): array
    {
        $data = [];
        foreach ($productIds as $pid) {
            try {
                $product = $this->productRepository->getById($pid);
                $categoryIds = $product->getCategoryIds();
                $cost = (float)$product->getData('cost');
                $data[$pid] = [
                    'price' => (float)$product->getFinalPrice(),
                    'cost' => $cost > 0 ? $cost : 0.0,
                    'category_id' => !empty($categoryIds) ? (int)$categoryIds[0] : 0,
                ];
            } catch (\Exception $e) {
                $data[$pid] = ['price' => 0.0, 'cost' => 0.0, 'category_id' => 0];
            }
        }
        return $data;
    }

    /**
     * @param int $customerId
     * @return array{segment: string, rfm_score: float}
     */
    private function loadCustomerSegment(int $customerId): array
    {
        try {
            $conn = $this->resourceConnection->getConnection();
            $table = $this->resourceConnection->getTableName('vendor_dubors_customer_segment');
            $row = $conn->fetchRow(
                $conn->select()->from($table)->where('customer_id = ?', $customerId)
            );
            if ($row) {
                return [
                    'segment' => (string)($row['segment'] ?? ''),
                    'rfm_score' => (float)($row['rfm_score'] ?? 0.0),
                ];
            }
        } catch (\Exception $e) {
            $this->logger->debug('Could not load segment', ['error' => $e->getMessage()]);
        }
        return ['segment' => '', 'rfm_score' => 0.0];
    }

    /**
     * @param int $customerId
     * @return array{received: int, redeemed: int}
     */
    private function loadDiscountHistory(int $customerId): array
    {
        $cutoff = date('Y-m-d H:i:s', strtotime('-30 days'));

        $collection = $this->recommendationCollectionFactory->create();
        $collection->addFieldToFilter('customer_id', $customerId);
        $collection->addFieldToFilter('status', [
            'in' => [
                RecommendationInterface::STATUS_APPROVED,
                RecommendationInterface::STATUS_REDEEMED,
                RecommendationInterface::STATUS_EXPIRED,
            ]
        ]);
        $collection->addFieldToFilter('created_at', ['gteq' => $cutoff]);

        $received = 0;
        $redeemed = 0;
        foreach ($collection as $rec) {
            $received++;
            if ($rec->getStatus() === RecommendationInterface::STATUS_REDEEMED) {
                $redeemed++;
            }
        }

        return ['received' => $received, 'redeemed' => $redeemed];
    }

    /**
     * @param array $behaviors
     * @return int[]
     */
    private function extractTopProductIds(array $behaviors): array
    {
        $productScores = $this->scoreProductsFromBehavior($behaviors);
        arsort($productScores);
        $maxRecs = $this->config->getMaxRecommendationsPerCustomer();
        $topIds = array_keys(array_slice($productScores, 0, $maxRecs, true));
        return array_map('intval', $topIds);
    }

    /**
     * @param int $customerId
     * @param int $productId
     * @return bool
     */
    private function hasActiveRecommendation(int $customerId, int $productId): bool
    {
        $collection = $this->recommendationCollectionFactory->create();
        $collection->addFieldToFilter('customer_id', $customerId);
        $collection->addFieldToFilter('product_id', $productId);
        $collection->addFieldToFilter('status', [
            'in' => [RecommendationInterface::STATUS_PENDING, RecommendationInterface::STATUS_APPROVED]
        ]);
        return $collection->getSize() > 0;
    }

    /**
     * @param int $customerId
     * @return array
     */
    private function getBehaviorData(int $customerId): array
    {
        try {
            $collection = $this->behaviorCollectionFactory->create();
            $collection->addFieldToFilter('customer_id', ['eq' => $customerId]);
            $collection->setOrder('created_at', 'desc');
            $collection->setPageSize(100);

            return array_values($collection->getItems());
        } catch (\Exception $e) {
            $this->logger->error(
                'Failed to retrieve behavior data',
                ['customer_id' => $customerId, 'error' => $e->getMessage()]
            );
            return [];
        }
    }
}
