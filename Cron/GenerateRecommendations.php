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

namespace BrainStation23\Dubors\Cron;

use BrainStation23\Dubors\Model\RecommendationFactory;
use BrainStation23\Dubors\Service\RecommendationEngine;
use Magento\Customer\Model\ResourceModel\Customer\CollectionFactory;
use Magento\Framework\App\ResourceConnection;
use Psr\Log\LoggerInterface;

class GenerateRecommendations
{
    private const BATCH_SIZE = 100;

    /**
     * @var RecommendationEngine
     */
    private RecommendationEngine $recommendationEngine;

    /**
     * @var RecommendationFactory
     */
    private RecommendationFactory $recommendationFactory;

    /**
     * @var CollectionFactory
     */
    private CollectionFactory $customerCollectionFactory;

    /**
     * @var ResourceConnection
     */
    private ResourceConnection $resourceConnection;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * GenerateRecommendations constructor.
     *
     * @param RecommendationEngine $recommendationEngine
     * @param RecommendationFactory $recommendationFactory
     * @param CollectionFactory $customerCollectionFactory
     * @param ResourceConnection $resourceConnection
     * @param LoggerInterface $logger
     */
    public function __construct(
        RecommendationEngine $recommendationEngine,
        RecommendationFactory $recommendationFactory,
        CollectionFactory $customerCollectionFactory,
        ResourceConnection $resourceConnection,
        LoggerInterface $logger
    ) {
        $this->recommendationEngine = $recommendationEngine;
        $this->recommendationFactory = $recommendationFactory;
        $this->customerCollectionFactory = $customerCollectionFactory;
        $this->resourceConnection = $resourceConnection;
        $this->logger = $logger;
    }

    /**
     * Execute batch recommendation generation for all customers.
     *
     * This cron job processes customers in batches to generate ML-based product
     * recommendations. It handles both new customers and existing customers needing
     * refreshed recommendations.
     *
     * @return void
     */
    public function execute(): void
    {
        try {
            $this->logger->info('Starting batch recommendation generation cron job');

            $customerCollection = $this->customerCollectionFactory->create();
            $totalCustomers = $customerCollection->getSize();

            if ($totalCustomers === 0) {
                $this->logger->info('No customers found for recommendation generation');
                return;
            }

            $this->logger->info(sprintf('Processing %d customers for recommendations', $totalCustomers));

            $processed = 0;
            $successful = 0;
            $failed = 0;
            $startTime = time();

            // Process customers in batches
            $currentBatch = 1;
            $maxBatches = ceil($totalCustomers / self::BATCH_SIZE);

            for ($page = 1; $page <= $maxBatches; $page++) {
                $batch = $this->customerCollectionFactory->create();
                $batch->setPageSize(self::BATCH_SIZE);
                $batch->setCurPage($page);

                foreach ($batch as $customer) {
                    try {
                        $customerId = (int) $customer->getId();
                        $recommendations = $this->recommendationEngine->generate($customerId);

                        if (!empty($recommendations)) {
                            $successful++;
                        }

                        $processed++;

                        // Log progress every 500 customers
                        if ($processed % 500 === 0) {
                            $this->logger->info(sprintf(
                                'Processed %d/%d customers (%.2f%% complete)',
                                $processed,
                                $totalCustomers,
                                ($processed / $totalCustomers) * 100
                            ));
                        }
                    } catch (\Exception $e) {
                        $failed++;
                        $this->logger->error(sprintf(
                            'Error generating recommendations for customer %d: %s',
                            $customerId ?? 'unknown',
                            $e->getMessage()
                        ));
                    }
                }

                $currentBatch++;
            }

            $elapsedTime = time() - $startTime;
            $averageTime = $processed > 0 ? round($elapsedTime / $processed, 3) : 0;

            $this->logger->info(sprintf(
                'Batch recommendation generation completed. Total: %d, Successful: %d, Failed: %d, Time: %ds, Avg: %.3fs/customer',
                $processed,
                $successful,
                $failed,
                $elapsedTime,
                $averageTime
            ));
        } catch (\Exception $e) {
            $this->logger->error(sprintf(
                'Fatal error in recommendation generation cron: %s',
                $e->getMessage()
            ));
            throw $e;
        }
    }

    /**
     * Save generated recommendations to the database.
     *
     * Stores recommendations with metadata and timestamps for tracking.
     *
     * @param int $customerId
     * @param array $recommendations Array of product IDs with scores
     * @return void
     */
    private function saveRecommendations(int $customerId, array $recommendations): void
    {
        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName('vendor_dubors_recommendation');

        // Delete previous recommendations to maintain freshness
        $connection->delete($table, ['customer_id = ?' => $customerId]);

        $now = new \DateTime();
        $now->modify('+30 days');

        foreach ($recommendations as $index => $recommendation) {
            $productId = $recommendation['product_id'] ?? null;
            $score = $recommendation['score'] ?? 0;

            if ($productId) {
                $connection->insert($table, [
                    'customer_id' => $customerId,
                    'product_id' => $productId,
                    'score' => $score,
                    'position' => $index + 1,
                    'status' => 'pending',
                    'expires_at' => $now->format('Y-m-d H:i:s'),
                    'created_at' => (new \DateTime())->format('Y-m-d H:i:s'),
                    'updated_at' => (new \DateTime())->format('Y-m-d H:i:s'),
                ]);
            }
        }
    }
}
