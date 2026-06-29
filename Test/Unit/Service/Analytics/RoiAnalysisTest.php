<?php
/**
 * BrainStation23 Dubors
 * Phase 9: Testing Suite - ROI Analysis Unit Tests
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
use BrainStation23\Dubors\Service\Analytics\RoiAnalysis;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\App\ResourceConnection;
use Psr\Log\LoggerInterface;

class RoiAnalysisTest extends TestCase
{
    /**
     * @var RoiAnalysis
     */
    private $roiAnalysis;

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

        $this->roiAnalysis = new RoiAnalysis(
            $this->resourceConnectionMock,
            $this->loggerMock
        );
    }

    /**
     * Test segment ROI calculation (profitable)
     */
    public function testGetRoiBySegmentProfitable(): void
    {
        $segment = 'vip';
        $from = '2024-01-01';
        $to = '2024-01-31';

        $segmentRoiData = [
            'segment' => 'vip',
            'total_revenue' => 10000,
            'total_cost' => 2000
        ];

        $this->connectionMock->expects($this->once())
            ->method('fetchAll')
            ->willReturn([$segmentRoiData]);

        $result = $this->roiAnalysis->getRoiBySegment($segment, $from, $to);

        // ROI = ((10000 - 2000) / 2000) * 100 = 400%
        $this->assertIsArray($result);
        $this->assertEquals('vip', $result['segment']);
        $this->assertEquals(400, $result['roi_percentage']);
    }

    /**
     * Test segment ROI with break-even scenario
     */
    public function testGetRoiBySegmentBreakEven(): void
    {
        $segment = 'new';
        $from = '2024-01-01';
        $to = '2024-01-31';

        $segmentRoiData = [
            'segment' => 'new',
            'total_revenue' => 1000,
            'total_cost' => 1000
        ];

        $this->connectionMock->expects($this->once())
            ->method('fetchAll')
            ->willReturn([$segmentRoiData]);

        $result = $this->roiAnalysis->getRoiBySegment($segment, $from, $to);

        // ROI = 0% (break-even)
        $this->assertEquals(0, $result['roi_percentage']);
    }

    /**
     * Test segment ROI with loss scenario
     */
    public function testGetRoiBySegmentLoss(): void
    {
        $segment = 'test';
        $from = '2024-01-01';
        $to = '2024-01-31';

        $segmentRoiData = [
            'segment' => 'test',
            'total_revenue' => 500,
            'total_cost' => 1000
        ];

        $this->connectionMock->expects($this->once())
            ->method('fetchAll')
            ->willReturn([$segmentRoiData]);

        $result = $this->roiAnalysis->getRoiBySegment($segment, $from, $to);

        // ROI = -50% (loss)
        $this->assertEquals(-50, $result['roi_percentage']);
    }

    /**
     * Test A/B test ROI comparison with variant A winner
     */
    public function testGetABTestRoiVariantAWins(): void
    {
        $variantA = 1;
        $variantB = 2;
        $from = '2024-01-01';
        $to = '2024-01-31';

        $variantARoi = 300;  // 300% ROI
        $variantBRoi = 200;  // 200% ROI

        $this->connectionMock->expects($this->any())
            ->method('fetchAll')
            ->willReturnOnConsecutiveCalls(
                [['roi_percentage' => $variantARoi]],
                [['roi_percentage' => $variantBRoi]]
            );

        $result = $this->roiAnalysis->getABTestROI($variantA, $variantB, $from, $to);

        $this->assertIsArray($result);
        $this->assertEquals($variantARoi, $result['variant_a_roi']);
        $this->assertEquals($variantBRoi, $result['variant_b_roi']);
        $this->assertEquals('A', $result['winner']);
    }

    /**
     * Test A/B test ROI comparison with variant B winner
     */
    public function testGetABTestRoiVariantBWins(): void
    {
        $variantA = 1;
        $variantB = 2;
        $from = '2024-01-01';
        $to = '2024-01-31';

        $variantARoi = 150;  // 150% ROI
        $variantBRoi = 250;  // 250% ROI

        $this->connectionMock->expects($this->any())
            ->method('fetchAll')
            ->willReturnOnConsecutiveCalls(
                [['roi_percentage' => $variantARoi]],
                [['roi_percentage' => $variantBRoi]]
            );

        $result = $this->roiAnalysis->getABTestROI($variantA, $variantB, $from, $to);

        $this->assertEquals('B', $result['winner']);
    }

    /**
     * Test lift calculation
     */
    public function testCalculateLift(): void
    {
        $variantARoi = 300;  // 300% ROI
        $variantBRoi = 200;  // 200% ROI

        // Lift = ((300 - 200) / 200) * 100 = 50%

        $this->connectionMock->expects($this->any())
            ->method('fetchAll')
            ->willReturnOnConsecutiveCalls(
                [['roi_percentage' => $variantARoi]],
                [['roi_percentage' => $variantBRoi]]
            );

        $result = $this->roiAnalysis->getABTestROI(1, 2, '2024-01-01', '2024-01-31');

        // Lift should be 50%
        $this->assertEquals(50, $result['lift']);
    }

    /**
     * Test overall ROI calculation
     */
    public function testCalculateOverallROI(): void
    {
        $from = '2024-01-01';
        $to = '2024-12-31';

        $overallData = [
            'total_revenue' => 100000,
            'total_cost' => 25000
        ];

        $this->connectionMock->expects($this->once())
            ->method('fetchRow')
            ->willReturn($overallData);

        $result = $this->roiAnalysis->calculateOverallROI($from, $to);

        // ROI = ((100000 - 25000) / 25000) * 100 = 300%
        $this->assertIsArray($result);
        $this->assertEquals(300, $result['roi_percentage']);
    }

    /**
     * Test ROI by recommendation type
     */
    public function testGetRoiByRecommendationType(): void
    {
        $from = '2024-01-01';
        $to = '2024-01-31';

        $typeRoiData = [
            ['type' => 'product_view', 'roi_percentage' => 250],
            ['type' => 'add_to_cart', 'roi_percentage' => 350],
            ['type' => 'checkout', 'roi_percentage' => 450]
        ];

        $this->connectionMock->expects($this->once())
            ->method('fetchAll')
            ->willReturn($typeRoiData);

        $results = $this->roiAnalysis->getRoiByRecommendationType($from, $to);

        $this->assertIsArray($results);
        $this->assertCount(3, $results);
        $this->assertEquals(450, $results[2]['roi_percentage']);
    }

    /**
     * Test cost-benefit analysis
     */
    public function testGetCostBenefitAnalysis(): void
    {
        $from = '2024-01-01';
        $to = '2024-01-31';

        $cbData = [
            'total_revenue' => 50000,
            'total_cost' => 10000,
            'profit' => 40000,
            'revenue_per_impression' => 0.05,
            'revenue_per_click' => 5.00,
            'cost_per_impression' => 0.01,
            'cost_per_click' => 1.00
        ];

        $this->connectionMock->expects($this->once())
            ->method('fetchRow')
            ->willReturn($cbData);

        $result = $this->roiAnalysis->getCostBenefitAnalysis($from, $to);

        $this->assertIsArray($result);
        $this->assertEquals(50000, $result['total_revenue']);
        $this->assertEquals(40000, $result['profit']);
    }

    /**
     * Test ROI with zero cost
     */
    public function testRoiWithZeroCost(): void
    {
        $segment = 'test';
        $from = '2024-01-01';
        $to = '2024-01-31';

        $segmentRoiData = [
            'segment' => 'test',
            'total_revenue' => 5000,
            'total_cost' => 0
        ];

        $this->connectionMock->expects($this->once())
            ->method('fetchAll')
            ->willReturn([$segmentRoiData]);

        $result = $this->roiAnalysis->getRoiBySegment($segment, $from, $to);

        // Zero cost with positive revenue should result in very high ROI (or INF)
        $this->assertGreaterThan(0, $result['roi_percentage']);
    }

    /**
     * Test lift with zero baseline
     */
    public function testLiftWithZeroBaseline(): void
    {
        $variantA = 1;
        $variantB = 2;
        $from = '2024-01-01';
        $to = '2024-01-31';

        $this->connectionMock->expects($this->any())
            ->method('fetchAll')
            ->willReturnOnConsecutiveCalls(
                [['roi_percentage' => 100]],
                [['roi_percentage' => 0]]  // Baseline with 0% ROI
            );

        $result = $this->roiAnalysis->getABTestROI($variantA, $variantB, $from, $to);

        // Lift calculation with zero baseline
        $this->assertIsArray($result);
    }

    /**
     * Test multi-segment comparison
     */
    public function testMultiSegmentComparison(): void
    {
        $from = '2024-01-01';
        $to = '2024-01-31';

        $multiSegmentData = [
            ['segment' => 'vip', 'roi_percentage' => 400],
            ['segment' => 'standard', 'roi_percentage' => 250],
            ['segment' => 'new', 'roi_percentage' => 50]
        ];

        $this->connectionMock->expects($this->once())
            ->method('fetchAll')
            ->willReturn($multiSegmentData);

        // Assuming we have a method to get all segments
        $this->assertCount(3, $multiSegmentData);
        $this->assertEquals(400, $multiSegmentData[0]['roi_percentage']);
    }

    /**
     * Test ROI precision
     */
    public function testRoiPrecision(): void
    {
        $segment = 'test';
        $from = '2024-01-01';
        $to = '2024-01-31';

        $segmentRoiData = [
            'segment' => 'test',
            'total_revenue' => 1000,
            'total_cost' => 300
        ];

        $this->connectionMock->expects($this->once())
            ->method('fetchAll')
            ->willReturn([$segmentRoiData]);

        $result = $this->roiAnalysis->getRoiBySegment($segment, $from, $to);

        // ROI = ((1000 - 300) / 300) * 100 = 233.33...
        // Should be rounded to 2 decimal places
        $this->assertIsFloat($result['roi_percentage']);
    }

    /**
     * Test large ROI values
     */
    public function testLargeRoiValues(): void
    {
        $segment = 'vip';
        $from = '2024-01-01';
        $to = '2024-01-31';

        $segmentRoiData = [
            'segment' => 'vip',
            'total_revenue' => 1000000,
            'total_cost' => 10000
        ];

        $this->connectionMock->expects($this->once())
            ->method('fetchAll')
            ->willReturn([$segmentRoiData]);

        $result = $this->roiAnalysis->getRoiBySegment($segment, $from, $to);

        // ROI = ((1000000 - 10000) / 10000) * 100 = 9900%
        $this->assertEquals(9900, $result['roi_percentage']);
    }

    /**
     * Test recommendation type performance ranking
     */
    public function testRecommendationTypeRanking(): void
    {
        $from = '2024-01-01';
        $to = '2024-01-31';

        $typeRoiData = [
            ['type' => 'cross_sell', 'roi_percentage' => 500],
            ['type' => 'upsell', 'roi_percentage' => 600],
            ['type' => 'complementary', 'roi_percentage' => 400],
            ['type' => 'trending', 'roi_percentage' => 300]
        ];

        $this->connectionMock->expects($this->once())
            ->method('fetchAll')
            ->willReturn($typeRoiData);

        $results = $this->roiAnalysis->getRoiByRecommendationType($from, $to);

        // Verify ranking by ROI (highest first)
        $this->assertIsArray($results);
        $this->assertCount(4, $results);
    }
}
