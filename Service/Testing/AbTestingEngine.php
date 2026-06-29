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

namespace BrainStation23\Dubors\Service\Testing;

use Magento\Framework\App\ResourceConnection;
use Psr\Log\LoggerInterface;

class AbTestingEngine
{
    private const TEST_TABLE = 'vendor_dubors_ab_test';
    private const VARIANT_TABLE = 'vendor_dubors_test_variant';

    /**
     * @var ResourceConnection
     */
    private ResourceConnection $resourceConnection;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * AbTestingEngine constructor.
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
     * Create a new A/B test.
     *
     * @param array $data Test data including name, description, variants
     * @return int Test ID
     */
    public function createTest(array $data): int
    {
        try {
            $connection = $this->resourceConnection->getConnection();
            $table = $this->resourceConnection->getTableName(self::TEST_TABLE);

            $testData = [
                'test_name' => $data['name'] ?? 'Unnamed Test',
                'description' => $data['description'] ?? '',
                'status' => 'draft',
                'split_percentage' => $data['split_percentage'] ?? 50,
                'created_at' => (new \DateTime())->format('Y-m-d H:i:s'),
                'updated_at' => (new \DateTime())->format('Y-m-d H:i:s'),
            ];

            $connection->insert($table, $testData);
            // Get last inserted ID by querying the table
            $query = $connection->select()
                ->from($table, ['test_id'])
                ->order('test_id DESC')
                ->limit(1);
            $result = $connection->fetchRow($query);
            $testId = (int) ($result['test_id'] ?? 0);

            $this->logger->info(sprintf('Created A/B test: %d - %s', $testId, $data['name']));

            return $testId;
        } catch (\Exception $e) {
            $this->logger->error(sprintf('Error creating A/B test: %s', $e->getMessage()));
            throw $e;
        }
    }

    /**
     * Add variant to test.
     *
     * @param int $testId
     * @param array $variantData Variant configuration
     * @return int Variant ID
     */
    public function addVariant(int $testId, array $variantData): int
    {
        try {
            $connection = $this->resourceConnection->getConnection();
            $table = $this->resourceConnection->getTableName(self::VARIANT_TABLE);

            $data = [
                'test_id' => $testId,
                'variant_key' => $variantData['key'] ?? 'control',
                'variant_name' => $variantData['name'] ?? 'Control',
                'variant_config' => json_encode($variantData['config'] ?? []),
                'traffic_percentage' => $variantData['traffic_percentage'] ?? 50,
                'conversions' => 0,
                'impressions' => 0,
                'created_at' => (new \DateTime())->format('Y-m-d H:i:s'),
                'updated_at' => (new \DateTime())->format('Y-m-d H:i:s'),
            ];

            $connection->insert($table, $data);
            // Get last inserted ID by querying the table
            $query = $connection->select()
                ->from($table, ['variant_id'])
                ->order('variant_id DESC')
                ->limit(1);
            $result = $connection->fetchRow($query);
            $variantId = (int) ($result['variant_id'] ?? 0);

            $this->logger->info(sprintf('Added variant %d to test %d', $variantId, $testId));

            return $variantId;
        } catch (\Exception $e) {
            $this->logger->error(sprintf('Error adding variant: %s', $e->getMessage()));
            throw $e;
        }
    }

    /**
     * Assign customer to test variant.
     *
     * @param int $customerId
     * @param int $testId
     * @return string Assigned variant key
     */
    public function assignCustomerVariant(int $customerId, int $testId): string
    {
        try {
            $connection = $this->resourceConnection->getConnection();
            $testTable = $this->resourceConnection->getTableName(self::TEST_TABLE);
            $variantTable = $this->resourceConnection->getTableName(self::VARIANT_TABLE);

            // Get test split percentage
            $testQuery = $connection->select()
                ->from($testTable)
                ->where('test_id = ?', $testId);

            $test = $connection->fetchRow($testQuery);

            if (!$test) {
                throw new \Exception(sprintf('Test %d not found', $testId));
            }

            // Get variants for test
            $variantQuery = $connection->select()
                ->from($variantTable)
                ->where('test_id = ?', $testId)
                ->order('traffic_percentage DESC');

            $variants = $connection->fetchAll($variantQuery);

            if (empty($variants)) {
                throw new \Exception(sprintf('No variants for test %d', $testId));
            }

            // Assign variant based on customer ID hash
            $assignedVariant = $this->selectVariant($customerId, $variants);

            return $assignedVariant['variant_key'];
        } catch (\Exception $e) {
            $this->logger->error(sprintf(
                'Error assigning customer %d to test %d: %s',
                $customerId,
                $testId,
                $e->getMessage()
            ));
            throw $e;
        }
    }

    /**
     * Select variant based on traffic distribution.
     *
     * @param int $customerId
     * @param array $variants
     * @return array Selected variant
     */
    private function selectVariant(int $customerId, array $variants): array
    {
        // Use customer ID hash to consistently assign to same variant
        $hash = (int) (abs(crc32($customerId)) % 100);

        $cumulative = 0;
        foreach ($variants as $variant) {
            $cumulative += $variant['traffic_percentage'];
            if ($hash < $cumulative) {
                return $variant;
            }
        }

        // Fallback to first variant
        return $variants[0];
    }

    /**
     * Track test impression (recommendation shown).
     *
     * @param int $testId
     * @param int $variantId
     * @param int $customerId
     * @return void
     */
    public function trackImpression(int $testId, int $variantId, int $customerId): void
    {
        try {
            $connection = $this->resourceConnection->getConnection();
            $table = $this->resourceConnection->getTableName(self::VARIANT_TABLE);

            $connection->update(
                $table,
                ['impressions' => new \Zend_Db_Expr('impressions + 1')],
                ['variant_id = ?' => $variantId]
            );

            $this->logger->debug(sprintf(
                'Tracked impression for variant %d (test %d, customer %d)',
                $variantId,
                $testId,
                $customerId
            ));
        } catch (\Exception $e) {
            $this->logger->warning(sprintf('Error tracking impression: %s', $e->getMessage()));
        }
    }

    /**
     * Track test conversion (customer clicked/purchased).
     *
     * @param int $testId
     * @param int $variantId
     * @param int $customerId
     * @return void
     */
    public function trackConversion(int $testId, int $variantId, int $customerId): void
    {
        try {
            $connection = $this->resourceConnection->getConnection();
            $table = $this->resourceConnection->getTableName(self::VARIANT_TABLE);

            $connection->update(
                $table,
                ['conversions' => new \Zend_Db_Expr('conversions + 1')],
                ['variant_id = ?' => $variantId]
            );

            $this->logger->debug(sprintf(
                'Tracked conversion for variant %d (test %d, customer %d)',
                $variantId,
                $testId,
                $customerId
            ));
        } catch (\Exception $e) {
            $this->logger->warning(sprintf('Error tracking conversion: %s', $e->getMessage()));
        }
    }

    /**
     * Get test results and statistics.
     *
     * @param int $testId
     * @return array Test results including conversion rates
     */
    public function getTestResults(int $testId): array
    {
        try {
            $connection = $this->resourceConnection->getConnection();
            $table = $this->resourceConnection->getTableName(self::VARIANT_TABLE);

            $query = $connection->select()
                ->from($table)
                ->where('test_id = ?', $testId);

            $variants = $connection->fetchAll($query);

            $results = [];
            foreach ($variants as $variant) {
                $conversions = (int) $variant['conversions'];
                $impressions = (int) $variant['impressions'];
                $conversionRate = $impressions > 0 ? ($conversions / $impressions) * 100 : 0;

                $results[] = [
                    'variant_id' => $variant['variant_id'],
                    'variant_name' => $variant['variant_name'],
                    'variant_key' => $variant['variant_key'],
                    'impressions' => $impressions,
                    'conversions' => $conversions,
                    'conversion_rate' => round($conversionRate, 2),
                ];
            }

            $this->logger->info(sprintf('Retrieved results for test %d: %d variants', $testId, count($results)));

            return $results;
        } catch (\Exception $e) {
            $this->logger->error(sprintf('Error getting test results: %s', $e->getMessage()));
            throw $e;
        }
    }

    /**
     * Determine winner of A/B test with statistical significance.
     *
     * @param int $testId
     * @return array Winner information with significance level
     */
    public function determineWinner(int $testId): array
    {
        try {
            $results = $this->getTestResults($testId);

            if (empty($results)) {
                return [
                    'winner_id' => null,
                    'winner_name' => 'No data',
                    'significance' => 0,
                    'recommendation' => 'Insufficient data',
                ];
            }

            // Sort by conversion rate
            usort($results, fn($a, $b) => $b['conversion_rate'] <=> $a['conversion_rate']);

            $winner = $results[0];
            $runner_up = $results[1] ?? null;

            // Calculate statistical significance (simplified Chi-squared)
            $significance = 0;
            if ($runner_up && $winner['impressions'] > 30 && $runner_up['impressions'] > 30) {
                $significance = $this->calculateSignificance($winner, $runner_up);
            }

            return [
                'winner_id' => $winner['variant_id'],
                'winner_name' => $winner['variant_name'],
                'winner_rate' => $winner['conversion_rate'],
                'runner_up_rate' => $runner_up['conversion_rate'] ?? 0,
                'significance' => round($significance, 2),
                'recommendation' => $significance > 0.95 ? 'Implement winner' : 'Continue testing',
            ];
        } catch (\Exception $e) {
            $this->logger->error(sprintf('Error determining winner: %s', $e->getMessage()));
            throw $e;
        }
    }

    /**
     * Calculate statistical significance between two variants.
     *
     * @param array $variant1
     * @param array $variant2
     * @return float Significance level (0-1)
     */
    private function calculateSignificance(array $variant1, array $variant2): float
    {
        $conversions1 = $variant1['conversions'];
        $conversions2 = $variant2['conversions'];
        $impressions1 = $variant1['impressions'];
        $impressions2 = $variant2['impressions'];

        // Chi-squared test
        $rate1 = $conversions1 / $impressions1;
        $rate2 = $conversions2 / $impressions2;
        $pooled = ($conversions1 + $conversions2) / ($impressions1 + $impressions2);

        $se = sqrt($pooled * (1 - $pooled) * (1 / $impressions1 + 1 / $impressions2));

        if ($se == 0) {
            return 0;
        }

        $z = ($rate1 - $rate2) / $se;
        // Very simplified significance calculation
        return min(1, abs($z) / 1.96);
    }

    /**
     * End A/B test.
     *
     * @param int $testId
     * @return void
     */
    public function endTest(int $testId): void
    {
        try {
            $connection = $this->resourceConnection->getConnection();
            $table = $this->resourceConnection->getTableName(self::TEST_TABLE);

            $connection->update(
                $table,
                [
                    'status' => 'completed',
                    'updated_at' => (new \DateTime())->format('Y-m-d H:i:s'),
                ],
                ['test_id = ?' => $testId]
            );

            $this->logger->info(sprintf('Ended A/B test %d', $testId));
        } catch (\Exception $e) {
            $this->logger->error(sprintf('Error ending test: %s', $e->getMessage()));
            throw $e;
        }
    }
}
