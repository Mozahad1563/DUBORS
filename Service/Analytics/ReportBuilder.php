<?php

namespace BrainStation23\Dubors\Service\Analytics;

use Magento\Framework\App\ResourceConnection;
use Psr\Log\LoggerInterface;

/**
 * Report Builder Service
 * 
 * Generates custom analytics reports:
 * - Custom report configuration
 * - Report data generation
 * - Report export functionality
 */
class ReportBuilder
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
     * ReportBuilder constructor
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
     * Create a new custom report
     *
     * @param array $config Report configuration
     * @return int Report ID
     */
    public function createReport(array $config): int
    {
        try {
            $connection = $this->resourceConnection->getConnection();
            $table = $this->resourceConnection->getTableName('dubors_custom_report');

            $connection->insert($table, [
                'name' => $config['name'],
                'description' => $config['description'] ?? '',
                'metrics' => json_encode($config['metrics'] ?? []),
                'dimensions' => json_encode($config['dimensions'] ?? []),
                'filters' => json_encode($config['filters'] ?? []),
                'period_from' => $config['period_from'] ?? date('Y-m-d', strtotime('-30 days')),
                'period_to' => $config['period_to'] ?? date('Y-m-d'),
                'status' => 1,
                'created_at' => date('Y-m-d H:i:s')
            ]);

            // Get inserted ID using query-based approach
            $select = $connection->select()
                ->from($table, ['id'])
                ->order('id DESC')
                ->limit(1);
            return (int)$connection->fetchOne($select);
        } catch (\Exception $e) {
            $this->logger->error('Error creating report: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Get report configuration
     *
     * @param int $reportId
     * @return array
     */
    public function getReport(int $reportId): array
    {
        try {
            $connection = $this->resourceConnection->getConnection();
            $table = $this->resourceConnection->getTableName('dubors_custom_report');

            $select = $connection->select()
                ->from($table)
                ->where('id = ?', $reportId);

            return $connection->fetchRow($select) ?: [];
        } catch (\Exception $e) {
            $this->logger->error('Error getting report: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Generate report data
     *
     * @param int $reportId
     * @return array Report data with metrics and dimensions
     */
    public function generateReport(int $reportId): array
    {
        try {
            $report = $this->getReport($reportId);

            if (empty($report)) {
                return [];
            }

            $metrics = json_decode($report['metrics'], true) ?? [];
            $dimensions = json_decode($report['dimensions'], true) ?? [];
            $filters = json_decode($report['filters'], true) ?? [];

            $data = $this->queryReportData($metrics, $dimensions, $filters, $report);

            return [
                'report_id' => $reportId,
                'name' => $report['name'],
                'generated_at' => date('Y-m-d H:i:s'),
                'metrics' => $metrics,
                'dimensions' => $dimensions,
                'data' => $data,
                'summary' => $this->calculateSummary($data, $metrics)
            ];
        } catch (\Exception $e) {
            $this->logger->error('Error generating report: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Query data for report
     *
     * @param array $metrics
     * @param array $dimensions
     * @param array $filters
     * @param array $report
     * @return array
     */
    private function queryReportData(array $metrics, array $dimensions, array $filters, array $report): array
    {
        try {
            $connection = $this->resourceConnection->getConnection();
            $table = $this->resourceConnection->getTableName('dubors_recommendation_event');

            $select = $connection->select()
                ->from($table);

            // Add metric aggregations
            $selectFields = [];
            foreach ($metrics as $metric) {
                $selectFields[$metric] = $this->getMetricExpression($metric);
            }

            if ($selectFields) {
                $select = $connection->select()
                    ->from($table, $selectFields);
            }

            // Add dimensions for grouping
            foreach ($dimensions as $dimension) {
                $select->group($this->getDimensionExpression($dimension));
            }

            // Apply filters
            if (!empty($report['period_from'])) {
                $select->where('DATE(created_at) >= ?', $report['period_from']);
            }
            if (!empty($report['period_to'])) {
                $select->where('DATE(created_at) <= ?', $report['period_to']);
            }

            return $connection->fetchAll($select);
        } catch (\Exception $e) {
            $this->logger->error('Error querying report data: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get metric SQL expression
     *
     * @param string $metric
     * @return \Zend_Db_Expr
     */
    private function getMetricExpression(string $metric): \Zend_Db_Expr
    {
        $expressions = [
            'impressions' => new \Zend_Db_Expr('COUNT(CASE WHEN event_type = "impression" THEN 1 END)'),
            'clicks' => new \Zend_Db_Expr('COUNT(CASE WHEN event_type = "click" THEN 1 END)'),
            'conversions' => new \Zend_Db_Expr('COUNT(CASE WHEN event_type = "conversion" THEN 1 END)'),
            'revenue' => new \Zend_Db_Expr('SUM(CASE WHEN event_type = "conversion" THEN order_value ELSE 0 END)'),
            'avg_order_value' => new \Zend_Db_Expr('AVG(CASE WHEN event_type = "conversion" THEN order_value ELSE 0 END)')
        ];

        return $expressions[$metric] ?? new \Zend_Db_Expr('COUNT(*)');
    }

    /**
     * Get dimension SQL expression
     *
     * @param string $dimension
     * @return string
     */
    private function getDimensionExpression(string $dimension): string
    {
        $expressions = [
            'date' => 'DATE(created_at)',
            'segment' => 'customer_segment',
            'type' => 'event_type',
            'channel' => 'channel'
        ];

        return $expressions[$dimension] ?? $dimension;
    }

    /**
     * Calculate summary statistics
     *
     * @param array $data
     * @param array $metrics
     * @return array
     */
    private function calculateSummary(array $data, array $metrics): array
    {
        $summary = [];

        foreach ($metrics as $metric) {
            $values = array_column($data, $metric);
            $values = array_filter($values);

            if (!empty($values)) {
                $summary[$metric] = [
                    'total' => array_sum($values),
                    'average' => round(array_sum($values) / count($values), 2),
                    'min' => min($values),
                    'max' => max($values)
                ];
            }
        }

        return $summary;
    }

    /**
     * Export report to CSV
     *
     * @param int $reportId
     * @return string CSV content
     */
    public function exportToCSV(int $reportId): string
    {
        try {
            $report = $this->generateReport($reportId);

            if (empty($report['data'])) {
                return '';
            }

            $output = fopen('php://temp', 'r+');

            // Write headers
            $headers = array_keys($report['data'][0]);
            fputcsv($output, $headers);

            // Write data
            foreach ($report['data'] as $row) {
                fputcsv($output, array_values($row));
            }

            rewind($output);
            $csv = stream_get_contents($output);
            fclose($output);

            return $csv;
        } catch (\Exception $e) {
            $this->logger->error('Error exporting report to CSV: ' . $e->getMessage());
            return '';
        }
    }

    /**
     * Get available metrics
     *
     * @return array
     */
    public function getAvailableMetrics(): array
    {
        return [
            'impressions' => 'Number of times shown',
            'clicks' => 'Number of clicks',
            'conversions' => 'Number of conversions',
            'revenue' => 'Total revenue generated',
            'avg_order_value' => 'Average order value'
        ];
    }

    /**
     * Get available dimensions
     *
     * @return array
     */
    public function getAvailableDimensions(): array
    {
        return [
            'date' => 'By date',
            'segment' => 'By customer segment',
            'type' => 'By event type',
            'channel' => 'By channel'
        ];
    }

    /**
     * Save report run history
     *
     * @param int $reportId
     * @param int $rowCount
     * @return bool
     */
    public function saveReportRun(int $reportId, int $rowCount = 0): bool
    {
        try {
            $connection = $this->resourceConnection->getConnection();
            $table = $this->resourceConnection->getTableName('dubors_report_run');

            $connection->insert($table, [
                'report_id' => $reportId,
                'row_count' => $rowCount,
                'executed_at' => date('Y-m-d H:i:s')
            ]);

            return true;
        } catch (\Exception $e) {
            $this->logger->error('Error saving report run: ' . $e->getMessage());
            return false;
        }
    }
}
