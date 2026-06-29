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
use Magento\Framework\App\ResourceConnection;
use Psr\Log\LoggerInterface;

class PurgeOldBehavior
{
    private const DEFAULT_RETENTION_DAYS = 90;

    /**
     * @var ResourceConnection
     */
    private ResourceConnection $resourceConnection;

    /**
     * @var Config
     */
    private Config $config;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * PurgeOldBehavior constructor.
     *
     * @param ResourceConnection $resourceConnection
     * @param Config $config
     * @param LoggerInterface $logger
     */
    public function __construct(
        ResourceConnection $resourceConnection,
        Config $config,
        LoggerInterface $logger
    ) {
        $this->resourceConnection = $resourceConnection;
        $this->config = $config;
        $this->logger = $logger;
    }

    /**
     * Execute cleanup of old user behavior records.
     *
     * This cron job removes user behavior records older than the configured
     * retention period. This is essential for:
     * - Database performance (prevents table bloat)
     * - Privacy compliance (GDPR/similar regulations)
     * - Storage optimization
     *
     * @return void
     */
    public function execute(): void
    {
        try {
            $this->logger->info('Starting old behavior records purge cron job');

            $retentionDays = $this->getRetentionDays();
            $purgeBeforeDate = $this->calculatePurgeDate($retentionDays);

            $this->logger->info(sprintf(
                'Purging behavior records older than %d days (before %s)',
                $retentionDays,
                $purgeBeforeDate->format('Y-m-d H:i:s')
            ));

            $purgedCount = $this->purgeBehaviorRecords($purgeBeforeDate);
            $purgedRecommendationCount = $this->purgeExpiredRecommendations();

            $this->logger->info(sprintf(
                'Purge completed: %d behavior records and %d expired recommendations deleted',
                $purgedCount,
                $purgedRecommendationCount
            ));
        } catch (\Exception $e) {
            $this->logger->error(sprintf(
                'Fatal error in purge old behavior cron: %s',
                $e->getMessage()
            ));
            throw $e;
        }
    }

    /**
     * Purge old behavior records from the database.
     *
     * @param \DateTime $purgeBeforeDate
     * @return int Number of deleted records
     */
    private function purgeBehaviorRecords(\DateTime $purgeBeforeDate): int
    {
        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName('vendor_dubors_user_behavior');

        try {
            $affectedRows = $connection->delete(
                $table,
                ['created_at < ?' => $purgeBeforeDate->format('Y-m-d H:i:s')]
            );

            $this->logger->info(sprintf('Deleted %d old behavior records', $affectedRows));
            return $affectedRows;
        } catch (\Exception $e) {
            $this->logger->error(sprintf('Error purging behavior records: %s', $e->getMessage()));
            return 0;
        }
    }

    /**
     * Purge expired recommendations.
     *
     * @return int Number of deleted records
     */
    private function purgeExpiredRecommendations(): int
    {
        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName('vendor_dubors_recommendation');

        try {
            $now = (new \DateTime())->format('Y-m-d H:i:s');
            $affectedRows = $connection->delete(
                $table,
                ['expires_at < ?' => $now]
            );

            $this->logger->info(sprintf('Deleted %d expired recommendations', $affectedRows));
            return $affectedRows;
        } catch (\Exception $e) {
            $this->logger->error(sprintf('Error purging recommendations: %s', $e->getMessage()));
            return 0;
        }
    }

    /**
     * Get configured retention days or use default.
     *
     * @return int
     */
    private function getRetentionDays(): int
    {
        try {
            $configured = $this->config->getDataRetentionDays();
            return $configured > 0 ? $configured : self::DEFAULT_RETENTION_DAYS;
        } catch (\Exception $e) {
            $this->logger->warning(sprintf(
                'Failed to load retention days config, using default (%d): %s',
                self::DEFAULT_RETENTION_DAYS,
                $e->getMessage()
            ));
            return self::DEFAULT_RETENTION_DAYS;
        }
    }

    /**
     * Calculate the date before which records should be purged.
     *
     * @param int $retentionDays
     * @return \DateTime
     */
    private function calculatePurgeDate(int $retentionDays): \DateTime
    {
        $now = new \DateTime();
        $now->modify(sprintf('-%d days', $retentionDays));
        return $now;
    }
}
