<?php

namespace BrainStation23\Dubors\Service\Analytics;

use Magento\Framework\App\ResourceConnection;
use Psr\Log\LoggerInterface;

/**
 * Revenue Attribution Service
 * 
 * Tracks and attributes revenue to recommendations:
 * - Direct attribution (immediate purchase)
 * - Multi-touch attribution
 * - First touch vs last touch
 * - Time to conversion tracking
 */
class RevenueAttribution
{
    /**
     * @var ResourceConnection
     */
    private ResourceConnection $resourceConnection;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * RevenueAttribution constructor
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
     * Track revenue attribution for a conversion
     *
     * @param int $customerId
     * @param float $orderValue
     * @param array $touchpoints [timestamp, recommendation_id, channel]
     * @return int Attribution ID
     */
    public function trackAttribution(int $customerId, float $orderValue, array $touchpoints = []): int
    {
        try {
            $connection = $this->resourceConnection->getConnection();
            $table = $this->resourceConnection->getTableName('vendor_dubors_revenue_attribution');

            // Calculate attribution split across touchpoints
            $attribution = $this->calculateMultiTouchAttribution($orderValue, $touchpoints);

            $data = [
                'customer_id' => $customerId,
                'order_value' => $orderValue,
                'attribution_model' => 'multi_touch',
                'touchpoint_count' => count($touchpoints),
                'first_touch_id' => $touchpoints[0]['recommendation_id'] ?? null,
                'last_touch_id' => end($touchpoints)['recommendation_id'] ?? null,
                'attribution_data' => json_encode($attribution),
                'created_at' => date('Y-m-d H:i:s')
            ];

            $connection->insert($table, $data);
            
            // Get the inserted ID
            $select = $connection->select()
                ->from($table, ['id'])
                ->order('id DESC')
                ->limit(1);
            $attributionId = (int)$connection->fetchOne($select);

            // Distribute attributed revenue to recommendations
            foreach ($attribution as $recId => $amount) {
                $this->updateRecommendationRevenue($recId, $amount);
            }

            $this->logger->info("Revenue attribution tracked for customer {$customerId}, attribution ID: {$attributionId}");
            return $attributionId;
        } catch (\Exception $e) {
            $this->logger->error('Error tracking revenue attribution: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Calculate multi-touch attribution split
     *
     * Uses linear attribution by default
     *
     * @param float $orderValue
     * @param array $touchpoints
     * @return array [recommendation_id => attributed_revenue]
     */
    private function calculateMultiTouchAttribution(float $orderValue, array $touchpoints = []): array
    {
        if (empty($touchpoints)) {
            return [];
        }

        $count = count($touchpoints);
        $attribution = [];

        // Linear attribution: equal split across all touchpoints
        $perTouchpoint = $orderValue / $count;

        foreach ($touchpoints as $touch) {
            $recId = $touch['recommendation_id'] ?? null;
            if ($recId) {
                $attribution[$recId] = ($attribution[$recId] ?? 0) + $perTouchpoint;
            }
        }

        return $attribution;
    }

    /**
     * Update recommendation with attributed revenue
     *
     * @param int $recommendationId
     * @param float $revenue
     * @return bool
     */
    private function updateRecommendationRevenue(int $recommendationId, float $revenue): bool
    {
        try {
            $connection = $this->resourceConnection->getConnection();
            $table = $this->resourceConnection->getTableName('vendor_dubors_recommendation');

            $connection->update(
                $table,
                [
                    'attributed_revenue' => new \Zend_Db_Expr("attributed_revenue + {$revenue}"),
                    'conversion_count' => new \Zend_Db_Expr('conversion_count + 1')
                ],
                ['recommendation_id = ?' => $recommendationId]
            );

            return true;
        } catch (\Exception $e) {
            $this->logger->error("Error updating recommendation revenue: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get total attributed revenue for a recommendation
     *
     * @param int $recommendationId
     * @return float
     */
    public function getRecommendationRevenue(int $recommendationId): float
    {
        try {
            $connection = $this->resourceConnection->getConnection();
            $table = $this->resourceConnection->getTableName('vendor_dubors_recommendation');

            $select = $connection->select()
                ->from($table, ['attributed_revenue'])
                ->where('recommendation_id = ?', $recommendationId);

            $result = $connection->fetchOne($select);
            return $result ? round($result, 2) : 0.0;
        } catch (\Exception $e) {
            $this->logger->error('Error getting recommendation revenue: ' . $e->getMessage());
            return 0.0;
        }
    }

    /**
     * Get revenue by attribution model comparison
     *
     * @param string $from
     * @param string $to
     * @return array
     */
    public function getRevenueByModel(string $from, string $to): array
    {
        try {
            $connection = $this->resourceConnection->getConnection();
            $table = $this->resourceConnection->getTableName('vendor_dubors_revenue_attribution');

            $select = $connection->select()
                ->from($table, [
                    'model' => 'attribution_model',
                    'total_revenue' => 'SUM(order_value)',
                    'count' => 'COUNT(*)',
                    'avg_revenue' => 'AVG(order_value)'
                ])
                ->where('DATE(created_at) >= ?', $from)
                ->where('DATE(created_at) <= ?', $to)
                ->group('attribution_model');

            $result = $connection->fetchAll($select);

            $models = [];
            foreach ($result as $row) {
                $models[$row['model']] = [
                    'total_revenue' => round($row['total_revenue'], 2),
                    'count' => (int)$row['count'],
                    'average_revenue' => round($row['avg_revenue'], 2)
                ];
            }

            return $models;
        } catch (\Exception $e) {
            $this->logger->error('Error getting revenue by model: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get time to conversion statistics
     *
     * @param string $from
     * @param string $to
     * @return array
     */
    public function getTimeToConversion(string $from, string $to): array
    {
        try {
            $connection = $this->resourceConnection->getConnection();
            $table = $this->resourceConnection->getTableName('vendor_dubors_revenue_attribution');

            $select = $connection->select()
                ->from($table, [
                    'avg_days' => 'AVG(DATEDIFF(created_at, first_touch_date))',
                    'min_days' => 'MIN(DATEDIFF(created_at, first_touch_date))',
                    'max_days' => 'MAX(DATEDIFF(created_at, first_touch_date))',
                    'median_days' => 'SUBSTRING_INDEX(GROUP_CONCAT(DATEDIFF(created_at, first_touch_date) ORDER BY DATEDIFF(created_at, first_touch_date)), ",", 1)'
                ])
                ->where('DATE(created_at) >= ?', $from)
                ->where('DATE(created_at) <= ?', $to)
                ->where('first_touch_date IS NOT NULL');

            $result = $connection->fetchRow($select);

            return [
                'average_days' => $result['avg_days'] ?? 0,
                'minimum_days' => $result['min_days'] ?? 0,
                'maximum_days' => $result['max_days'] ?? 0,
                'distribution' => $this->getConversionDistribution($from, $to)
            ];
        } catch (\Exception $e) {
            $this->logger->error('Error getting time to conversion: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get distribution of conversions by days to convert
     *
     * @param string $from
     * @param string $to
     * @return array
     */
    private function getConversionDistribution(string $from, string $to): array
    {
        try {
            $connection = $this->resourceConnection->getConnection();
            $table = $this->resourceConnection->getTableName('vendor_dubors_revenue_attribution');

            $select = $connection->select()
                ->from($table, [
                    'days_bucket' => 'CASE 
                        WHEN DATEDIFF(created_at, first_touch_date) <= 1 THEN "same_day"
                        WHEN DATEDIFF(created_at, first_touch_date) <= 7 THEN "1_7_days"
                        WHEN DATEDIFF(created_at, first_touch_date) <= 30 THEN "8_30_days"
                        ELSE "30plus_days"
                    END',
                    'count' => 'COUNT(*)',
                    'revenue' => 'SUM(order_value)'
                ])
                ->where('DATE(created_at) >= ?', $from)
                ->where('DATE(created_at) <= ?', $to)
                ->where('first_touch_date IS NOT NULL')
                ->group('days_bucket');

            return $connection->fetchAll($select);
        } catch (\Exception $e) {
            $this->logger->error('Error getting conversion distribution: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get channel attribution breakdown
     *
     * @param string $from
     * @param string $to
     * @return array
     */
    public function getChannelAttribution(string $from, string $to): array
    {
        try {
            $connection = $this->resourceConnection->getConnection();
            $table = $this->resourceConnection->getTableName('vendor_dubors_revenue_attribution');

            $select = $connection->select()
                ->from($table, [
                    'channel' => 'channel',
                    'count' => 'COUNT(*)',
                    'revenue' => 'SUM(order_value)',
                    'avg_revenue' => 'AVG(order_value)'
                ])
                ->where('DATE(created_at) >= ?', $from)
                ->where('DATE(created_at) <= ?', $to)
                ->group('channel')
                ->order('revenue DESC');

            $results = $connection->fetchAll($select);

            $channels = [];
            foreach ($results as $row) {
                $channels[$row['channel']] = [
                    'conversions' => (int)$row['count'],
                    'total_revenue' => round($row['revenue'], 2),
                    'average_revenue' => round($row['avg_revenue'], 2)
                ];
            }

            return $channels;
        } catch (\Exception $e) {
            $this->logger->error('Error getting channel attribution: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get top attributed recommendations
     *
     * @param int $limit
     * @param string $from
     * @param string $to
     * @return array
     */
    public function getTopRecommendations(int $limit = 10, string $from = '', string $to = ''): array
    {
        try {
            $connection = $this->resourceConnection->getConnection();
            $table = $this->resourceConnection->getTableName('vendor_dubors_recommendation');

            $select = $connection->select()
                ->from($table, [
                    'recommendation_id',
                    'product_id',
                    'attributed_revenue',
                    'conversion_count',
                    'roi' => new \Zend_Db_Expr('ROUND(attributed_revenue / NULLIF(cost, 0), 2)')
                ]);

            if ($from && $to) {
                $select->where('DATE(created_at) >= ?', $from)
                    ->where('DATE(created_at) <= ?', $to);
            }

            $select->order('attributed_revenue DESC')
                ->limit($limit);

            return $connection->fetchAll($select);
        } catch (\Exception $e) {
            $this->logger->error('Error getting top recommendations: ' . $e->getMessage());
            return [];
        }
    }
}
