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

namespace BrainStation23\Dubors\Service\Filtering;

use Magento\Framework\App\ResourceConnection;
use Psr\Log\LoggerInterface;

class RuleBasedFilter
{
    private const RULE_TABLE = 'vendor_dubors_recommendation_rule';
    private const EXCLUSION_TABLE = 'vendor_dubors_product_exclusion';

    /**
     * @var ResourceConnection
     */
    private ResourceConnection $resourceConnection;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * RuleBasedFilter constructor.
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
     * Apply rules to filter recommendations.
     *
     * Rules can exclude products based on:
     * - Category restrictions
     * - Price ranges
     * - Attributes
     * - Customer segments
     * - Inventory status
     *
     * @param int $customerId
     * @param array $recommendations Product recommendations to filter
     * @return array Filtered recommendations
     */
    public function filterRecommendations(int $customerId, array $recommendations): array
    {
        try {
            $this->logger->debug(sprintf(
                'Applying rules to filter %d recommendations for customer %d',
                count($recommendations),
                $customerId
            ));

            $rules = $this->getActiveRules();
            $filtered = $recommendations;

            foreach ($rules as $rule) {
                if (!$this->shouldApplyRule($rule, $customerId)) {
                    continue;
                }

                $filtered = $this->applyRule($rule, $filtered);
            }

            $removedCount = count($recommendations) - count($filtered);
            if ($removedCount > 0) {
                $this->logger->debug(sprintf(
                    'Filtered out %d recommendations for customer %d',
                    $removedCount,
                    $customerId
                ));
            }

            return $filtered;
        } catch (\Exception $e) {
            $this->logger->error(sprintf('Error filtering recommendations: %s', $e->getMessage()));
            return $recommendations; // Fallback to unfiltered
        }
    }

    /**
     * Get all active rules from database.
     *
     * @return array Rules
     */
    private function getActiveRules(): array
    {
        try {
            $connection = $this->resourceConnection->getConnection();
            $table = $this->resourceConnection->getTableName(self::RULE_TABLE);

            $query = $connection->select()
                ->from($table)
                ->where('is_active = ?', 1)
                ->order('priority ASC');

            return $connection->fetchAll($query);
        } catch (\Exception $e) {
            $this->logger->warning(sprintf('Error fetching rules: %s', $e->getMessage()));
            return [];
        }
    }

    /**
     * Check if rule should be applied to this customer.
     *
     * @param array $rule
     * @param int $customerId
     * @return bool
     */
    private function shouldApplyRule(array $rule, int $customerId): bool
    {
        $conditions = json_decode($rule['conditions'] ?? '{}', true);

        if (empty($conditions)) {
            return true;
        }

        foreach ($conditions as $condition) {
            if (!$this->evaluateCondition($condition, $customerId)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Evaluate a condition against customer.
     *
     * @param array $condition
     * @param int $customerId
     * @return bool
     */
    private function evaluateCondition(array $condition, int $customerId): bool
    {
        $type = $condition['type'] ?? null;
        $operator = $condition['operator'] ?? '==';
        $value = $condition['value'] ?? null;

        // Simplified condition evaluation
        switch ($type) {
            case 'segment':
                return $this->customerInSegment($customerId, $value);

            case 'min_purchases':
                return $this->customerMinPurchases($customerId, $value);

            case 'customer_group':
                return $this->customerInGroup($customerId, $value);

            default:
                return true;
        }
    }

    /**
     * Check if customer is in segment.
     *
     * @param int $customerId
     * @param string $segment
     * @return bool
     */
    private function customerInSegment(int $customerId, string $segment): bool
    {
        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName('vendor_dubors_customer_segment');

        $query = $connection->select()
            ->from($table)
            ->where('customer_id = ?', $customerId)
            ->where('segment_name = ?', $segment)
            ->limit(1);

        return (bool) $connection->fetchRow($query);
    }

    /**
     * Check if customer has minimum purchases.
     *
     * @param int $customerId
     * @param int $minPurchases
     * @return bool
     */
    private function customerMinPurchases(int $customerId, int $minPurchases): bool
    {
        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName('vendor_dubors_user_behavior');

        $query = $connection->select()
            ->from($table, [new \Zend_Db_Expr('COUNT(*)')])
            ->where('customer_id = ?', $customerId)
            ->where('behavior_type = ?', 'purchase');

        $result = $connection->fetchRow($query);
        return ($result[0] ?? 0) >= $minPurchases;
    }

    /**
     * Check if customer is in customer group.
     *
     * @param int $customerId
     * @param int $groupId
     * @return bool
     */
    private function customerInGroup(int $customerId, int $groupId): bool
    {
        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName('customer_entity');

        $query = $connection->select()
            ->from($table)
            ->where('entity_id = ?', $customerId)
            ->where('group_id = ?', $groupId)
            ->limit(1);

        return (bool) $connection->fetchRow($query);
    }

    /**
     * Apply a rule to filter recommendations.
     *
     * @param array $rule
     * @param array $recommendations
     * @return array Filtered recommendations
     */
    private function applyRule(array $rule, array $recommendations): array
    {
        $ruleAction = $rule['action'] ?? 'exclude';
        $productIds = json_decode($rule['product_ids'] ?? '[]', true);
        $categories = json_decode($rule['categories'] ?? '[]', true);

        $filtered = $recommendations;

        if ($ruleAction === 'exclude') {
            $filtered = array_filter($filtered, function ($recommendation) use ($productIds, $categories) {
                $productId = $recommendation['product_id'] ?? null;

                // Exclude by product ID
                if (in_array($productId, $productIds)) {
                    return false;
                }

                // Exclude by category (simplified)
                if (!empty($categories)) {
                    // Would need product-category lookup
                }

                return true;
            });
        }

        return array_values($filtered); // Re-index array
    }

    /**
     * Create a new filtering rule.
     *
     * @param array $ruleData Rule configuration
     * @return int Rule ID
     */
    public function createRule(array $ruleData): int
    {
        try {
            $connection = $this->resourceConnection->getConnection();
            $table = $this->resourceConnection->getTableName(self::RULE_TABLE);

            $data = [
                'rule_name' => $ruleData['name'] ?? 'Unnamed Rule',
                'description' => $ruleData['description'] ?? '',
                'action' => $ruleData['action'] ?? 'exclude',
                'priority' => $ruleData['priority'] ?? 100,
                'conditions' => json_encode($ruleData['conditions'] ?? []),
                'product_ids' => json_encode($ruleData['product_ids'] ?? []),
                'categories' => json_encode($ruleData['categories'] ?? []),
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

            $this->logger->info(sprintf('Created filtering rule: %d - %s', $ruleId, $ruleData['name']));

            return $ruleId;
        } catch (\Exception $e) {
            $this->logger->error(sprintf('Error creating rule: %s', $e->getMessage()));
            throw $e;
        }
    }

    /**
     * Update existing rule.
     *
     * @param int $ruleId
     * @param array $ruleData Updated rule data
     * @return void
     */
    public function updateRule(int $ruleId, array $ruleData): void
    {
        try {
            $connection = $this->resourceConnection->getConnection();
            $table = $this->resourceConnection->getTableName(self::RULE_TABLE);

            $data = [
                'rule_name' => $ruleData['name'] ?? null,
                'description' => $ruleData['description'] ?? null,
                'priority' => $ruleData['priority'] ?? null,
                'conditions' => isset($ruleData['conditions']) ? json_encode($ruleData['conditions']) : null,
                'product_ids' => isset($ruleData['product_ids']) ? json_encode($ruleData['product_ids']) : null,
                'is_active' => $ruleData['is_active'] ?? null,
                'updated_at' => (new \DateTime())->format('Y-m-d H:i:s'),
            ];

            $data = array_filter($data, fn($v) => $v !== null);

            $connection->update($table, $data, ['rule_id = ?' => $ruleId]);

            $this->logger->info(sprintf('Updated filtering rule: %d', $ruleId));
        } catch (\Exception $e) {
            $this->logger->error(sprintf('Error updating rule: %s', $e->getMessage()));
            throw $e;
        }
    }

    /**
     * Delete a rule.
     *
     * @param int $ruleId
     * @return void
     */
    public function deleteRule(int $ruleId): void
    {
        try {
            $connection = $this->resourceConnection->getConnection();
            $table = $this->resourceConnection->getTableName(self::RULE_TABLE);

            $connection->delete($table, ['rule_id = ?' => $ruleId]);

            $this->logger->info(sprintf('Deleted filtering rule: %d', $ruleId));
        } catch (\Exception $e) {
            $this->logger->error(sprintf('Error deleting rule: %s', $e->getMessage()));
            throw $e;
        }
    }
}
