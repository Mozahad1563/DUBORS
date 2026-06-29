<?php

namespace BrainStation23\Dubors\Service\Analytics;

use Magento\Framework\App\ResourceConnection;
use Psr\Log\LoggerInterface;

/**
 * Customer Lifetime Value (CLV) Service
 * 
 * Calculates and tracks CLV metrics:
 * - Historical CLV (actual value to date)
 * - Predictive CLV (estimated future value)
 * - CLV by segment
 * - Customer value ranking
 */
class CustomerLifetimeValue
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
     * CustomerLifetimeValue constructor
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
     * Calculate historical CLV for a customer
     *
     * @param int $customerId
     * @return array
     */
    public function calculateHistoricalClv(int $customerId): array
    {
        try {
            $connection = $this->resourceConnection->getConnection();
            $orderTable = $this->resourceConnection->getTableName('sales_order');

            $select = $connection->select()
                ->from($orderTable, [
                    'total_revenue' => 'SUM(grand_total)',
                    'order_count' => 'COUNT(*)',
                    'avg_order_value' => 'AVG(grand_total)',
                    'first_order_date' => 'MIN(created_at)',
                    'last_order_date' => 'MAX(created_at)',
                    'days_as_customer' => 'DATEDIFF(MAX(created_at), MIN(created_at))'
                ])
                ->where('customer_id = ?', $customerId)
                ->where('status != ?', 'canceled');

            $result = $connection->fetchRow($select);

            if (!$result) {
                return [
                    'customer_id' => $customerId,
                    'total_revenue' => 0,
                    'order_count' => 0,
                    'avg_order_value' => 0,
                    'days_as_customer' => 0
                ];
            }

            return [
                'customer_id' => $customerId,
                'total_revenue' => round($result['total_revenue'] ?? 0, 2),
                'order_count' => (int)($result['order_count'] ?? 0),
                'avg_order_value' => round($result['avg_order_value'] ?? 0, 2),
                'first_order_date' => $result['first_order_date'],
                'last_order_date' => $result['last_order_date'],
                'days_as_customer' => (int)($result['days_as_customer'] ?? 0)
            ];
        } catch (\Exception $e) {
            $this->logger->error("Error calculating historical CLV for customer {$customerId}: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Calculate predictive CLV using past behavior
     *
     * Uses simple linear prediction model
     *
     * @param int $customerId
     * @param int $projectionMonths
     * @return float
     */
    public function calculatePredictiveClv(int $customerId, int $projectionMonths = 12): float
    {
        try {
            $historicalClv = $this->calculateHistoricalClv($customerId);

            if ($historicalClv['days_as_customer'] == 0 || $historicalClv['order_count'] == 0) {
                return 0.0;
            }

            // Calculate average revenue per day
            $avgRevenuePerDay = $historicalClv['total_revenue'] / max($historicalClv['days_as_customer'], 1);
            
            // Project forward
            $daysToProject = $projectionMonths * 30;
            $projectedRevenue = $avgRevenuePerDay * $daysToProject;
            
            // Retention adjustment (assume 5% monthly churn)
            $retentionRate = pow(0.95, $projectionMonths);
            $adjustedProjection = $projectedRevenue * $retentionRate;

            return round($adjustedProjection, 2);
        } catch (\Exception $e) {
            $this->logger->error("Error calculating predictive CLV: " . $e->getMessage());
            return 0.0;
        }
    }

    /**
     * Calculate total CLV (historical + predictive)
     *
     * @param int $customerId
     * @param int $projectionMonths
     * @return float
     */
    public function calculateTotalClv(int $customerId, int $projectionMonths = 12): float
    {
        $historical = $this->calculateHistoricalClv($customerId)['total_revenue'] ?? 0;
        $predictive = $this->calculatePredictiveClv($customerId, $projectionMonths);
        return round($historical + $predictive, 2);
    }

    /**
     * Get CLV distribution by segment
     *
     * @param string $from
     * @param string $to
     * @return array
     */
    public function getClvBySegment(string $from, string $to): array
    {
        try {
            $connection = $this->resourceConnection->getConnection();
            $segmentTable = $this->resourceConnection->getTableName('dubors_customer_segment');
            $orderTable = $this->resourceConnection->getTableName('sales_order');

            $select = $connection->select()
                ->from(['s' => $segmentTable], [
                    'segment' => 'segment_code',
                    'avg_clv' => 'AVG(o.grand_total)',
                    'total_revenue' => 'SUM(o.grand_total)',
                    'customer_count' => 'COUNT(DISTINCT s.customer_id)',
                    'order_count' => 'COUNT(o.entity_id)'
                ])
                ->joinLeft(['o' => $orderTable], 's.customer_id = o.customer_id AND DATE(o.created_at) >= ? AND DATE(o.created_at) <= ?', 
                    [$from, $to])
                ->group('segment_code')
                ->order('total_revenue DESC');

            $results = $connection->fetchAll($select);

            $segments = [];
            foreach ($results as $row) {
                $segments[$row['segment']] = [
                    'average_clv' => round($row['avg_clv'] ?? 0, 2),
                    'total_revenue' => round($row['total_revenue'] ?? 0, 2),
                    'customer_count' => (int)$row['customer_count'],
                    'order_count' => (int)$row['order_count']
                ];
            }

            return $segments;
        } catch (\Exception $e) {
            $this->logger->error('Error getting CLV by segment: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get top customers by CLV
     *
     * @param int $limit
     * @return array
     */
    public function getTopCustomersByCLV(int $limit = 20): array
    {
        try {
            $connection = $this->resourceConnection->getConnection();
            $orderTable = $this->resourceConnection->getTableName('sales_order');

            $select = $connection->select()
                ->from($orderTable, [
                    'customer_id',
                    'total_clv' => 'SUM(grand_total)',
                    'order_count' => 'COUNT(*)',
                    'avg_order_value' => 'AVG(grand_total)'
                ])
                ->where('status != ?', 'canceled')
                ->group('customer_id')
                ->order('total_clv DESC')
                ->limit($limit);

            $results = $connection->fetchAll($select);

            $customers = [];
            foreach ($results as $row) {
                $customers[] = [
                    'customer_id' => (int)$row['customer_id'],
                    'total_clv' => round($row['total_clv'], 2),
                    'order_count' => (int)$row['order_count'],
                    'average_order_value' => round($row['avg_order_value'], 2)
                ];
            }

            return $customers;
        } catch (\Exception $e) {
            $this->logger->error('Error getting top customers by CLV: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Calculate CLV percentile for a customer
     *
     * @param int $customerId
     * @return float (0-100)
     */
    public function getClvPercentile(int $customerId): float
    {
        try {
            $historicalClv = $this->calculateHistoricalClv($customerId)['total_revenue'] ?? 0;

            $connection = $this->resourceConnection->getConnection();
            $orderTable = $this->resourceConnection->getTableName('sales_order');

            // Count how many customers have lower CLV
            $select = $connection->select()
                ->from($orderTable, [
                    'count' => 'COUNT(DISTINCT customer_id)'
                ])
                ->where('SUM(grand_total) < ?', $historicalClv)
                ->where('status != ?', 'canceled')
                ->group('customer_id');

            $lowerCount = (int)$connection->fetchOne($select);

            // Get total customer count
            $totalSelect = $connection->select()
                ->from($orderTable, ['COUNT(DISTINCT customer_id) as cnt']);
            $totalCount = (int)$connection->fetchOne($totalSelect);

            if ($totalCount == 0) {
                return 0.0;
            }

            $percentile = ($lowerCount / $totalCount) * 100;
            return round($percentile, 2);
        } catch (\Exception $e) {
            $this->logger->error('Error calculating CLV percentile: ' . $e->getMessage());
            return 0.0;
        }
    }

    /**
     * Save CLV snapshot for tracking
     *
     * @param int $customerId
     * @param array $clvData
     * @return bool
     */
    public function saveClvSnapshot(int $customerId, array $clvData): bool
    {
        try {
            $connection = $this->resourceConnection->getConnection();
            $table = $this->resourceConnection->getTableName('dubors_clv_snapshot');

            $connection->insert($table, [
                'customer_id' => $customerId,
                'historical_clv' => $clvData['historical_clv'] ?? 0,
                'predictive_clv' => $clvData['predictive_clv'] ?? 0,
                'total_clv' => $clvData['total_clv'] ?? 0,
                'percentile' => $clvData['percentile'] ?? 0,
                'created_at' => date('Y-m-d H:i:s')
            ]);

            return true;
        } catch (\Exception $e) {
            $this->logger->error('Error saving CLV snapshot: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get CLV trend for a customer
     *
     * @param int $customerId
     * @param int $monthsBack
     * @return array
     */
    public function getClvTrend(int $customerId, int $monthsBack = 12): array
    {
        try {
            $connection = $this->resourceConnection->getConnection();
            $orderTable = $this->resourceConnection->getTableName('sales_order');

            $select = $connection->select()
                ->from($orderTable, [
                    'month' => 'DATE_FORMAT(created_at, "%Y-%m")',
                    'monthly_revenue' => 'SUM(grand_total)',
                    'cumulative_revenue' => 'SUM(SUM(grand_total)) OVER (ORDER BY DATE_FORMAT(created_at, "%Y-%m"))',
                    'order_count' => 'COUNT(*)'
                ])
                ->where('customer_id = ?', $customerId)
                ->where('created_at >= DATE_SUB(NOW(), INTERVAL ? MONTH)', $monthsBack)
                ->where('status != ?', 'canceled')
                ->group('DATE_FORMAT(created_at, "%Y-%m")')
                ->order('DATE_FORMAT(created_at, "%Y-%m") ASC');

            return $connection->fetchAll($select);
        } catch (\Exception $e) {
            $this->logger->error('Error getting CLV trend: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Calculate customer acquisition cost vs CLV ratio
     *
     * @param int $customerId
     * @param float $acquisitionCost
     * @return array
     */
    public function calculateCacToClvRatio(int $customerId, float $acquisitionCost = 0): array
    {
        try {
            $historicalClv = $this->calculateHistoricalClv($customerId)['total_revenue'] ?? 0;
            $predictiveClv = $this->calculatePredictiveClv($customerId);
            $totalClv = $historicalClv + $predictiveClv;

            $cacRatio = $acquisitionCost > 0 ? $totalClv / $acquisitionCost : 0;

            return [
                'customer_id' => $customerId,
                'total_clv' => round($totalClv, 2),
                'acquisition_cost' => round($acquisitionCost, 2),
                'clv_to_cac_ratio' => round($cacRatio, 2),
                'healthy' => $cacRatio >= 3 ? 'yes' : 'no'  // Rule of thumb: CLV should be 3x CAC
            ];
        } catch (\Exception $e) {
            $this->logger->error('Error calculating CAC to CLV ratio: ' . $e->getMessage());
            return [];
        }
    }
}
