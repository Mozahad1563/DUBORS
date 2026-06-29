<?php
/**
 * BrainStation23 Dubors
 * Phase 9: Testing Suite - Report Builder Unit Tests
 *
 * @category  BrainStation23
 * @package   BrainStation23_Dubors
 * @author    BrainStation23 <info@brainstation-23.com>
 * @copyright 2024 BrainStation23
 * @license   Proprietary
 */

namespace BrainStation23\Dubors\Test\Unit\Service\Analytics;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use BrainStation23\Dubors\Service\Analytics\ReportBuilder;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\App\ResourceConnection;
use Psr\Log\LoggerInterface;

class ReportBuilderTest extends TestCase
{
    /**
     * @var ReportBuilder
     */
    private $reportBuilder;

    /**
     * @var MockObject|ResourceConnection
     */
    private $resourceConnectionMock;

    /**
     * @var MockObject|AdapterInterface
     */
    private $connectionMock;

    /**
     * @var MockObject|LoggerInterface
     */
    private $loggerMock;

    protected function setUp(): void
    {
        $this->connectionMock = $this->createMock(AdapterInterface::class);
        $this->resourceConnectionMock = $this->createMock(ResourceConnection::class);
        $this->loggerMock = $this->createMock(LoggerInterface::class);

        $this->resourceConnectionMock->expects($this->any())
            ->method('getConnection')
            ->willReturn($this->connectionMock);

        $this->resourceConnectionMock->expects($this->any())
            ->method('getTableName')
            ->willReturnArgument(0);

        $this->reportBuilder = new ReportBuilder(
            $this->resourceConnectionMock,
            $this->loggerMock
        );
    }

    /**
     * Test creating a simple report
     */
    public function testCreateReport(): void
    {
        $name = 'Monthly Performance Report';
        $metrics = ['revenue', 'clicks'];
        $dimensions = ['date', 'segment'];
        $from = '2024-01-01';
        $to = '2024-01-31';

        $this->connectionMock->expects($this->once())
            ->method('insert')
            ->with('dubors_custom_report')
            ->willReturn(1);

        $result = $this->reportBuilder->createReport($name, $metrics, $dimensions, $from, $to);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('report_id', $result);
        $this->assertEquals($name, $result['name']);
    }

    /**
     * Test creating report with invalid metric
     */
    public function testCreateReportInvalidMetric(): void
    {
        $name = 'Invalid Report';
        $metrics = ['invalid_metric'];
        $dimensions = ['date'];
        $from = '2024-01-01';
        $to = '2024-01-31';

        // Should throw exception for invalid metric
        $this->expectException(\Exception::class);

        $this->reportBuilder->createReport($name, $metrics, $dimensions, $from, $to);
    }

    /**
     * Test report generation
     */
    public function testGenerateReport(): void
    {
        $reportId = 1;

        // Mock report configuration retrieval
        $this->connectionMock->expects($this->any())
            ->method('fetchRow')
            ->willReturn([
                'id' => 1,
                'name' => 'Test Report',
                'metrics' => json_encode(['revenue', 'clicks']),
                'dimensions' => json_encode(['date']),
                'period_from' => '2024-01-01',
                'period_to' => '2024-01-31'
            ]);

        // Mock report data query
        $this->connectionMock->expects($this->any())
            ->method('fetchAll')
            ->willReturn([
                ['date' => '2024-01-01', 'revenue' => 1000, 'clicks' => 50],
                ['date' => '2024-01-02', 'revenue' => 1200, 'clicks' => 60]
            ]);

        $result = $this->reportBuilder->generateReport($reportId);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('row_count', $result);
    }

    /**
     * Test retrieving available metrics
     */
    public function testGetAvailableMetrics(): void
    {
        $metrics = $this->reportBuilder->getAvailableMetrics();

        $this->assertIsArray($metrics);
        $this->assertContains('revenue', $metrics);
        $this->assertContains('clicks', $metrics);
        $this->assertContains('impressions', $metrics);
    }

    /**
     * Test retrieving available dimensions
     */
    public function testGetAvailableDimensions(): void
    {
        $dimensions = $this->reportBuilder->getAvailableDimensions();

        $this->assertIsArray($dimensions);
        $this->assertContains('date', $dimensions);
        $this->assertContains('segment', $dimensions);
        $this->assertContains('channel', $dimensions);
    }

    /**
     * Test CSV export
     */
    public function testExportToCsv(): void
    {
        $reportId = 1;

        $this->connectionMock->expects($this->any())
            ->method('fetchRow')
            ->willReturn([
                'id' => 1,
                'metrics' => json_encode(['revenue']),
                'dimensions' => json_encode(['date']),
                'period_from' => '2024-01-01',
                'period_to' => '2024-01-31'
            ]);

        $this->connectionMock->expects($this->any())
            ->method('fetchAll')
            ->willReturn([
                ['date' => '2024-01-01', 'revenue' => 1000],
                ['date' => '2024-01-02', 'revenue' => 1200]
            ]);

        $csv = $this->reportBuilder->exportToCSV($reportId);

        $this->assertIsString($csv);
        $this->assertStringContainsString('date', $csv);
        $this->assertStringContainsString('revenue', $csv);
    }

    /**
     * Test report run history
     */
    public function testSaveReportRun(): void
    {
        $reportId = 1;

        $this->connectionMock->expects($this->once())
            ->method('insert')
            ->with('dubors_report_run');

        $this->reportBuilder->saveReportRun($reportId);

        // Should complete without error
        $this->assertTrue(true);
    }

    /**
     * Test multi-metric report
     */
    public function testMultiMetricReport(): void
    {
        $name = 'Multi-Metric Report';
        $metrics = ['revenue', 'clicks', 'conversions', 'aov'];
        $dimensions = ['segment'];
        $from = '2024-01-01';
        $to = '2024-01-31';

        $this->connectionMock->expects($this->once())
            ->method('insert');

        $result = $this->reportBuilder->createReport($name, $metrics, $dimensions, $from, $to);

        $this->assertIsArray($result);
    }

    /**
     * Test multi-dimension report
     */
    public function testMultiDimensionReport(): void
    {
        $name = 'Multi-Dimension Report';
        $metrics = ['revenue'];
        $dimensions = ['date', 'segment', 'channel'];
        $from = '2024-01-01';
        $to = '2024-01-31';

        $this->connectionMock->expects($this->once())
            ->method('insert');

        $result = $this->reportBuilder->createReport($name, $metrics, $dimensions, $from, $to);

        $this->assertIsArray($result);
    }

    /**
     * Test summary statistics calculation
     */
    public function testCalculateSummary(): void
    {
        $data = [
            ['revenue' => 1000, 'clicks' => 50],
            ['revenue' => 1200, 'clicks' => 60],
            ['revenue' => 1100, 'clicks' => 55]
        ];

        $metrics = ['revenue', 'clicks'];

        $summary = $this->reportBuilder->calculateSummary($data, $metrics);

        $this->assertIsArray($summary);
        $this->assertArrayHasKey('total_revenue', $summary);
        $this->assertArrayHasKey('avg_revenue', $summary);
    }

    /**
     * Test empty report handling
     */
    public function testEmptyReportHandling(): void
    {
        $reportId = 999;

        $this->connectionMock->expects($this->any())
            ->method('fetchRow')
            ->willReturn([
                'id' => 999,
                'metrics' => json_encode(['revenue']),
                'dimensions' => json_encode(['date']),
                'period_from' => '2024-01-01',
                'period_to' => '2024-01-31'
            ]);

        $this->connectionMock->expects($this->any())
            ->method('fetchAll')
            ->willReturn([]);

        $result = $this->reportBuilder->generateReport($reportId);

        $this->assertIsArray($result);
        $this->assertEquals(0, $result['row_count']);
    }

    /**
     * Test large dataset handling
     */
    public function testLargeDatasetHandling(): void
    {
        $reportId = 1;
        $largeDataset = [];

        // Generate 1000 rows of data
        for ($i = 1; $i <= 1000; $i++) {
            $largeDataset[] = [
                'date' => '2024-01-' . str_pad($i % 31, 2, '0', STR_PAD_LEFT),
                'revenue' => 1000 + rand(-100, 100),
                'clicks' => 50 + rand(-5, 5)
            ];
        }

        $this->connectionMock->expects($this->any())
            ->method('fetchRow')
            ->willReturn([
                'id' => 1,
                'metrics' => json_encode(['revenue', 'clicks']),
                'dimensions' => json_encode(['date']),
                'period_from' => '2024-01-01',
                'period_to' => '2024-12-31'
            ]);

        $this->connectionMock->expects($this->any())
            ->method('fetchAll')
            ->willReturn($largeDataset);

        $result = $this->reportBuilder->generateReport($reportId);

        $this->assertIsArray($result);
        $this->assertEquals(1000, $result['row_count']);
    }

    /**
     * Test dimension grouping
     */
    public function testDimensionGrouping(): void
    {
        $name = 'Segment Performance';
        $metrics = ['revenue', 'conversions'];
        $dimensions = ['segment'];
        $from = '2024-01-01';
        $to = '2024-01-31';

        $this->connectionMock->expects($this->once())
            ->method('insert');

        $result = $this->reportBuilder->createReport($name, $metrics, $dimensions, $from, $to);

        $this->assertIsArray($result);
    }

    /**
     * Test date dimension aggregation
     */
    public function testDateDimensionAggregation(): void
    {
        $name = 'Daily Performance';
        $metrics = ['revenue'];
        $dimensions = ['date'];
        $from = '2024-01-01';
        $to = '2024-01-31';

        $this->connectionMock->expects($this->once())
            ->method('insert');

        $result = $this->reportBuilder->createReport($name, $metrics, $dimensions, $from, $to);

        $this->assertIsArray($result);
    }

    /**
     * Test report name validation
     */
    public function testReportNameValidation(): void
    {
        $name = '';  // Empty name
        $metrics = ['revenue'];
        $dimensions = ['date'];
        $from = '2024-01-01';
        $to = '2024-01-31';

        // Should handle empty name gracefully
        $this->connectionMock->expects($this->once())
            ->method('insert');

        $result = $this->reportBuilder->createReport($name, $metrics, $dimensions, $from, $to);

        $this->assertIsArray($result);
    }

    /**
     * Test metric precision in reports
     */
    public function testMetricPrecision(): void
    {
        $reportId = 1;

        $this->connectionMock->expects($this->any())
            ->method('fetchRow')
            ->willReturn([
                'id' => 1,
                'metrics' => json_encode(['revenue', 'aov']),
                'dimensions' => json_encode(['date']),
                'period_from' => '2024-01-01',
                'period_to' => '2024-01-31'
            ]);

        $this->connectionMock->expects($this->any())
            ->method('fetchAll')
            ->willReturn([
                ['date' => '2024-01-01', 'revenue' => 1000.50, 'aov' => 99.99]
            ]);

        $result = $this->reportBuilder->generateReport($reportId);

        $this->assertIsArray($result);
    }
}
