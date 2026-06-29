<?php
/**
 * BrainStation23 Dubors Extension
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * @category   BrainStation23
 * @package    BrainStation23_Dubors
 * @author     BrainStation23 Team
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace BrainStation23\Dubors\Service\Scoring;

use Magento\Framework\App\ResourceConnection;
use Psr\Log\LoggerInterface;

class CustomScoringEngine
{
    private const SCORING_RULE_TABLE = 'vendor_dubors_scoring_rule';

    /**
     * @var ResourceConnection
     */
    private ResourceConnection $resourceConnection;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * CustomScoringEngine constructor.
     *
     * @param ResourceConnection $resourceConnection
     * @param LoggerInterface $logger
     */
    public function __construct(
        ResourceConnection $resourceConnection,
        LoggerInterface $logger
    ) {
        $this->resourceConnection = $resourceConnection;
        $this->logger = $logger;
    }

    /**
     * Apply custom scoring algorithm to recommendations.
     *
     * Weighted scoring combines multiple factors:
     * - ML model score (weight: configurable)
     * - User engagement match (weight: configurable)
     * - Popularity score (weight: configurable)
     * - Category preference (weight: configurable)
     * - Price range match (weight: configurable)
     *
     * @param int $customerId
     * @param array $recommendations Base recommendations with ML scores
     * @param array $customerProfile Customer profile data
     * @return array Rescored recommendations
     */
    public function scoreRecommendations(
        int $customerId,
        array $recommendations,
        array $customerProfile = []
    ): array {
        try {
            $this->logger->debug(sprintf(
                'Applying custom scoring to %d recommendations for customer %d',
                count($recommendations),
                $customerId
            ));

            $weights = $this->getWeights();
            $rescored = [];

            foreach ($recommendations as $recommendation) {
                $productId = $recommendation['product_id'] ?? null;
                $baseScore = $recommendation['score'] ?? 0;

                if (!$productId) {
                    continue;
                }

                $finalScore = $this->calculateScore(
                    $productId,
                    $customerId,
                    $baseScore,
                    $customerProfile,
                    $weights
                );

                $rescored[] = [
                    'product_id' => $productId,
                    'score' => $finalScore,
                    'base_score' => $baseScore,
                    'components' => $this->getScoreComponents($productId, $customerId, $customerProfile, $weights),
                ];
            }

            // Sort by final score descending
            usort($rescored, fn($a, $b) => $b['score'] <=> $a['score']);

            return $rescored;
        } catch (\Exception $e) {
            $this->logger->error(sprintf('Error in custom scoring: %s', $e->getMessage()));
            return $recommendations; // Fallback to original scores
        }
    }

    /**
     * Get scoring weights from configuration.
     *
     * @return array Weights for each scoring component
     */
    private function getWeights(): array
    {
        try {
            $connection = $this->resourceConnection->getConnection();
            $table = $this->resourceConnection->getTableName(self::SCORING_RULE_TABLE);

            $query = $connection->select()
                ->from($table)
                ->where('is_active = ?', 1)
                ->limit(1);

            $rule = $connection->fetchRow($query);

            if ($rule) {
                return json_decode($rule['weights'] ?? '{}', true);
            }

            // Default weights
            return [
                'ml_model' => 0.35,
                'engagement' => 0.25,
                'popularity' => 0.20,
                'category' => 0.15,
                'price' => 0.05,
            ];
        } catch (\Exception $e) {
            $this->logger->warning(sprintf('Error loading weights: %s', $e->getMessage()));
            return [
                'ml_model' => 0.35,
                'engagement' => 0.25,
                'popularity' => 0.20,
                'category' => 0.15,
                'price' => 0.05,
            ];
        }
    }

    /**
     * Calculate final score for a product.
     *
     * @param int $productId
     * @param int $customerId
     * @param float $baseScore ML model score
     * @param array $customerProfile Customer profile
     * @param array $weights Scoring weights
     * @return float Final score (0-100)
     */
    private function calculateScore(
        int $productId,
        int $customerId,
        float $baseScore,
        array $customerProfile,
        array $weights
    ): float {
        $mlScore = $this->normalizeScore($baseScore);
        $engagementScore = $this->calculateEngagementScore($productId, $customerProfile);
        $popularityScore = $this->calculatePopularityScore($productId);
        $categoryScore = $this->calculateCategoryScore($productId, $customerProfile);
        $priceScore = $this->calculatePriceScore($productId, $customerProfile);

        $finalScore = (
            $mlScore * $weights['ml_model'] +
            $engagementScore * $weights['engagement'] +
            $popularityScore * $weights['popularity'] +
            $categoryScore * $weights['category'] +
            $priceScore * $weights['price']
        );

        return (float) round($finalScore, 2);
    }

    /**
     * Normalize score to 0-100 range.
     *
     * @param float $score
     * @return float
     */
    private function normalizeScore(float $score): float
    {
        return min(100, max(0, (float) $score));
    }

    /**
     * Calculate engagement score based on product similarity to user preferences.
     *
     * @param int $productId
     * @param array $customerProfile
     * @return float Score 0-100
     */
    private function calculateEngagementScore(int $productId, array $customerProfile): float
    {
        if (empty($customerProfile['category_preferences'])) {
            return 50; // Default neutral score
        }

        // Simplified: check if product matches preferred categories
        $connection = $this->resourceConnection->getConnection();
        $categoryProductTable = $this->resourceConnection->getTableName('catalog_category_product');

        $query = $connection->select()
            ->from($categoryProductTable, ['category_id'])
            ->where('product_id = ?', $productId);

        $productCategories = array_column($connection->fetchAll($query), 'category_id');
        $preferredCategories = $customerProfile['category_preferences'] ?? [];

        if (empty($productCategories) || empty($preferredCategories)) {
            return 50;
        }

        $matches = count(array_intersect($productCategories, $preferredCategories));
        $score = ($matches / count($preferredCategories)) * 100;

        return (float) min(100, $score);
    }

    /**
     * Calculate popularity score based on views and sales.
     *
     * @param int $productId
     * @return float Score 0-100
     */
    private function calculatePopularityScore(int $productId): float
    {
        try {
            $connection = $this->resourceConnection->getConnection();
            $behaviorTable = $this->resourceConnection->getTableName('vendor_dubors_user_behavior');

            $query = $connection->select()
                ->from($behaviorTable, [new \Zend_Db_Expr('COUNT(*) as total')])
                ->where('product_id = ?', $productId);

            $result = $connection->fetchRow($query);
            $totalInteractions = $result['total'] ?? 0;

            // Normalize: 100 interactions = score 100
            return min(100, ($totalInteractions / 100) * 100);
        } catch (\Exception $e) {
            return 50;
        }
    }

    /**
     * Calculate category score based on customer's past category purchases.
     *
     * @param int $productId
     * @param array $customerProfile
     * @return float Score 0-100
     */
    private function calculateCategoryScore(int $productId, array $customerProfile): float
    {
        return $this->calculateEngagementScore($productId, $customerProfile);
    }

    /**
     * Calculate price score based on customer's price sensitivity.
     *
     * @param int $productId
     * @param array $customerProfile
     * @return float Score 0-100
     */
    private function calculatePriceScore(int $productId, array $customerProfile): float
    {
        try {
            $connection = $this->resourceConnection->getConnection();
            $productTable = $this->resourceConnection->getTableName('catalog_product_entity');
            $priceTable = $this->resourceConnection->getTableName('catalog_product_entity_decimal');

            // Get product price (simplified)
            $query = $connection->select()
                ->from($productTable)
                ->where('entity_id = ?', $productId);

            $product = $connection->fetchRow($query);

            if (!$product) {
                return 50;
            }

            $avgPrice = $customerProfile['avg_purchase_price'] ?? 50;
            $productPrice = $product['price'] ?? 0;

            // Score based on proximity to average price
            $priceDiff = abs($productPrice - $avgPrice);
            $maxDiff = $avgPrice * 0.5; // Allow 50% variance

            if ($priceDiff > $maxDiff) {
                return 30; // Low score for very different prices
            }

            return 50 + (50 * (1 - ($priceDiff / $maxDiff))); // 50-100 range
        } catch (\Exception $e) {
            return 50;
        }
    }

    /**
     * Get detailed score components.
     *
     * @param int $productId
     * @param int $customerId
     * @param array $customerProfile
     * @param array $weights
     * @return array Score breakdown
     */
    private function getScoreComponents(
        int $productId,
        int $customerId,
        array $customerProfile,
        array $weights
    ): array {
        $baseScore = 75; // Placeholder ML score

        return [
            'ml_score' => $baseScore * $weights['ml_model'],
            'engagement_score' => $this->calculateEngagementScore($productId, $customerProfile) * $weights['engagement'],
            'popularity_score' => $this->calculatePopularityScore($productId) * $weights['popularity'],
            'category_score' => $this->calculateCategoryScore($productId, $customerProfile) * $weights['category'],
            'price_score' => $this->calculatePriceScore($productId, $customerProfile) * $weights['price'],
        ];
    }

    /**
     * Create custom scoring rule.
     *
     * @param array $ruleData Rule configuration with weights
     * @return int Rule ID
     */
    public function createScoringRule(array $ruleData): int
    {
        try {
            $connection = $this->resourceConnection->getConnection();
            $table = $this->resourceConnection->getTableName(self::SCORING_RULE_TABLE);

            $data = [
                'rule_name' => $ruleData['name'] ?? 'Default Scoring',
                'description' => $ruleData['description'] ?? '',
                'weights' => json_encode($ruleData['weights'] ?? [
                    'ml_model' => 0.35,
                    'engagement' => 0.25,
                    'popularity' => 0.20,
                    'category' => 0.15,
                    'price' => 0.05,
                ]),
                'is_active' => $ruleData['is_active'] ?? 1,
                'created_at' => (new \DateTime())->format('Y-m-d H:i:s'),
                'updated_at' => (new \DateTime())->format('Y-m-d H:i:s'),
            ];

            $connection->insert($table, $data);
            // Get last inserted ID by querying the table
            $query = $connection->select()
                ->from($table, ['rule_id'])
                ->order('rule_id DESC')
                ->limit(1);
            $result = $connection->fetchRow($query);
            $ruleId = (int) ($result['rule_id'] ?? 0);

            $this->logger->info(sprintf('Created scoring rule: %d - %s', $ruleId, $ruleData['name']));

            return $ruleId;
        } catch (\Exception $e) {
            $this->logger->error(sprintf('Error creating scoring rule: %s', $e->getMessage()));
            throw $e;
        }
    }

    /**
     * Update scoring rule weights.
     *
     * @param int $ruleId
     * @param array $weights New weights
     * @return void
     */
    public function updateScoringWeights(int $ruleId, array $weights): void
    {
        try {
            $connection = $this->resourceConnection->getConnection();
            $table = $this->resourceConnection->getTableName(self::SCORING_RULE_TABLE);

            $data = [
                'weights' => json_encode($weights),
                'updated_at' => (new \DateTime())->format('Y-m-d H:i:s'),
            ];

            $connection->update($table, $data, ['rule_id = ?' => $ruleId]);

            $this->logger->info(sprintf('Updated scoring weights for rule: %d', $ruleId));
        } catch (\Exception $e) {
            $this->logger->error(sprintf('Error updating weights: %s', $e->getMessage()));
            throw $e;
        }
    }
}
