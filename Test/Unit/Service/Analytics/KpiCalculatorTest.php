<?php
/**
 * BrainStation23 Dubors
 * Phase 9: Testing Suite - KPI Calculator Unit Tests
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
use BrainStation23\Dubors\Service\Analytics\KpiCalculator;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\App\ResourceConnection;
use Psr\Log\LoggerInterface;

class KpiCalculatorTest extends TestCase
{
    /**
     * @var KpiCalculator
     */
    private $kpiCalculator;

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

        $this->kpiCalculator = new KpiCalculator(
            $this->resourceConnectionMock,
            $this->loggerMock
        );
    }

    /**
     * Test CTR calculation with valid data
     */
    public function testCalculateClickThroughRate(): void
    {
        $from = '2024-01-01';
        $to = '2024-01-31';
        $expectedImpressions = 1000;
        $expectedClicks = 25;
        $expectedCtr = 2.5;

        // Mock impression query
        $this->connectionMock->expects($this->at(0))
            ->method('fetchOne')
            ->willReturn($expectedImpressions);

        // Mock clicks query
        $this->connectionMock->expects($this->at(1))
            ->method('fetchOne')
            ->willReturn($expectedClicks);

        $ctr = $this->kpiCalculator->calculateClickThroughRate($from, $to);

        $this->assertEquals($expectedCtr, $ctr);
    }

    /**
     * Test CTR calculation with zero impressions
     */
    public function testCalculateClickThroughRateZeroImpressions(): void
    {
        $from = '2024-01-01';
        $to = '2024-01-31';

        // Mock impression query returns 0
        $this->connectionMock->expects($this->at(0))
            ->method('fetchOne')
            ->willReturn(0);

        $ctr = $this->kpiCalculator->calculateClickThroughRate($from, $to);

        // Should return 0, not NaN or error
        $this->assertEquals(0, $ctr);
    }

    /**
     * Test conversion rate calculation
     */
    public function testCalculateConversionRate(): void
    {
        $from = '2024-01-01';
        $to = '2024-01-31';
        $expectedClicks = 100;
        $expectedConversions = 5;
        $expectedRate = 5.0;

        // Mock clicks query
        $this->connectionMock->expects($this->at(0))
            ->method('fetchOne')
            ->willReturn($expectedClicks);

        // Mock conversions query
        $this->connectionMock->expects($this->at(1))
            ->method('fetchOne')
            ->willReturn($expectedConversions);

        $rate = $this->kpiCalculator->calculateConversionRate($from, $to);

        $this->assertEquals($expectedRate, $rate);
    }

    /**
     * Test AOV calculation
     */
    public function testCalculateAverageOrderValue(): void
    {
        $from = '2024-01-01';
        $to = '2024-01-31';
        $totalRevenue = 5000;
        $conversions = 50;
        $expectedAov = 100;

        // Mock revenue query
        $this->connectionMock->expects($this->at(0))
            ->method('fetchOne')
            ->willReturn($totalRevenue);

        // Mock conversions query
        $this->connectionMock->expects($this->at(1))
            ->method('fetchOne')
            ->willReturn($conversions);

        $aov = $this->kpiCalculator->calculateAverageOrderValue($from, $to);

        $this->assertEquals($expectedAov, $aov);
    }

    /**
     * Test engagement score calculation
     */
    public function testCalculateEngagementScore(): void
    {
        $from = '2024-01-01';
        $to = '2024-01-31';

        // Mock CTR calculation: 2%
        // Mock Conversion Rate: 1%
        // Engagement = (2 * 0.4) + (1 * 0.6) = 0.8 + 0.6 = 1.4

        $this->connectionMock->expects($this->any())
            ->method('fetchOne')
            ->willReturnOnConsecutiveCalls(
                1000,   // impressions
                20,     // clicks (2% CTR)
                100,    // clicks for conversion rate
                1       // conversions (1% conversion)
            );

        $score = $this->kpiCalculator->calculateEngagementScore($from, $to);

        // Score should be between 0 and 100
        $this->assertGreaterThanOrEqual(0, $score);
        $this->assertLessThanOrEqual(100, $score);
    }

    /**
     * Test overall KPI calculation
     */
    public function testCalculateOverallKpis(): void
    {
        $from = '2024-01-01';
        $to = '2024-01-31';

        $this->connectionMock->expects($this->any())
            ->method('fetchOne')
            ->willReturnOnConsecutiveCalls(
                1000,   // impressions
                25,     // clicks
                100,    // clicks for conversion
                5,      // conversions
                500,    // revenue
                5       // conversions for AOV
            );

        $kpis = $this->kpiCalculator->calculateOverallKpis($from, $to);

        $this->assertIsArray($kpis);
        $this->assertArrayHasKey('ctr', $kpis);
        $this->assertArrayHasKey('conversion_rate', $kpis);
        $this->assertArrayHasKey('aov', $kpis);
        $this->assertArrayHasKey('engagement_score', $kpis);
    }

    /**
     * Test daily trends calculation
     */
    public function testGetDailyTrends(): void
    {
        $from = '2024-01-01';
        $to = '2024-01-02';

        $this->connectionMock->expects($this->any())
            ->method('fetchOne')
            ->willReturnOnConsecutiveCalls(
                1000, 25, 100, 5, 500, 5,  // Day 1
                1100, 27, 110, 6, 550, 6   // Day 2
            );

        $trends = $this->kpiCalculator->getDailyTrends($from, $to);

        $this->assertIsArray($trends);
        $this->assertGreaterThan(0, count($trends));
    }

    /**
     * Test KPI by segment
     */
    public function testGetKpisBySegment(): void
    {
        $from = '2024-01-01';
        $to = '2024-01-31';
        $segment = 'vip';

        $this->connectionMock->expects($this->any())
            ->method('fetchOne')
            ->willReturnOnConsecutiveCalls(
                500,   // impressions for VIP
                15,    // clicks
                50,    // clicks for conversion
                3      // conversions
            );

        $kpis = $this->kpiCalculator->calculateOverallKpis($from, $to, $segment);

        $this->assertIsArray($kpis);
        $this->assertArrayHasKey('ctr', $kpis);
    }

    /**
     * Test with invalid date range
     */
    public function testCalculateWithInvalidDateRange(): void
    {
        $from = '2024-01-31';
        $to = '2024-01-01';

        $this->expectException(\Exception::class);

        $this->kpiCalculator->calculateOverallKpis($from, $to);
    }

    /**
     * Test precision of calculations
     */
    public function testCalculationPrecision(): void
    {
        $from = '2024-01-01';
        $to = '2024-01-31';

        $this->connectionMock->expects($this->at(0))
            ->method('fetchOne')
            ->willReturn(1000);

        $this->connectionMock->expects($this->at(1))
            ->method('fetchOne')
            ->willReturn(33);

        $ctr = $this->kpiCalculator->calculateClickThroughRate($from, $to);

        // CTR should have exactly 2 decimal places: 3.30
        $this->assertEquals(3.30, $ctr);
    }

    /**
     * Test large numbers handling
     */
    public function testLargeNumbersHandling(): void
    {
        $from = '2024-01-01';
        $to = '2024-12-31';

        $this->connectionMock->expects($this->at(0))
            ->method('fetchOne')
            ->willReturn(1000000);

        $this->connectionMock->expects($this->at(1))
            ->method('fetchOne')
            ->willReturn(50000);

        $ctr = $this->kpiCalculator->calculateClickThroughRate($from, $to);

        // Should handle 1M+ impressions without issues
        $this->assertEquals(5.0, $ctr);
    }
}
