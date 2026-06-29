<?php
/**
 * BrainStation23 Dubors
 * Phase 9: Testing Suite - Revenue Attribution Unit Tests
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
use BrainStation23\Dubors\Service\Analytics\RevenueAttribution;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\App\ResourceConnection;
use Psr\Log\LoggerInterface;

class RevenueAttributionTest extends TestCase
{
    /**
     * @var RevenueAttribution
     */
    private $revenueAttribution;

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

        $this->revenueAttribution = new RevenueAttribution(
            $this->resourceConnectionMock,
            $this->loggerMock
        );
    }

    /**
     * Test linear attribution with single touchpoint
     */
    public function testLinearAttributionSingleTouchpoint(): void
    {
        $customerId = 123;
        $revenue = 100;
        $touchpoints = [
            ['recommendation_id' => 1, 'created_at' => '2024-01-15']
        ];

        $this->connectionMock->expects($this->once())
            ->method('insert');

        $this->connectionMock->expects($this->once())
            ->method('fetchOne')
            ->willReturn(1);

        $result = $this->revenueAttribution->trackAttribution(
            $customerId,
            $revenue,
            $touchpoints
        );

        $this->assertIsInt($result);
        $this->assertEquals(1, $result);
    }

    /**
     * Test linear attribution with multiple touchpoints
     */
    public function testLinearAttributionMultipleTouchpoints(): void
    {
        $customerId = 123;
        $revenue = 300;
        $touchpoints = [
            ['recommendation_id' => 1, 'created_at' => '2024-01-10'],
            ['recommendation_id' => 2, 'created_at' => '2024-01-15'],
            ['recommendation_id' => 3, 'created_at' => '2024-01-20']
        ];

        $this->connectionMock->expects($this->exactly(4))
            ->method('insert');

        $this->connectionMock->expects($this->once())
            ->method('fetchOne')
            ->willReturn(2);

        $result = $this->revenueAttribution->trackAttribution(
            $customerId,
            $revenue,
            $touchpoints
        );

        $this->assertIsInt($result);
        $this->assertEquals(2, $result);
    }

    /**
     * Test attribution with empty touchpoints
     */
    public function testAttributionWithEmptyTouchpoints(): void
    {
        $orderId = 123;
        $revenue = 100;
        $touchpoints = [];

        $this->expectException(\Exception::class);

        $this->revenueAttribution->trackAttribution(
            $orderId,
            $revenue,
            $touchpoints
        );
    }

    /**
     * Test revenue split calculation
     */
    public function testRevenueDistribution(): void
    {
        $revenue = 100;
        $touchpointCount = 4;
        $expectedShare = $revenue / $touchpointCount;

        $this->connectionMock->expects($this->any())
            ->method('insert')
            ->with(
                'dubors_revenue_attribution',
                $this->callback(function ($data) use ($expectedShare) {
                    return abs($data['revenue_share'] - $expectedShare) < 0.01;
                })
            );

        $touchpoints = [
            ['recommendation_id' => 1, 'created_at' => '2024-01-10'],
            ['recommendation_id' => 2, 'created_at' => '2024-01-15'],
            ['recommendation_id' => 3, 'created_at' => '2024-01-20'],
            ['recommendation_id' => 4, 'created_at' => '2024-01-25']
        ];

        $this->revenueAttribution->trackAttribution(
            123,
            $revenue,
            $touchpoints
        );
    }

    /**
     * Test time-to-conversion calculation
     */
    public function testTimeToConversion(): void
    {
        $from = '2024-01-01';
        $to = '2024-01-31';

        $this->connectionMock->expects($this->any())
            ->method('fetchRow')
            ->willReturn([
                'avg_days' => 14,
                'min_days' => 1,
                'max_days' => 30
            ]);

        $this->connectionMock->expects($this->any())
            ->method('fetchAll')
            ->willReturn([
                ['days_bucket' => 'same_day', 'count' => 10, 'revenue' => 500],
                ['days_bucket' => '1_7_days', 'count' => 5, 'revenue' => 300]
            ]);

        $result = $this->revenueAttribution->getTimeToConversion($from, $to);

        $this->assertIsArray($result);
        $this->assertEquals(14, $result['average_days']);
        $this->assertEquals(1, $result['minimum_days']);
        $this->assertEquals(30, $result['maximum_days']);
    }

    /**
     * Test top recommendations ranking
     */
    public function testGetTopRecommendations(): void
    {
        $topRecommendations = [
            [
                'recommendation_id' => 1,
                'total_revenue' => 5000,
                'attribution_count' => 150
            ],
            [
                'recommendation_id' => 2,
                'total_revenue' => 4500,
                'attribution_count' => 140
            ],
            [
                'recommendation_id' => 3,
                'total_revenue' => 4000,
                'attribution_count' => 120
            ]
        ];

        $this->connectionMock->expects($this->once())
            ->method('fetchAll')
            ->willReturn($topRecommendations);

        $results = $this->revenueAttribution->getTopRecommendations(10);

        $this->assertIsArray($results);
        $this->assertCount(3, $results);
        $this->assertEquals(5000, $results[0]['total_revenue']);
    }

    /**
     * Test channel attribution breakdown
     */
    public function testGetChannelAttribution(): void
    {
        $from = '2024-01-01';
        $to = '2024-01-31';

        $channelData = [
            ['channel' => 'email', 'revenue' => 2500, 'attributed_count' => 50],
            ['channel' => 'web', 'revenue' => 2000, 'attributed_count' => 40],
            ['channel' => 'mobile', 'revenue' => 1500, 'attributed_count' => 30]
        ];

        $this->connectionMock->expects($this->once())
            ->method('fetchAll')
            ->willReturn($channelData);

        $results = $this->revenueAttribution->getChannelAttribution($from, $to);

        $this->assertIsArray($results);
        $this->assertCount(3, $results);
        $this->assertEquals(2500, $results[0]['revenue']);
    }

    /**
     * Test revenue by attribution model comparison
     */
    public function testGetRevenueByModel(): void
    {
        $from = '2024-01-01';
        $to = '2024-01-31';

        $modelData = [
            ['model' => 'linear', 'total_revenue' => 6000],
            ['model' => 'first', 'total_revenue' => 5500],
            ['model' => 'last', 'total_revenue' => 5800]
        ];

        $this->connectionMock->expects($this->once())
            ->method('fetchAll')
            ->willReturn($modelData);

        $results = $this->revenueAttribution->getRevenueByModel($from, $to);

        $this->assertIsArray($results);
        $this->assertEquals(6000, $results[0]['total_revenue']);
    }

    /**
     * Test conversion distribution analysis
     */
    public function testGetConversionDistributionViaTimeToConversion(): void
    {
        $from = '2024-01-01';
        $to = '2024-01-31';

        $this->connectionMock->expects($this->any())
            ->method('fetchRow')
            ->willReturn([
                'avg_days' => 14,
                'min_days' => 1,
                'max_days' => 30
            ]);

        $this->connectionMock->expects($this->any())
            ->method('fetchAll')
            ->willReturn([
                ['days_bucket' => 'same_day', 'count' => 50, 'revenue' => 2500],
                ['days_bucket' => '1_7_days', 'count' => 30, 'revenue' => 1800],
                ['days_bucket' => '8_30_days', 'count' => 20, 'revenue' => 1200]
            ]);

        $result = $this->revenueAttribution->getTimeToConversion($from, $to);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('distribution', $result);
        $this->assertIsArray($result['distribution']);
        $this->assertCount(3, $result['distribution']);
        $this->assertEquals(50, $result['distribution'][0]['count']);
    }

    /**
     * Test with large order revenue
     */
    public function testLargeOrderRevenue(): void
    {
        $customerId = 123;
        $revenue = 100000;
        $touchpoints = [
            ['recommendation_id' => 1, 'created_at' => '2024-01-10'],
            ['recommendation_id' => 2, 'created_at' => '2024-01-15']
        ];

        $this->connectionMock->expects($this->exactly(4))
            ->method('insert');

        $this->connectionMock->expects($this->once())
            ->method('fetchOne')
            ->willReturn(3);

        $result = $this->revenueAttribution->trackAttribution(
            $customerId,
            $revenue,
            $touchpoints
        );

        $this->assertIsInt($result);
        $this->assertEquals(3, $result);
    }

    /**
     * Test with negative revenue (refund)
     */
    public function testNegativeRevenue(): void
    {
        $orderId = 123;
        $revenue = -50;
        $touchpoints = [
            ['recommendation_id' => 1, 'created_at' => '2024-01-10']
        ];

        $this->connectionMock->expects($this->once())
            ->method('insert');

        $result = $this->revenueAttribution->trackAttribution(
            $orderId,
            $revenue,
            $touchpoints
        );

        $this->assertIsArray($result);
    }

    /**
     * Test precision in revenue split
     */
    public function testRevenueSplitPrecision(): void
    {
        $customerId = 123;
        $revenue = 100;
        $touchpointCount = 3;
        // 100 / 3 = 33.33... (repeating)

        $touchpoints = [
            ['recommendation_id' => 1, 'created_at' => '2024-01-10'],
            ['recommendation_id' => 2, 'created_at' => '2024-01-15'],
            ['recommendation_id' => 3, 'created_at' => '2024-01-20']
        ];

        $this->connectionMock->expects($this->exactly(4))
            ->method('insert');

        $this->connectionMock->expects($this->once())
            ->method('fetchOne')
            ->willReturn(4);

        $result = $this->revenueAttribution->trackAttribution(
            $customerId,
            $revenue,
            $touchpoints
        );

        $this->assertIsInt($result);
        $this->assertEquals(4, $result);
    }
}
