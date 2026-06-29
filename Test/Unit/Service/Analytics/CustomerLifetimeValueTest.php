<?php
/**
 * BrainStation23 Dubors
 * Phase 9: Testing Suite - Customer Lifetime Value Unit Tests
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
use BrainStation23\Dubors\Service\Analytics\CustomerLifetimeValue;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\App\ResourceConnection;
use Psr\Log\LoggerInterface;

class CustomerLifetimeValueTest extends TestCase
{
    /**
     * @var CustomerLifetimeValue
     */
    private $customerLifetimeValue;

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

        $this->customerLifetimeValue = new CustomerLifetimeValue(
            $this->resourceConnectionMock,
            $this->loggerMock
        );
    }

    /**
     * Test historical CLV calculation
     */
    public function testCalculateHistoricalClv(): void
    {
        $customerId = 123;
        $expectedRevenue = 5000;

        $this->connectionMock->expects($this->once())
            ->method('fetchOne')
            ->willReturn($expectedRevenue);

        $clv = $this->customerLifetimeValue->calculateHistoricalClv($customerId);

        $this->assertEquals($expectedRevenue, $clv);
    }

    /**
     * Test historical CLV with no orders
     */
    public function testCalculateHistoricalClvNoOrders(): void
    {
        $customerId = 123;

        $this->connectionMock->expects($this->once())
            ->method('fetchOne')
            ->willReturn(null);

        $clv = $this->customerLifetimeValue->calculateHistoricalClv($customerId);

        $this->assertEquals(0, $clv);
    }

    /**
     * Test predictive CLV calculation
     */
    public function testCalculatePredictiveClv(): void
    {
        $customerId = 123;
        $projectionDays = 365;

        // Mock historical CLV
        $this->connectionMock->expects($this->any())
            ->method('fetchOne')
            ->willReturnOnConsecutiveCalls(
                5000,    // Historical CLV
                100      // Account age in days
            );

        $predictedClv = $this->customerLifetimeValue->calculatePredictiveClv(
            $customerId,
            $projectionDays
        );

        // Predicted CLV should be a positive number
        $this->assertGreaterThan(0, $predictedClv);
    }

    /**
     * Test total CLV = historical + predicted
     */
    public function testCalculateTotalClv(): void
    {
        $customerId = 123;
        $projectionDays = 365;

        // Mock for historical and predicted calculations
        $this->connectionMock->expects($this->any())
            ->method('fetchOne')
            ->willReturnOnConsecutiveCalls(
                5000,    // Historical CLV
                100,     // Account age in days
                5000,    // Historical CLV again for total
                50       // Account age again for total
            );

        $totalClv = $this->customerLifetimeValue->calculateTotalClv(
            $customerId,
            $projectionDays
        );

        // Total CLV should be greater than historical alone
        $this->assertGreaterThanOrEqual(5000, $totalClv);
    }

    /**
     * Test CLV by customer segment
     */
    public function testGetClvBySegment(): void
    {
        $from = '2024-01-01';
        $to = '2024-01-31';
        $segmentClvData = [
            ['segment' => 'vip', 'avg_clv' => 10000, 'customer_count' => 50],
            ['segment' => 'standard', 'avg_clv' => 5000, 'customer_count' => 200],
            ['segment' => 'new', 'avg_clv' => 1000, 'customer_count' => 500]
        ];

        $this->connectionMock->expects($this->once())
            ->method('fetchAll')
            ->willReturn($segmentClvData);

        $results = $this->customerLifetimeValue->getClvBySegment($from, $to);

        $this->assertIsArray($results);
        $this->assertCount(3, $results);
        $this->assertEquals('vip', $results[0]['segment']);
    }

    /**
     * Test top customers by CLV
     */
    public function testGetTopCustomersByCLv(): void
    {
        $limit = 10;
        $topCustomersData = [
            ['customer_id' => 1, 'total_clv' => 50000],
            ['customer_id' => 2, 'total_clv' => 45000],
            ['customer_id' => 3, 'total_clv' => 40000]
        ];

        $this->connectionMock->expects($this->once())
            ->method('fetchAll')
            ->willReturn($topCustomersData);

        $results = $this->customerLifetimeValue->getTopCustomersByCLV($limit);

        $this->assertIsArray($results);
        $this->assertCount(3, $results);
        $this->assertEquals(50000, $results[0]['total_clv']);
    }

    /**
     * Test CLV percentile calculation
     */
    public function testGetClvPercentile(): void
    {
        $customerId = 123;
        $customerClv = 15000;
        $higherClvCount = 350;
        $totalCustomers = 1000;

        // Percentile = (customers with higher CLV / total customers) * 100
        // (350 / 1000) * 100 = 35th percentile

        $this->connectionMock->expects($this->any())
            ->method('fetchOne')
            ->willReturnOnConsecutiveCalls(
                15000,  // Customer's CLV
                350,    // Customers with higher CLV
                1000    // Total customers
            );

        $percentile = $this->customerLifetimeValue->getClvPercentile($customerId);

        // Should be between 0 and 100
        $this->assertGreaterThanOrEqual(0, $percentile);
        $this->assertLessThanOrEqual(100, $percentile);
    }

    /**
     * Test CLV trend tracking
     */
    public function testGetClvTrend(): void
    {
        $customerId = 123;
        $days = 90;
        $trendData = [
            ['date' => '2024-01-01', 'clv' => 1000],
            ['date' => '2024-02-01', 'clv' => 2000],
            ['date' => '2024-03-01', 'clv' => 3000]
        ];

        $this->connectionMock->expects($this->once())
            ->method('fetchAll')
            ->willReturn($trendData);

        $results = $this->customerLifetimeValue->getClvTrend($customerId, $days);

        $this->assertIsArray($results);
        $this->assertCount(3, $results);
        $this->assertEquals(1000, $results[0]['clv']);
    }

    /**
     * Test CAC to CLV ratio calculation
     */
    public function testCalculateCacToClvRatio(): void
    {
        $customerId = 123;
        $acquisitionCost = 50;

        $this->connectionMock->expects($this->any())
            ->method('fetchOne')
            ->willReturnOnConsecutiveCalls(
                100,     // Account age
                5000,    // Historical CLV
                100      // Account age again
            );

        $ratio = $this->customerLifetimeValue->calculateCacToClvRatio(
            $customerId,
            $acquisitionCost
        );

        // Ratio should be an array
        $this->assertIsArray($ratio);
        $this->assertArrayHasKey('ratio', $ratio);
        $this->assertArrayHasKey('status', $ratio);

        // With $50 CAC and projected high CLV, ratio should be healthy
        $this->assertGreaterThan(0, $ratio['ratio']);
    }

    /**
     * Test CAC to CLV with zero acquisition cost
     */
    public function testCalculateCacToClvRatioZeroCost(): void
    {
        $customerId = 123;
        $acquisitionCost = 0;

        $this->connectionMock->expects($this->any())
            ->method('fetchOne')
            ->willReturnOnConsecutiveCalls(
                100,     // Account age
                5000,    // Historical CLV
                100      // Account age again
            );

        $ratio = $this->customerLifetimeValue->calculateCacToClvRatio(
            $customerId,
            $acquisitionCost
        );

        // Zero cost should result in infinite ratio (or very high)
        $this->assertIsArray($ratio);
    }

    /**
     * Test retention rate impact on predictions
     */
    public function testRetentionRateImpact(): void
    {
        $customerId = 123;
        $projectionDays = 365;

        $this->connectionMock->expects($this->any())
            ->method('fetchOne')
            ->willReturnOnConsecutiveCalls(
                10000,   // Historical CLV
                100      // Account age
            );

        $predictedClv = $this->customerLifetimeValue->calculatePredictiveClv(
            $customerId,
            $projectionDays
        );

        // With 95% monthly retention, predictive CLV should be reasonable
        // Not as high as 10000 * 3.65 but significant
        $this->assertGreaterThan(0, $predictedClv);
        $this->assertLessThan(100000, $predictedClv);
    }

    /**
     * Test with new customer (minimal history)
     */
    public function testNewCustomerCLV(): void
    {
        $customerId = 999;

        $this->connectionMock->expects($this->once())
            ->method('fetchOne')
            ->willReturn(0);

        $clv = $this->customerLifetimeValue->calculateHistoricalClv($customerId);

        $this->assertEquals(0, $clv);
    }

    /**
     * Test with high-value customer
     */
    public function testHighValueCustomerCLV(): void
    {
        $customerId = 1;
        $expectedRevenue = 1000000;

        $this->connectionMock->expects($this->once())
            ->method('fetchOne')
            ->willReturn($expectedRevenue);

        $clv = $this->customerLifetimeValue->calculateHistoricalClv($customerId);

        $this->assertEquals($expectedRevenue, $clv);
    }

    /**
     * Test multiple segment CLV analysis
     */
    public function testMultipleSegmentAnalysis(): void
    {
        $from = '2024-01-01';
        $to = '2024-12-31';

        $this->connectionMock->expects($this->once())
            ->method('fetchAll')
            ->willReturn([
                ['segment' => 'gold', 'avg_clv' => 20000, 'customer_count' => 30],
                ['segment' => 'silver', 'avg_clv' => 10000, 'customer_count' => 100],
                ['segment' => 'bronze', 'avg_clv' => 5000, 'customer_count' => 300],
                ['segment' => 'new', 'avg_clv' => 500, 'customer_count' => 1000]
            ]);

        $results = $this->customerLifetimeValue->getClvBySegment($from, $to);

        $this->assertCount(4, $results);

        // Verify tier hierarchy
        $this->assertEquals(20000, $results[0]['avg_clv']);
        $this->assertEquals(500, $results[3]['avg_clv']);
    }

    /**
     * Test edge case: negative CLV (refunds exceeding purchases)
     */
    public function testNegativeClv(): void
    {
        $customerId = 123;

        $this->connectionMock->expects($this->once())
            ->method('fetchOne')
            ->willReturn(-500);

        $clv = $this->customerLifetimeValue->calculateHistoricalClv($customerId);

        // Should return 0 (never negative)
        $this->assertGreaterThanOrEqual(0, $clv);
    }
}
