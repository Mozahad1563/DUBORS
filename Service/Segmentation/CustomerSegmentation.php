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

namespace BrainStation23\Dubors\Service\Segmentation;

use Magento\Framework\App\ResourceConnection;
use Psr\Log\LoggerInterface;

class CustomerSegmentation
{
    private const SEGMENT_TABLE = 'vendor_dubors_customer_segment';
    private const BEHAVIOR_TABLE = 'vendor_dubors_user_behavior';

    /**
     * @var ResourceConnection
     */
    private ResourceConnection $resourceConnection;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * CustomerSegmentation constructor.
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
     * Segment customers based on RFM analysis and behavioral patterns.
     *
     * Segments:
     * - VIP: High frequency, high monetary value, recent activity
     * - Active: Regular purchasers with consistent engagement
     * - At-Risk: Previously active but declining engagement
     * - Dormant: No recent activity
     * - New: Less than 30 days activity
     *
     * @param int|null $customerId Optional customer to segment individually
     * @return array Segmentation results with statistics
     */
    public function segmentCustomers(?int $customerId = null): array
    {
        try {
            $this->logger->info('Starting customer segmentation');

            $segments = [
                'vip' => [],
                'active' => [],
                'at_risk' => [],
                'dormant' => [],
                'new' => [],
            ];

            if ($customerId) {
                $segment = $this->classifyCustomer($customerId);
                $segments[$segment][] = $customerId;
            } else {
                $segments = $this->classifyAllCustomers();
            }

            $this->saveSegments($segments);
            $this->logStatistics($segments);

            return $segments;
        } catch (\Exception $e) {
            $this->logger->error(sprintf('Error during customer segmentation: %s', $e->getMessage()));
            throw $e;
        }
    }

    /**
     * Classify a single customer into a segment.
     *
     * @param int $customerId
     * @return string Segment name
     */
    private function classifyCustomer(int $customerId): string
    {
        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName(self::BEHAVIOR_TABLE);

        $query = $connection->select()
            ->from($table)
            ->where('customer_id = ?', $customerId);

        $behaviors = $connection->fetchAll($query);

        if (empty($behaviors)) {
            return 'new';
        }

        $rfmMetrics = $this->calculateRfm($behaviors);

        return $this->determineSegment($rfmMetrics);
    }

    /**
     * Classify all customers into segments.
     *
     * @return array Segments with customer IDs
     */
    private function classifyAllCustomers(): array
    {
        $segments = [
            'vip' => [],
            'active' => [],
            'at_risk' => [],
            'dormant' => [],
            'new' => [],
        ];

        $connection = $this->resourceConnection->getConnection();
        $behaviorTable = $this->resourceConnection->getTableName('vendor_dubors_user_behavior');
        $customerTable = $this->resourceConnection->getTableName('customer_entity');

        // Get all customers with behavior
        $query = $connection->select()
            ->from(['b' => $behaviorTable], ['customer_id'])
            ->joinLeft(['c' => $customerTable], 'b.customer_id = c.entity_id', [])
            ->group('b.customer_id')
            ->distinct();

        $results = $connection->fetchAll($query);

        foreach ($results as $row) {
            $customerId = (int) $row['customer_id'];
            $segment = $this->classifyCustomer($customerId);
            $segments[$segment][] = $customerId;
        }

        // Add new customers (with no behavior yet)
        $noActivityQuery = $connection->select()
            ->from($customerTable, ['entity_id'])
            ->where('entity_id NOT IN (?)', $connection->select()->from($behaviorTable, ['customer_id']));

        $noActivityResults = $connection->fetchAll($noActivityQuery);
        foreach ($noActivityResults as $row) {
            $segments['new'][] = (int) $row['entity_id'];
        }

        return $segments;
    }

    /**
     * Calculate RFM metrics from behaviors.
     *
     * @param array $behaviors User behavior records
     * @return array RFM metrics
     */
    private function calculateRfm(array $behaviors): array
    {
        if (empty($behaviors)) {
            return [
                'recency' => PHP_INT_MAX,
                'frequency' => 0,
                'monetary' => 0,
                'engagement_score' => 0,
            ];
        }

        $now = new \DateTime();
        $latestDate = null;
        $totalValue = 0;
        $purchaseCount = 0;
        $totalEngagement = 0;

        foreach ($behaviors as $behavior) {
            $createdAt = new \DateTime($behavior['created_at']);
            $latestDate = $latestDate === null || $createdAt > $latestDate ? $createdAt : $latestDate;

            // Calculate engagement score based on behavior type
            $engagementValue = $this->getBehaviorValue($behavior['behavior_type']);
            $totalEngagement += $engagementValue;

            if ($behavior['behavior_type'] === 'purchase') {
                $purchaseCount++;
                $totalValue += $behavior['value'] ?? 0;
            }
        }

        $recency = $latestDate ? (int) $now->diff($latestDate)->days : PHP_INT_MAX;

        return [
            'recency' => $recency,
            'frequency' => count($behaviors),
            'monetary' => $totalValue,
            'purchases' => $purchaseCount,
            'engagement_score' => $totalEngagement,
        ];
    }

    /**
     * Get engagement value for behavior type.
     *
     * @param string $behaviorType
     * @return int
     */
    private function getBehaviorValue(string $behaviorType): int
    {
        $values = [
            'purchase' => 100,
            'add_to_cart' => 50,
            'wishlist_add' => 30,
            'product_view' => 10,
            'search' => 5,
        ];

        return $values[$behaviorType] ?? 0;
    }

    /**
     * Determine segment based on RFM metrics.
     *
     * @param array $rfm RFM metrics
     * @return string Segment name
     */
    private function determineSegment(array $rfm): string
    {
        $recency = $rfm['recency'] ?? PHP_INT_MAX;
        $frequency = $rfm['frequency'] ?? 0;
        $monetary = $rfm['monetary'] ?? 0;
        $engagement = $rfm['engagement_score'] ?? 0;

        // VIP: Recent, high frequency, high value
        if ($recency <= 30 && $frequency >= 10 && $monetary >= 500) {
            return 'vip';
        }

        // Active: Recent, regular engagement
        if ($recency <= 60 && $frequency >= 5) {
            return 'active';
        }

        // At-Risk: Was active but declining
        if ($recency > 60 && $recency <= 120 && $frequency >= 3) {
            return 'at_risk';
        }

        // Dormant: Inactive for 120+ days
        if ($recency > 120) {
            return 'dormant';
        }

        // New: Low activity
        return 'new';
    }

    /**
     * Save segments to database.
     *
     * @param array $segments Segmentation data
     * @return void
     */
    private function saveSegments(array $segments): void
    {
        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName(self::SEGMENT_TABLE);

        try {
            // Clear existing segments
            $connection->delete($table);

            $now = (new \DateTime())->format('Y-m-d H:i:s');

            foreach ($segments as $segmentName => $customerIds) {
                foreach ($customerIds as $customerId) {
                    $connection->insert($table, [
                        'customer_id' => $customerId,
                        'segment_name' => $segmentName,
                        'assigned_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }

            $this->logger->info('Segments saved to database');
        } catch (\Exception $e) {
            $this->logger->error(sprintf('Error saving segments: %s', $e->getMessage()));
            throw $e;
        }
    }

    /**
     * Log segmentation statistics.
     *
     * @param array $segments
     * @return void
     */
    private function logStatistics(array $segments): void
    {
        $stats = [];
        $total = 0;

        foreach ($segments as $name => $customers) {
            $count = count($customers);
            $stats[$name] = $count;
            $total += $count;
        }

        $this->logger->info(sprintf(
            'Segmentation complete: VIP=%d, Active=%d, At-Risk=%d, Dormant=%d, New=%d, Total=%d',
            $stats['vip'] ?? 0,
            $stats['active'] ?? 0,
            $stats['at_risk'] ?? 0,
            $stats['dormant'] ?? 0,
            $stats['new'] ?? 0,
            $total
        ));
    }

    /**
     * Get customers in a specific segment.
     *
     * @param string $segmentName
     * @return array Customer IDs
     */
    public function getSegmentCustomers(string $segmentName): array
    {
        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName(self::SEGMENT_TABLE);

        $query = $connection->select()
            ->from($table, ['customer_id'])
            ->where('segment_name = ?', $segmentName);

        $results = $connection->fetchAll($query);
        return array_column($results, 'customer_id');
    }
}
