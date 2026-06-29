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

use BrainStation23\Dubors\Model\ResourceModel\UserBehavior\CollectionFactory;
use BrainStation23\Dubors\Model\ResourceModel\Recommendation\CollectionFactory as RecommendationCollectionFactory;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Feature extraction for ML model training
 *
 * @api
 */
class FeatureExtractor
{
    /**
     * @param CollectionFactory $behaviorCollectionFactory
     * @param RecommendationCollectionFactory $recommendationCollectionFactory
     * @param ProductRepositoryInterface $productRepository
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly CollectionFactory $behaviorCollectionFactory,
        private readonly RecommendationCollectionFactory $recommendationCollectionFactory,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Extract features for customer
     *
     * @param int $customerId
     * @param int $daysBack
     * @return array
     */
    public function extractCustomerFeatures(int $customerId, int $daysBack = 30): array
    {
        try {
            $collection = $this->behaviorCollectionFactory->create();
            $collection->addFieldToFilter('customer_id', ['eq' => $customerId]);
            $collection->addFieldToFilter(
                'created_at',
                ['from' => date('Y-m-d H:i:s', strtotime("-{$daysBack} days"))]
            );

            $behaviors = $collection->getItems();

            // Calculate features
            $features = [
                'customer_id' => $customerId,
                'total_events' => count($behaviors),
                'unique_products' => $this->countUniqueProducts($behaviors),
                'behavior_distribution' => $this->calculateBehaviorDistribution($behaviors),
                'temporal_features' => $this->extractTemporalFeatures($behaviors),
                'product_features' => $this->extractProductFeatures($behaviors),
                'engagement_score' => $this->calculateEngagementScore($behaviors),
                'recency_score' => $this->calculateRecencyScore($behaviors),
                'frequency_score' => $this->calculateFrequencyScore($behaviors),
                'monetary_features' => $this->extractMonetaryFeatures($customerId),
                'extraction_timestamp' => date('Y-m-d H:i:s')
            ];

            $this->logger->debug(
                'Extracted customer features',
                [
                    'customer_id' => $customerId,
                    'event_count' => count($behaviors),
                    'unique_products' => $features['unique_products']
                ]
            );

            return $features;
        } catch (\Exception $e) {
            $this->logger->error(
                'Failed to extract customer features',
                [
                    'customer_id' => $customerId,
                    'error' => $e->getMessage()
                ]
            );
            return [];
        }
    }

    /**
     * Extract training data for ML model.
     *
     * Primary path: one sample per existing recommendation (supervised — label from outcome).
     * Cold-start fallback: when no recommendations exist yet, derives samples directly from
     * behavior events using a heuristic discount label so the model can bootstrap.
     *
     * @param int $limit
     * @param int $daysBack
     * @return array
     */
    public function extractTrainingData(int $limit = 1000, int $daysBack = 90): array
    {
        try {
            $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$daysBack} days"));

            $recommendations = $this->recommendationCollectionFactory->create();
            $recommendations->addFieldToFilter('created_at', ['from' => $cutoffDate]);
            $recommendations->setPageSize($limit);
            $recommendations->setOrder('created_at', 'desc');

            if ($recommendations->getSize() > 0) {
                return $this->extractFromRecommendations($recommendations, $daysBack);
            }

            $this->logger->info('No recommendations found — using cold-start path from behavior data');
            return $this->extractColdStartData($limit, $daysBack, $cutoffDate);
        } catch (\Exception $e) {
            $this->logger->error('Failed to extract training data', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Build training samples from existing recommendation records (normal path).
     *
     * @param \Magento\Framework\Data\Collection $recommendations
     * @param int $daysBack
     * @return array
     */
    private function extractFromRecommendations($recommendations, int $daysBack): array
    {
        $trainingData = [];

        foreach ($recommendations as $recommendation) {
            $status = $recommendation->getStatus();
            if ($status === 'pending') {
                continue;
            }

            $customerId = (int)$recommendation->getCustomerId();
            $features = $this->extract9Features($customerId, $daysBack);
            $discount = (float)$recommendation->getDiscountPercent();

            if ($status === 'redeemed') {
                $label = $discount;
                $weight = 1.5;
            } elseif ($status === 'approved') {
                $label = $discount;
                $weight = 1.0;
            } elseif ($status === 'expired') {
                $label = max(0.0, $discount - 5.0);
                $weight = 0.5;
            } else {
                $label = max(0.0, $discount - 5.0);
                $weight = 0.3;
            }

            $trainingData[] = [
                'features'          => $features,
                'label'             => $label,
                'weight'            => $weight,
                'recommendation_id' => (int)$recommendation->getEntityId(),
                'confidence_score'  => (float)$recommendation->getConfidenceScore(),
            ];
        }

        $this->logger->info('Extracted training data from recommendations', ['records' => count($trainingData)]);

        return $trainingData;
    }

    /**
     * Cold-start fallback: build training samples directly from behavior events.
     *
     * Label heuristic (discount % the customer is estimated to need):
     *   - Has purchases        →  5.0  (already converts; minimal incentive required)
     *   - Cart but no purchase → 15.0  (close to converting; moderate discount)
     *   - Views only           → 20.0  (early funnel; needs stronger incentive)
     *
     * @param int    $limit
     * @param int    $daysBack
     * @param string $cutoffDate
     * @return array
     */
    private function extractColdStartData(int $limit, int $daysBack, string $cutoffDate): array
    {
        $trainingData = [];

        // Collect distinct customer IDs that have any behavior in the window
        $collection = $this->behaviorCollectionFactory->create();
        $collection->addFieldToFilter('created_at', ['from' => $cutoffDate]);
        $collection->getSelect()
            ->reset(\Zend_Db_Select::COLUMNS)
            ->columns(['customer_id'])
            ->group('customer_id')
            ->limit($limit);

        $customerIds = [];
        foreach ($collection as $row) {
            $customerIds[] = (int)$row->getCustomerId();
        }

        if (empty($customerIds)) {
            $this->logger->warning('Cold-start: no customer behavior data found in the last ' . $daysBack . ' days');
            return [];
        }

        foreach ($customerIds as $customerId) {
            $features = $this->extract9Features($customerId, $daysBack);

            // Determine which behavior types this customer performed
            $dist = $features['behavior_distribution'] ?? [];
            $hasPurchase  = isset($dist['purchase']) || isset($dist['purchase_item']);
            $hasCartAdd   = isset($dist['add_to_cart']);

            if ($hasPurchase) {
                $label = 5.0;
            } elseif ($hasCartAdd) {
                $label = 15.0;
            } else {
                $label = 20.0;
            }

            $trainingData[] = [
                'features'         => $features,
                'label'            => $label,
                'recommendation_id' => null,
                'confidence_score' => 0.0,
            ];
        }

        $this->logger->info(
            'Cold-start training data extracted from behavior events',
            ['customers' => count($trainingData), 'days_back' => $daysBack]
        );

        return $trainingData;
    }

    /**
     * Count unique products in behavior events
     *
     * @param array $behaviors
     * @return int
     */
    private function countUniqueProducts(array $behaviors): int
    {
        $products = [];
        foreach ($behaviors as $behavior) {
            if ($productId = $behavior->getProductId()) {
                $products[$productId] = true;
            }
        }
        return count($products);
    }

    /**
     * Calculate behavior type distribution
     *
     * @param array $behaviors
     * @return array
     */
    private function calculateBehaviorDistribution(array $behaviors): array
    {
        $distribution = [];
        foreach ($behaviors as $behavior) {
            $type = $behavior->getBehaviorType();
            $distribution[$type] = ($distribution[$type] ?? 0) + 1;
        }

        // Normalize
        $total = count($behaviors) ?: 1;
        foreach ($distribution as &$count) {
            $count = round($count / $total, 3);
        }

        return $distribution;
    }

    /**
     * Extract temporal features
     *
     * @param array $behaviors
     * @return array
     */
    private function extractTemporalFeatures(array $behaviors): array
    {
        if (empty($behaviors)) {
            return [
                'day_of_week_distribution' => [],
                'hour_of_day_distribution' => [],
                'average_events_per_day' => 0
            ];
        }

        $dayDistribution = [];
        $hourDistribution = [];
        $dates = [];

        foreach ($behaviors as $behavior) {
            $createdAt = new \DateTime($behavior->getCreatedAt());
            $day = (int)$createdAt->format('w');
            $hour = (int)$createdAt->format('H');
            $date = $createdAt->format('Y-m-d');

            $dayDistribution[$day] = ($dayDistribution[$day] ?? 0) + 1;
            $hourDistribution[$hour] = ($hourDistribution[$hour] ?? 0) + 1;
            $dates[$date] = true;
        }

        return [
            'day_of_week_distribution' => $dayDistribution,
            'hour_of_day_distribution' => $hourDistribution,
            'average_events_per_day' => round(count($behaviors) / count($dates), 2)
        ];
    }

    /**
     * Extract product category features
     *
     * @param array $behaviors
     * @return array
     */
    private function extractProductFeatures(array $behaviors): array
    {
        $categories = [];
        $productCount = 0;

        foreach ($behaviors as $behavior) {
            if (!$productId = $behavior->getProductId()) {
                continue;
            }

            $productCount++;
            try {
                $product = $this->productRepository->getById($productId);
                $categoryIds = $product->getCategoryIds();

                foreach ($categoryIds as $categoryId) {
                    $categories[$categoryId] = ($categories[$categoryId] ?? 0) + 1;
                }
            } catch (\Exception $e) {
                // Product not found, skip
                continue;
            }
        }

        return [
            'categories_viewed' => count($categories),
            'category_distribution' => $categories,
            'products_with_categories' => $productCount
        ];
    }

    /**
     * Calculate engagement score (0-100)
     *
     * @param array $behaviors
     * @return float
     */
    private function calculateEngagementScore(array $behaviors): float
    {
        if (empty($behaviors)) {
            return 0.0;
        }

        $score = 0.0;
        $eventTypeWeights = [
            'purchase' => 10,
            'add_to_cart' => 5,
            'product_view' => 1,
            'wishlist_add' => 3,
            'search' => 2
        ];

        foreach ($behaviors as $behavior) {
            $type = $behavior->getBehaviorType();
            $score += $eventTypeWeights[$type] ?? 0;
        }

        // Normalize to 0-100
        $maxScore = 10 * count($behaviors);
        return min(round(($score / $maxScore) * 100, 2), 100.0);
    }

    /**
     * Calculate recency score (0-100)
     *
     * @param array $behaviors
     * @return float
     */
    private function calculateRecencyScore(array $behaviors): float
    {
        if (empty($behaviors)) {
            return 0.0;
        }

        // Get most recent event
        $lastBehavior = end($behaviors);
        $lastDate = new \DateTime($lastBehavior->getCreatedAt());
        $now = new \DateTime();
        $daysAgo = $now->diff($lastDate)->days;

        // Score decreases with days
        return max(100.0 - ($daysAgo * 5), 0.0);
    }

    /**
     * Calculate frequency score (0-100)
     *
     * @param array $behaviors
     * @return float
     */
    private function calculateFrequencyScore(array $behaviors): float
    {
        $count = count($behaviors);

        // 20+ events = 100, 0 events = 0, linear scaling
        return min(round(($count / 20) * 100, 2), 100.0);
    }

    /**
     * Extract monetary features for customer
     *
     * @param int $customerId
     * @return array
     */
    private function extractMonetaryFeatures(int $customerId): array
    {
        // This would be implemented with sales data
        // For now, return placeholder
        return [
            'total_spend' => 0.0,
            'average_order_value' => 0.0,
            'lifetime_value' => 0.0,
            'purchase_frequency' => 0
        ];
    }

    /**
     * Extract 9-dimensional features matching ML service FEATURE_NAMES contract
     *
     * @param int $customerId
     * @param int $daysBack
     * @return array
     */
    public function extract9Features(int $customerId, int $daysBack = 30): array
    {
        try {
            $collection = $this->behaviorCollectionFactory->create();
            $collection->addFieldToFilter('customer_id', ['eq' => $customerId]);
            $collection->addFieldToFilter(
                'created_at',
                ['from' => date('Y-m-d H:i:s', strtotime("-{$daysBack} days"))]
            );

            $behaviors = $collection->getItems();

            $visitCount = 0;
            $cartValue = 0.0;
            $cartAbandonmentCount = 0;
            $purchaseValues = [];
            $lastPurchaseTs = null;
            $cartedProducts = [];
            $purchasedProducts = [];

            foreach ($behaviors as $behavior) {
                $type = $behavior->getBehaviorType();
                $productId = $behavior->getProductId();
                $value = (float)$behavior->getCartValue();
                $createdAt = $behavior->getCreatedAt();

                switch ($type) {
                    case 'product_view':
                        $visitCount++;
                        break;
                    case 'add_to_cart':
                        $cartValue += $value;
                        if ($productId) {
                            $cartedProducts[$productId] = true;
                        }
                        break;
                    case 'purchase':
                    case 'purchase_item':
                        $purchaseValues[] = $value;
                        if ($productId) {
                            $purchasedProducts[$productId] = true;
                        }
                        if ($createdAt && !$lastPurchaseTs) {
                            $lastPurchaseTs = strtotime($createdAt);
                        }
                        break;
                }
            }

            foreach (array_keys($cartedProducts) as $pid) {
                if (!isset($purchasedProducts[$pid])) {
                    $cartAbandonmentCount++;
                }
            }

            $daysSinceLastPurchase = $lastPurchaseTs ? (int)floor((time() - $lastPurchaseTs) / 86400) : null;
            $avgOrderValue = !empty($purchaseValues) ? array_sum($purchaseValues) / count($purchaseValues) : 0.0;
            $isNewCustomer = ($daysSinceLastPurchase === null) ? 1.0 : 0.0;
            $dslsVal = ($daysSinceLastPurchase !== null) ? (float)$daysSinceLastPurchase : 0.0;
            $cartToAvgRatio = ($avgOrderValue > 0) ? ($cartValue / $avgOrderValue) : 1.0;
            $abandonmentRate = $cartAbandonmentCount / max($visitCount, 1);
            $recencyScore = ($isNewCustomer === 0.0) ? (1.0 / (1.0 + $dslsVal)) : 0.0;

            $behaviorDistribution = $this->calculateBehaviorDistribution($behaviors);

            return [
                'customer_id' => $customerId,
                'behavior_distribution' => $behaviorDistribution,
                'features' => [
                    (float)$visitCount,
                    (float)$cartValue,
                    (float)$dslsVal,
                    (float)$avgOrderValue,
                    (float)$cartAbandonmentCount,
                    (float)$isNewCustomer,
                    (float)$cartToAvgRatio,
                    (float)$abandonmentRate,
                    (float)$recencyScore
                ]
            ];
        } catch (\Exception $e) {
            $this->logger->error('Failed to extract 9 features', ['error' => $e->getMessage()]);
            return [
                'customer_id' => $customerId,
                'behavior_distribution' => [],
                'features' => [0.0, 0.0, 0.0, 0.0, 0.0, 1.0, 1.0, 0.0, 0.0]
            ];
        }
    }
}
