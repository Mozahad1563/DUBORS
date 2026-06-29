<?php

namespace BrainStation23\Dubors\Service\Analytics;

use Magento\Framework\App\ResourceConnection;
use Psr\Log\LoggerInterface;

/**
 * KPI Calculator Service
 * 
 * Calculates key performance indicators for recommendations:
 * - Click-through rate (CTR)
 * - Conversion rate
 * - Average order value (AOV)
 * - Revenue per recommendation
 * - Customer engagement metrics
 */
class KpiCalculator
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
     * KpiCalculator constructor
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
     * Calculate overall KPIs for recommendations
     *
     * @param array $filters [period_from, period_to, segment, recommendation_type]
     * @return array
     */
    public function calculateOverallKpis(array $filters = []): array
    {
        try {
            $connection = $this->resourceConnection->getConnection();
            $from = $filters['period_from'] ?? date('Y-m-d', strtotime('-30 days'));
            $to = $filters['period_to'] ?? date('Y-m-d');

            $ctr = $this->calculateClickThroughRate($from, $to, $filters);
            $conversionRate = $this->calculateConversionRate($from, $to, $filters);
            $aov = $this->calculateAverageOrderValue($from, $to, $filters);
            $rpr = $this->calculateRevenuePerRecommendation($from, $to, $filters);
            $engagement = $this->calculateEngagementScore($from, $to, $filters);

            return [
                'period_from' => $from,
                'period_to' => $to,
                'click_through_rate' => $ctr,
                'conversion_rate' => $conversionRate,
                'average_order_value' => $aov,
                'revenue_per_recommendation' => $rpr,
                'engagement_score' => $engagement,
                'calculated_at' => date('Y-m-d H:i:s')
            ];
        } catch (\Exception $e) {
            $this->logger->error('Error calculating overall KPIs: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Calculate click-through rate (clicks / impressions)
     *
     * @param string $from
     * @param string $to
     * @param array $filters
     * @return float
     */
    public function calculateClickThroughRate(string $from, string $to, array $filters = []): float
    {
        try {
            $connection = $this->resourceConnection->getConnection();
            $table = $this->resourceConnection->getTableName('vendor_dubors_recommendation_event');

            $select = $connection->select()
                ->from($table, ['impressions' => 'COUNT(CASE WHEN event_type = "impression" THEN 1 END)',
                    'clicks' => 'COUNT(CASE WHEN event_type = "click" THEN 1 END)'])
                ->where('DATE(created_at) >= ?', $from)
                ->where('DATE(created_at) <= ?', $to);

            if (!empty($filters['segment'])) {
                $select->where('customer_segment = ?', $filters['segment']);
            }

            $result = $connection->fetchRow($select);

            if (!$result || $result['impressions'] == 0) {
                return 0.0;
            }

            $ctr = ($result['clicks'] / $result['impressions']) * 100;
            return round($ctr, 2);
        } catch (\Exception $e) {
            $this->logger->error('Error calculating CTR: ' . $e->getMessage());
            return 0.0;
        }
    }

    /**
     * Calculate conversion rate (conversions / clicks)
     *
     * @param string $from
     * @param string $to
     * @param array $filters
     * @return float
     */
    public function calculateConversionRate(string $from, string $to, array $filters = []): float
    {
        try {
            $connection = $this->resourceConnection->getConnection();
            $table = $this->resourceConnection->getTableName('vendor_dubors_recommendation_event');

            $select = $connection->select()
                ->from($table, ['clicks' => 'COUNT(CASE WHEN event_type = "click" THEN 1 END)',
                    'conversions' => 'COUNT(CASE WHEN event_type = "conversion" THEN 1 END)'])
                ->where('DATE(created_at) >= ?', $from)
                ->where('DATE(created_at) <= ?', $to);

            if (!empty($filters['segment'])) {
                $select->where('customer_segment = ?', $filters['segment']);
            }

            $result = $connection->fetchRow($select);

            if (!$result || $result['clicks'] == 0) {
                return 0.0;
            }

            $rate = ($result['conversions'] / $result['clicks']) * 100;
            return round($rate, 2);
        } catch (\Exception $e) {
            $this->logger->error('Error calculating conversion rate: ' . $e->getMessage());
            return 0.0;
        }
    }

    /**
     * Calculate average order value from recommendations
     *
     * @param string $from
     * @param string $to
     * @param array $filters
     * @return float
     */
    public function calculateAverageOrderValue(string $from, string $to, array $filters = []): float
    {
        try {
            $connection = $this->resourceConnection->getConnection();
            $table = $this->resourceConnection->getTableName('vendor_dubors_recommendation_event');

            $select = $connection->select()
                ->from($table, ['avg_value' => 'AVG(order_value)'])
                ->where('DATE(created_at) >= ?', $from)
                ->where('DATE(created_at) <= ?', $to)
                ->where('event_type = ?', 'conversion')
                ->where('order_value > 0');

            if (!empty($filters['segment'])) {
                $select->where('customer_segment = ?', $filters['segment']);
            }

            $result = $connection->fetchOne($select);
            return $result ? round($result, 2) : 0.0;
        } catch (\Exception $e) {
            $this->logger->error('Error calculating AOV: ' . $e->getMessage());
            return 0.0;
        }
    }

    /**
     * Calculate revenue generated per recommendation
     *
     * @param string $from
     * @param string $to
     * @param array $filters
     * @return float
     */
    public function calculateRevenuePerRecommendation(string $from, string $to, array $filters = []): float
    {
        try {
            $connection = $this->resourceConnection->getConnection();
            $eventTable = $this->resourceConnection->getTableName('vendor_dubors_recommendation_event');
            $recTable = $this->resourceConnection->getTableName('vendor_dubors_recommendation');

            $select = $connection->select()
                ->from(['e' => $eventTable], ['total_revenue' => 'SUM(e.order_value)'])
                ->joinLeft(['r' => $recTable], 'e.recommendation_id = r.recommendation_id', [])
                ->where('DATE(e.created_at) >= ?', $from)
                ->where('DATE(e.created_at) <= ?', $to)
                ->where('e.event_type = ?', 'conversion');

            if (!empty($filters['segment'])) {
                $select->where('e.customer_segment = ?', $filters['segment']);
            }

            $totalRevenue = $connection->fetchOne($select);
            
            $countSelect = $connection->select()
                ->from($recTable, ['COUNT(*) as cnt'])
                ->where('DATE(created_at) >= ?', $from)
                ->where('DATE(created_at) <= ?', $to);

            if (!empty($filters['segment'])) {
                $countSelect->where('segment = ?', $filters['segment']);
            }

            $count = $connection->fetchOne($countSelect);

            if (!$count || $count == 0) {
                return 0.0;
            }

            return round($totalRevenue / $count, 2);
        } catch (\Exception $e) {
            $this->logger->error('Error calculating revenue per recommendation: ' . $e->getMessage());
            return 0.0;
        }
    }

    /**
     * Calculate overall engagement score (0-100)
     *
     * @param string $from
     * @param string $to
     * @param array $filters
     * @return float
     */
    public function calculateEngagementScore(string $from, string $to, array $filters = []): float
    {
        try {
            $ctr = $this->calculateClickThroughRate($from, $to, $filters);
            $conversionRate = $this->calculateConversionRate($from, $to, $filters);
            
            // Weight: CTR 40%, Conversion 60%
            $score = ($ctr * 0.4) + ($conversionRate * 0.6);
            
            return round($score, 2);
        } catch (\Exception $e) {
            $this->logger->error('Error calculating engagement score: ' . $e->getMessage());
            return 0.0;
        }
    }

    /**
     * Get KPIs by segment
     *
     * @param string $from
     * @param string $to
     * @return array
     */
    public function getKpisBySegment(string $from, string $to): array
    {
        try {
            $segments = ['vip', 'active', 'at_risk', 'dormant', 'new'];
            $results = [];

            foreach ($segments as $segment) {
                $results[$segment] = $this->calculateOverallKpis([
                    'period_from' => $from,
                    'period_to' => $to,
                    'segment' => $segment
                ]);
            }

            return $results;
        } catch (\Exception $e) {
            $this->logger->error('Error getting KPIs by segment: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get daily KPI trends
     *
     * @param string $from
     * @param string $to
     * @return array
     */
    public function getDailyTrends(string $from, string $to): array
    {
        try {
            $connection = $this->resourceConnection->getConnection();
            $table = $this->resourceConnection->getTableName('vendor_dubors_recommendation_event');

            $select = $connection->select()
                ->from($table, [
                    'date' => 'DATE(created_at)',
                    'impressions' => 'COUNT(CASE WHEN event_type = "impression" THEN 1 END)',
                    'clicks' => 'COUNT(CASE WHEN event_type = "click" THEN 1 END)',
                    'conversions' => 'COUNT(CASE WHEN event_type = "conversion" THEN 1 END)',
                    'revenue' => 'SUM(CASE WHEN event_type = "conversion" THEN order_value ELSE 0 END)'
                ])
                ->where('DATE(created_at) >= ?', $from)
                ->where('DATE(created_at) <= ?', $to)
                ->group('DATE(created_at)')
                ->order('DATE(created_at) ASC');

            $result = $connection->fetchAll($select);

            // Calculate derived metrics
            $trends = [];
            foreach ($result as $row) {
                $trends[] = [
                    'date' => $row['date'],
                    'impressions' => (int)$row['impressions'],
                    'clicks' => (int)$row['clicks'],
                    'conversions' => (int)$row['conversions'],
                    'revenue' => round($row['revenue'] ?? 0, 2),
                    'ctr' => $row['impressions'] > 0 ? round(($row['clicks'] / $row['impressions']) * 100, 2) : 0,
                    'conversion_rate' => $row['clicks'] > 0 ? round(($row['conversions'] / $row['clicks']) * 100, 2) : 0
                ];
            }

            return $trends;
        } catch (\Exception $e) {
            $this->logger->error('Error getting daily trends: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Save KPI snapshot to database
     *
     * @param array $kpis
     * @param string $label
     * @return bool
     */
    public function saveKpiSnapshot(array $kpis, string $label = ''): bool
    {
        try {
            $connection = $this->resourceConnection->getConnection();
            $table = $this->resourceConnection->getTableName('vendor_dubors_kpi_snapshot');

            $connection->insert($table, [
                'label' => $label,
                'kpi_data' => json_encode($kpis),
                'created_at' => date('Y-m-d H:i:s')
            ]);

            return true;
        } catch (\Exception $e) {
            $this->logger->error('Error saving KPI snapshot: ' . $e->getMessage());
            return false;
        }
    }
}
