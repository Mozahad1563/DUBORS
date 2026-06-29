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

use BrainStation23\Dubors\Helper\Config;
use BrainStation23\Dubors\Service\MlServiceClient;
use Magento\Framework\App\ResourceConnection;
use Psr\Log\LoggerInterface;

class UpdateModelStatus
{
    private const MODEL_STALENESS_HOURS = 24;

    /**
     * @var MlServiceClient
     */
    private MlServiceClient $mlServiceClient;

    /**
     * @var Config
     */
    private Config $config;

    /**
     * @var ResourceConnection
     */
    private ResourceConnection $resourceConnection;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * UpdateModelStatus constructor.
     *
     * @param MlServiceClient $mlServiceClient
     * @param Config $config
     * @param ResourceConnection $resourceConnection
     * @param LoggerInterface $logger
     */
    public function __construct(
        MlServiceClient $mlServiceClient,
        Config $config,
        ResourceConnection $resourceConnection,
        LoggerInterface $logger
    ) {
        $this->mlServiceClient = $mlServiceClient;
        $this->config = $config;
        $this->resourceConnection = $resourceConnection;
        $this->logger = $logger;
    }

    /**
     * Execute model status update check.
     *
     * This cron job:
     * - Queries the ML service for model status updates
     * - Marks stale models for retraining
     * - Cleans up old model training records
     * - Alerts administrators if models are failing
     *
     * @return void
     */
    public function execute(): void
    {
        try {
            if (!$this->config->isMlServiceEnabled()) {
                $this->logger->info('ML service is disabled, skipping model status update');
                return;
            }

            $this->logger->info('Starting model status update cron job');

            // Check for stale models
            $staleCount = $this->markStaleModels();

            // Retrieve current model status from ML service
            $modelStatus = $this->getModelStatusFromService();

            // Update model training records
            $updatedCount = $this->updateModelTrainingRecords($modelStatus);

            // Cleanup old training logs
            $cleanupCount = $this->cleanupOldTrainingRecords();

            $this->logger->info(sprintf(
                'Model status update completed: %d stale, %d updated, %d cleanup',
                $staleCount,
                $updatedCount,
                $cleanupCount
            ));
        } catch (\Exception $e) {
            $this->logger->error(sprintf(
                'Fatal error in model status update cron: %s',
                $e->getMessage()
            ));
            throw $e;
        }
    }

    /**
     * Mark models that haven't been updated in 24 hours as stale.
     *
     * @return int Number of models marked as stale
     */
    private function markStaleModels(): int
    {
        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName('vendor_dubors_model_training');

        try {
            $stalenessThreshold = new \DateTime();
            $stalenessThreshold->modify(sprintf('-%d hours', self::MODEL_STALENESS_HOURS));

            $updatedRows = $connection->update(
                $table,
                ['status' => 'stale'],
                [
                    'status = ?' => 'completed',
                    'updated_at < ?' => $stalenessThreshold->format('Y-m-d H:i:s')
                ]
            );

            if ($updatedRows > 0) {
                $this->logger->info(sprintf('Marked %d models as stale', $updatedRows));
            }

            return $updatedRows;
        } catch (\Exception $e) {
            $this->logger->error(sprintf('Error marking stale models: %s', $e->getMessage()));
            return 0;
        }
    }

    /**
     * Get model status from ML service.
     *
     * @return array|null
     */
    private function getModelStatusFromService(): ?array
    {
        try {
            return $this->mlServiceClient->getModelStatus();
        } catch (\Exception $e) {
            $this->logger->warning(sprintf(
                'Could not fetch model status from ML service: %s',
                $e->getMessage()
            ));
            return null;
        }
    }

    /**
     * Update model training records with status from service.
     *
     * @param array|null $modelStatus
     * @return int Number of updated records
     */
    private function updateModelTrainingRecords(?array $modelStatus): int
    {
        if (null === $modelStatus) {
            return 0;
        }

        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName('vendor_dubors_model_training');
        $updatedCount = 0;

        try {
            foreach ($modelStatus as $model) {
                $modelId = $model['id'] ?? null;
                $status = $model['status'] ?? 'unknown';
                $accuracy = $model['accuracy'] ?? null;

                if ($modelId) {
                    $updateData = [
                        'status' => $status,
                        'updated_at' => (new \DateTime())->format('Y-m-d H:i:s'),
                    ];

                    if ($accuracy !== null) {
                        $updateData['accuracy'] = $accuracy;
                    }

                    $connection->update($table, $updateData, ['model_id = ?' => $modelId]);
                    $updatedCount++;
                }
            }

            $this->logger->info(sprintf('Updated %d model training records', $updatedCount));
            return $updatedCount;
        } catch (\Exception $e) {
            $this->logger->error(sprintf('Error updating model training records: %s', $e->getMessage()));
            return 0;
        }
    }

    /**
     * Cleanup training records older than 90 days.
     *
     * @return int Number of deleted records
     */
    private function cleanupOldTrainingRecords(): int
    {
        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName('vendor_dubors_model_training');

        try {
            $cutoffDate = new \DateTime();
            $cutoffDate->modify('-90 days');

            $affectedRows = $connection->delete(
                $table,
                [
                    'created_at < ?' => $cutoffDate->format('Y-m-d H:i:s'),
                    'status IN (?)' => ['failed', 'cancelled']
                ]
            );

            if ($affectedRows > 0) {
                $this->logger->info(sprintf('Cleaned up %d old training records', $affectedRows));
            }

            return $affectedRows;
        } catch (\Exception $e) {
            $this->logger->error(sprintf('Error cleaning up training records: %s', $e->getMessage()));
            return 0;
        }
    }
}
