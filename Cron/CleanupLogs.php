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

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Psr\Log\LoggerInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

class CleanupLogs
{
    private const LOG_RETENTION_DAYS = 30;
    private const LOG_DIR_NAME = 'dubors';

    /**
     * @var Filesystem
     */
    private Filesystem $filesystem;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * CleanupLogs constructor.
     *
     * @param Filesystem $filesystem
     * @param LoggerInterface $logger
     */
    public function __construct(
        Filesystem $filesystem,
        LoggerInterface $logger
    ) {
        $this->filesystem = $filesystem;
        $this->logger = $logger;
    }

    /**
     * Execute log cleanup.
     *
     * This cron job removes old log files to:
     * - Manage disk space usage
     * - Improve filesystem performance
     * - Comply with data retention policies
     *
     * @return void
     */
    public function execute(): void
    {
        try {
            $this->logger->info('Starting log cleanup cron job');

            $logDir = $this->getLogDirectory();

            if (!is_dir($logDir)) {
                $this->logger->info(sprintf('Log directory does not exist: %s', $logDir));
                return;
            }

            $cutoffTime = $this->calculateCutoffTime();
            $deletedCount = $this->cleanupOldFiles($logDir, $cutoffTime);
            $freedSpace = $this->calculateFreedSpace($logDir);

            $this->logger->info(sprintf(
                'Log cleanup completed: %d files deleted, approximately %s freed',
                $deletedCount,
                $this->formatBytes($freedSpace)
            ));
        } catch (\Exception $e) {
            $this->logger->error(sprintf(
                'Fatal error in log cleanup cron: %s',
                $e->getMessage()
            ));
            throw $e;
        }
    }

    /**
     * Get the Dubors log directory path.
     *
     * @return string
     */
    private function getLogDirectory(): string
    {
        $varDir = $this->filesystem->getDirectoryRead(DirectoryList::VAR_DIR)->getAbsolutePath();
        return $varDir . 'log' . DIRECTORY_SEPARATOR . self::LOG_DIR_NAME;
    }

    /**
     * Calculate the cutoff timestamp for old logs.
     *
     * @return int Unix timestamp
     */
    private function calculateCutoffTime(): int
    {
        $cutoff = new \DateTime();
        $cutoff->modify(sprintf('-%d days', self::LOG_RETENTION_DAYS));
        return (int) $cutoff->format('U');
    }

    /**
     * Recursively delete old log files.
     *
     * @param string $directory
     * @param int $cutoffTime
     * @return int Number of deleted files
     */
    private function cleanupOldFiles(string $directory, int $cutoffTime): int
    {
        $deletedCount = 0;

        try {
            if (!is_readable($directory)) {
                $this->logger->warning(sprintf('Log directory is not readable: %s', $directory));
                return 0;
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );

            /** @var SplFileInfo $file */
            foreach ($iterator as $file) {
                // Only process log files
                if ($file->isFile() && $file->getExtension() === 'log') {
                    if ($file->getMTime() < $cutoffTime) {
                        try {
                            if (unlink($file->getRealPath())) {
                                $deletedCount++;
                                $this->logger->debug(sprintf(
                                    'Deleted old log file: %s (modified: %s)',
                                    $file->getFilename(),
                                    date('Y-m-d H:i:s', $file->getMTime())
                                ));
                            }
                        } catch (\Exception $e) {
                            $this->logger->warning(sprintf(
                                'Could not delete log file %s: %s',
                                $file->getFilename(),
                                $e->getMessage()
                            ));
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            $this->logger->error(sprintf(
                'Error during log cleanup traversal: %s',
                $e->getMessage()
            ));
        }

        return $deletedCount;
    }

    /**
     * Calculate approximate freed space (rough estimate of remaining files).
     *
     * @param string $directory
     * @return int Estimated bytes
     */
    private function calculateFreedSpace(string $directory): int
    {
        $space = 0;

        try {
            if (!is_readable($directory)) {
                return 0;
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $space += $file->getSize();
                }
            }
        } catch (\Exception $e) {
            $this->logger->warning(sprintf(
                'Could not calculate directory size: %s',
                $e->getMessage()
            ));
        }

        return $space;
    }

    /**
     * Format bytes to human-readable format.
     *
     * @param int $bytes
     * @return string
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));

        return round($bytes, 2) . ' ' . $units[$pow];
    }
}
