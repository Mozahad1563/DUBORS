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
use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface;

class MonitorMlService
{
    private const HEALTH_CHECK_TIMEOUT = 10;
    private const MAX_CONSECUTIVE_FAILURES = 3;

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
     * MonitorMlService constructor.
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
     * Execute ML service health monitoring.
     *
     * This cron job:
     * - Performs regular health checks on the ML service
     * - Tracks consecutive failures for alerting
     * - Records service statistics for monitoring
     * - Triggers fallback mode if service is unavailable
     *
     * @return void
     */
    public function execute(): void
    {
        try {
            if (!$this->config->isMlServiceEnabled()) {
                $this->logger->debug('ML service is disabled, skipping health check');
                return;
            }

            $this->logger->info('Starting ML service health check cron job');

            $isAvailable = $this->checkServiceHealth();
            $this->recordHealthStatus($isAvailable);

            if ($isAvailable) {
                $this->logger->info('ML service health check passed');
                $this->resetConsecutiveFailures();
            } else {
                $this->logger->warning('ML service health check failed');
                $this->incrementConsecutiveFailures();
                $this->checkForAlertThreshold();
            }
        } catch (\Exception $e) {
            $this->logger->error(sprintf(
                'Fatal error in ML service monitoring cron: %s',
                $e->getMessage()
            ));
        }
    }

    /**
     * Perform health check on ML service.
     *
     * @return bool
     */
    private function checkServiceHealth(): bool
    {
        try {
            $startTime = microtime(true);
            $isAvailable = $this->mlServiceClient->isServiceAvailable();
            $responseTime = round((microtime(true) - $startTime) * 1000, 2); // milliseconds

            $this->logger->debug(sprintf(
                'ML service health check response time: %fms',
                $responseTime
            ));

            return $isAvailable;
        } catch (\Exception $e) {
            $this->logger->error(sprintf(
                'ML service health check failed with exception: %s',
                $e->getMessage()
            ));
            return false;
        }
    }

    /**
     * Record health status for monitoring and metrics.
     *
     * @param bool $isHealthy
     * @return void
     */
    private function recordHealthStatus(bool $isHealthy): void
    {
        try {
            $connection = $this->resourceConnection->getConnection();
            $table = $this->resourceConnection->getTableName('vendor_dubors_ml_service_monitor');

            $connection->insert($table, [
                'status' => $isHealthy ? 'healthy' : 'unhealthy',
                'checked_at' => (new \DateTime())->format('Y-m-d H:i:s'),
                'created_at' => (new \DateTime())->format('Y-m-d H:i:s'),
            ]);
        } catch (LocalizedException $e) {
            $this->logger->warning(sprintf(
                'Could not record health status (table may not exist): %s',
                $e->getMessage()
            ));
        } catch (\Exception $e) {
            $this->logger->error(sprintf(
                'Error recording health status: %s',
                $e->getMessage()
            ));
        }
    }

    /**
     * Reset consecutive failure count when service recovers.
     *
     * @return void
     */
    private function resetConsecutiveFailures(): void
    {
        try {
            $connection = $this->resourceConnection->getConnection();
            $table = $this->resourceConnection->getTableName('vendor_dubors_system_config');

            $connection->update(
                $table,
                ['config_value' => 0],
                ['config_key = ?' => 'ml_service_consecutive_failures']
            );

            $this->logger->debug('Reset consecutive ML service failures counter');
        } catch (\Exception $e) {
            $this->logger->warning(sprintf(
                'Could not reset failure counter: %s',
                $e->getMessage()
            ));
        }
    }

    /**
     * Increment the consecutive failure counter.
     *
     * @return void
     */
    private function incrementConsecutiveFailures(): void
    {
        try {
            $connection = $this->resourceConnection->getConnection();
            $table = $this->resourceConnection->getTableName('vendor_dubors_system_config');

            // Use raw SQL to increment
            $connection->query(
                sprintf(
                    "INSERT INTO %s (config_key, config_value) VALUES ('ml_service_consecutive_failures', 1) 
                    ON DUPLICATE KEY UPDATE config_value = config_value + 1",
                    $table
                )
            );

            $currentCount = $this->getConsecutiveFailureCount();
            $this->logger->warning(sprintf(
                'ML service failure count incremented to: %d',
                $currentCount
            ));
        } catch (\Exception $e) {
            $this->logger->warning(sprintf(
                'Could not increment failure counter: %s',
                $e->getMessage()
            ));
        }
    }

    /**
     * Get current consecutive failure count.
     *
     * @return int
     */
    private function getConsecutiveFailureCount(): int
    {
        try {
            $connection = $this->resourceConnection->getConnection();
            $table = $this->resourceConnection->getTableName('vendor_dubors_system_config');

            $result = $connection->fetchOne(
                $connection->select()
                    ->from($table, 'config_value')
                    ->where('config_key = ?', 'ml_service_consecutive_failures')
            );

            return $result ? (int) $result : 0;
        } catch (\Exception $e) {
            $this->logger->warning(sprintf(
                'Could not fetch failure count: %s',
                $e->getMessage()
            ));
            return 0;
        }
    }

    /**
     * Check if alert threshold has been exceeded.
     *
     * @return void
     */
    private function checkForAlertThreshold(): void
    {
        $failureCount = $this->getConsecutiveFailureCount();

        if ($failureCount >= self::MAX_CONSECUTIVE_FAILURES) {
            $this->logger->critical(sprintf(
                'ML service has failed %d consecutive health checks! Fallback mode activated.',
                $failureCount
            ));
            // In a production system, you might trigger an email alert or webhook here
        }
    }
}
