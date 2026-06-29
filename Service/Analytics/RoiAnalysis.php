<?php

namespace BrainStation23\Dubors\Service\Analytics;

use Magento\Framework\App\ResourceConnection;
use Psr\Log\LoggerInterface;

/**
 * ROI Analysis Service
 * 
 * Tracks and calculates return on investment:
 * - Revenue by segment ROI
 * - A/B test ROI comparison
 * - Recommendation set ROI
 * - Cost analysis
 */
class RoiAnalysis
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
     * RoiAnalysis constructor
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
     * Calculate ROI by segment
     *
     * @param string $from
     * @param string $to
     * @return array
     */
    public function getRoiBySegment(string $from, string $to): array
    {
        try {
            $connection = $this->resourceConnection->getConnection();
            $eventTable = $this->resourceConnection->getTableName('dubors_recommendation_event');

            $select = $connection->select()
                ->from($eventTable, [
                    'segment' => 'customer_segment',
                    'revenue' => 'SUM(CASE WHEN event_type = "conversion" THEN order_value ELSE 0 END)',
                    'impressions' => 'COUNT(CASE WHEN event_type = "impression" THEN 1 END)',
                    'clicks' => 'COUNT(CASE WHEN event_type = "click" THEN 1 END)',
                    'conversions' => 'COUNT(CASE WHEN event_type = "conversion" THEN 1 END)'
                ])
                ->where('DATE(created_at) >= ?', $from)
                ->where('DATE(created_at) <= ?', $to)
                ->group('customer_segment');

            $results = $connection->fetchAll($select);

            $roi = [];
            foreach ($results as $row) {
                $impressions = (int)$row['impressions'];
                $clicks = (int)$row['clicks'];
                $conversions = (int)$row['conversions'];
                $revenue = (float)$row['revenue'];

                $roi[$row['segment']] = [
                    'revenue' => round($revenue, 2),
                    'impressions' => $impressions,
                    'clicks' => $clicks,
                    'conversions' => $conversions,
                    'ctr' => $impressions > 0 ? round(($clicks / $impressions) * 100, 2) : 0,
                    'conversion_rate' => $clicks > 0 ? round(($conversions / $clicks) * 100, 2) : 0,
                    'revenue_per_impression' => $impressions > 0 ? round($revenue / $impressions, 4) : 0,
                    'revenue_per_click' => $clicks > 0 ? round($revenue / $clicks, 2) : 0,
                    'roi_percent' => 0  // Placeholder - needs cost data
                ];
            }

            return $roi;
        } catch (\Exception $e) {
            $this->logger->error('Error calculating ROI by segment: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Calculate A/B test ROI comparison
     *
     * @param int $testId
     * @return array
     */
    public function getABTestROI(int $testId): array
    {
        try {
            $connection = $this->resourceConnection->getConnection();
            $testTable = $this->resourceConnection->getTableName('dubors_ab_test');
            $variantTable = $this->resourceConnection->getTableName('dubors_test_variant');
            $eventTable = $this->resourceConnection->getTableName('dubors_recommendation_event');

            // Get test details
            $testSelect = $connection->select()
                ->from($testTable)
                ->where('test_id = ?', $testId);
            $testData = $connection->fetchRow($testSelect);

            if (!$testData) {
                return [];
            }

            // Get variant ROI data
            $variantSelect = $connection->select()
                ->from(['v' => $variantTable], [
                    'variant_id' => 'v.variant_id',
                    'variant_key' => 'v.variant_key',
                    'revenue' => 'SUM(CASE WHEN e.event_type = "conversion" THEN e.order_value ELSE 0 END)',
                    'impressions' => 'COUNT(CASE WHEN e.event_type = "impression" THEN 1 END)',
                    'clicks' => 'COUNT(CASE WHEN e.event_type = "click" THEN 1 END)',
                    'conversions' => 'COUNT(CASE WHEN e.event_type = "conversion" THEN 1 END)'
                ])
                ->joinLeft(['e' => $eventTable], 'v.variant_id = e.variant_id', [])
                ->where('v.test_id = ?', $testId)
                ->group('v.variant_id');

            $variants = $connection->fetchAll($variantSelect);

            $roi = [];
            foreach ($variants as $variant) {
                $impressions = (int)$variant['impressions'];
                $clicks = (int)$variant['clicks'];
                $conversions = (int)$variant['conversions'];
                $revenue = (float)$variant['revenue'];

                $roi[$variant['variant_key']] = [
                    'variant_id' => $variant['variant_id'],
                    'revenue' => round($revenue, 2),
                    'impressions' => $impressions,
                    'clicks' => $clicks,
                    'conversions' => $conversions,
                    'conversion_rate' => $clicks > 0 ? round(($conversions / $clicks) * 100, 2) : 0,
                    'revenue_per_click' => $clicks > 0 ? round($revenue / $clicks, 2) : 0
                ];
            }

            // Calculate winner
            $winner = '';
            $highestRpr = 0;
            foreach ($roi as $key => $data) {
                if ($data['revenue_per_click'] > $highestRpr) {
                    $highestRpr = $data['revenue_per_click'];
                    $winner = $key;
                }
            }

            return [
                'test_id' => $testId,
                'test_name' => $testData['name'] ?? '',
                'variants' => $roi,
                'winner' => $winner,
                'lift' => $this->calculateLift($roi, $winner)
            ];
        } catch (\Exception $e) {
            $this->logger->error('Error calculating A/B test ROI: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Calculate lift percentage for A/B test
     *
     * @param array $roi
     * @param string $winner
     * @return float
     */
    private function calculateLift(array $roi, string $winner): float
    {
        try {
            if (empty($winner) || empty($roi[$winner])) {
                return 0.0;
            }

            $winnerRpr = $roi[$winner]['revenue_per_click'];

            // Compare against first non-winner
            foreach ($roi as $key => $data) {
                if ($key !== $winner && $data['revenue_per_click'] > 0) {
                    $lift = (($winnerRpr - $data['revenue_per_click']) / $data['revenue_per_click']) * 100;
                    return round($lift, 2);
                }
            }

            return 0.0;
        } catch (\Exception $e) {
            $this->logger->error('Error calculating lift: ' . $e->getMessage());
            return 0.0;
        }
    }

    /**
     * Calculate overall ROI metrics
     *
     * @param string $from
     * @param string $to
     * @param float $totalCost
     * @return array
     */
    public function calculateOverallROI(string $from, string $to, float $totalCost = 0): array
    {
        try {
            $connection = $this->resourceConnection->getConnection();
            $eventTable = $this->resourceConnection->getTableName('dubors_recommendation_event');

            $select = $connection->select()
                ->from($eventTable, [
                    'revenue' => 'SUM(CASE WHEN event_type = "conversion" THEN order_value ELSE 0 END)',
                    'impressions' => 'COUNT(CASE WHEN event_type = "impression" THEN 1 END)',
                    'clicks' => 'COUNT(CASE WHEN event_type = "click" THEN 1 END)',
                    'conversions' => 'COUNT(CASE WHEN event_type = "conversion" THEN 1 END)'
                ])
                ->where('DATE(created_at) >= ?', $from)
                ->where('DATE(created_at) <= ?', $to);

            $result = $connection->fetchRow($select);

            if (!$result) {
                return [];
            }

            $revenue = (float)$result['revenue'];
            $impressions = (int)$result['impressions'];
            $clicks = (int)$result['clicks'];
            $conversions = (int)$result['conversions'];

            $roiPercent = $totalCost > 0 ? (($revenue - $totalCost) / $totalCost) * 100 : 0;

            return [
                'period_from' => $from,
                'period_to' => $to,
                'total_revenue' => round($revenue, 2),
                'total_cost' => round($totalCost, 2),
                'net_profit' => round($revenue - $totalCost, 2),
                'roi_percent' => round($roiPercent, 2),
                'impressions' => $impressions,
                'clicks' => $clicks,
                'conversions' => $conversions,
                'revenue_per_impression' => $impressions > 0 ? round($revenue / $impressions, 4) : 0,
                'cost_per_impression' => $impressions > 0 ? round($totalCost / $impressions, 4) : 0
            ];
        } catch (\Exception $e) {
            $this->logger->error('Error calculating overall ROI: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get ROI by recommendation type
     *
     * @param string $from
     * @param string $to
     * @return array
     */
    public function getRoiByRecommendationType(string $from, string $to): array
    {
        try {
            $connection = $this->resourceConnection->getConnection();
            $recTable = $this->resourceConnection->getTableName('dubors_recommendation');
            $eventTable = $this->resourceConnection->getTableName('dubors_recommendation_event');

            $select = $connection->select()
                ->from(['r' => $recTable], [
                    'type' => 'recommendation_type',
                    'revenue' => 'SUM(CASE WHEN e.event_type = "conversion" THEN e.order_value ELSE 0 END)',
                    'impressions' => 'COUNT(CASE WHEN e.event_type = "impression" THEN 1 END)',
                    'conversions' => 'COUNT(CASE WHEN e.event_type = "conversion" THEN 1 END)'
                ])
                ->joinLeft(['e' => $eventTable], 'r.recommendation_id = e.recommendation_id', [])
                ->where('DATE(r.created_at) >= ?', $from)
                ->where('DATE(r.created_at) <= ?', $to)
                ->group('r.recommendation_type');

            $results = $connection->fetchAll($select);

            $roi = [];
            foreach ($results as $row) {
                $impressions = (int)$row['impressions'];
                $conversions = (int)$row['conversions'];
                $revenue = (float)$row['revenue'];

                $roi[$row['type']] = [
                    'revenue' => round($revenue, 2),
                    'impressions' => $impressions,
                    'conversions' => $conversions,
                    'conversion_rate' => $impressions > 0 ? round(($conversions / $impressions) * 100, 2) : 0,
                    'revenue_per_impression' => $impressions > 0 ? round($revenue / $impressions, 4) : 0
                ];
            }

            return $roi;
        } catch (\Exception $e) {
            $this->logger->error('Error getting ROI by recommendation type: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get cost-benefit analysis
     *
     * @param string $from
     * @param string $to
     * @param float $systemCost
     * @param float $marketingCost
     * @return array
     */
    public function getCostBenefitAnalysis(string $from, string $to, float $systemCost = 0, float $marketingCost = 0): array
    {
        try {
            $totalCost = $systemCost + $marketingCost;
            $roi = $this->calculateOverallROI($from, $to, $totalCost);

            $roi['system_cost'] = round($systemCost, 2);
            $roi['marketing_cost'] = round($marketingCost, 2);
            $roi['total_cost'] = round($totalCost, 2);
            $roi['cost_breakdown'] = [
                'system_percent' => $totalCost > 0 ? round(($systemCost / $totalCost) * 100, 2) : 0,
                'marketing_percent' => $totalCost > 0 ? round(($marketingCost / $totalCost) * 100, 2) : 0
            ];

            return $roi;
        } catch (\Exception $e) {
            $this->logger->error('Error calculating cost-benefit analysis: ' . $e->getMessage());
            return [];
        }
    }
}
