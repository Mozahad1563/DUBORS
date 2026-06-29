<?php
/**
 * BrainStation23 Dubors
 * Phase 9: Testing Suite - Test Data Factory for Analytics
 *
 * @category  BrainStation23
 * @package   BrainStation23_Dubors
 * @author    BrainStation23 <info@brainstation-23.com>
 * @copyright 2024 BrainStation23
 * @license   Proprietary
 */

namespace BrainStation23\Dubors\Test\Unit\Fixture;

/**
 * Factory for creating test data for analytics tests
 */
class AnalyticsTestDataFactory
{
    /**
     * Create sample KPI data
     *
     * @param array $overrides
     * @return array
     */
    public static function createKpiData(array $overrides = []): array
    {
        $defaults = [
            'period_from' => '2024-01-01',
            'period_to' => '2024-01-31',
            'ctr' => 2.5,
            'conversion_rate' => 5.0,
            'aov' => 125.50,
            'rpr' => 3.14,
            'engagement_score' => 7.5,
            'click_count' => 250,
            'impression_count' => 10000,
            'conversion_count' => 500,
            'revenue' => 62750
        ];

        return array_merge($defaults, $overrides);
    }

    /**
     * Create sample revenue attribution data
     *
     * @param array $overrides
     * @return array
     */
    public static function createAttributionData(array $overrides = []): array
    {
        $defaults = [
            'id' => 1,
            'customer_id' => 123,
            'order_id' => 456,
            'order_value' => 500.00,
            'attribution_model' => 'multi_touch',
            'touchpoint_count' => 3,
            'first_touch_id' => 1,
            'last_touch_id' => 3,
            'first_touch_date' => '2024-01-01 10:00:00',
            'conversion_date' => '2024-01-15 14:30:00',
            'attribution_data' => json_encode([
                '1' => 166.67,
                '2' => 166.67,
                '3' => 166.66
            ]),
            'created_at' => '2024-01-15 14:30:00'
        ];

        return array_merge($defaults, $overrides);
    }

    /**
     * Create sample CLV data
     *
     * @param array $overrides
     * @return array
     */
    public static function createClvData(array $overrides = []): array
    {
        $defaults = [
            'customer_id' => 123,
            'historical_clv' => 2500.00,
            'predictive_clv' => 3500.00,
            'total_clv' => 6000.00,
            'order_count' => 15,
            'average_order_value' => 166.67,
            'last_order_date' => '2024-01-30',
            'days_since_first_order' => 365,
            'retention_rate' => 0.85,
            'churn_risk' => 0.15,
            'segment' => 'vip',
            'lifetime_value_percentile' => 85,
            'created_at' => date('Y-m-d H:i:s')
        ];

        return array_merge($defaults, $overrides);
    }

    /**
     * Create sample ROI analysis data
     *
     * @param array $overrides
     * @return array
     */
    public static function createRoiData(array $overrides = []): array
    {
        $defaults = [
            'segment' => 'vip',
            'period_from' => '2024-01-01',
            'period_to' => '2024-01-31',
            'total_revenue' => 50000.00,
            'total_cost' => 10000.00,
            'profit' => 40000.00,
            'roi_percentage' => 400.0,
            'roi_ratio' => 4.0,
            'break_even_point' => 10000.00,
            'payback_period_days' => 7,
            'impression_count' => 100000,
            'click_count' => 2500,
            'conversion_count' => 500,
            'cpc' => 4.00,
            'cpa' => 20.00,
            'created_at' => date('Y-m-d H:i:s')
        ];

        return array_merge($defaults, $overrides);
    }

    /**
     * Create sample report configuration
     *
     * @param array $overrides
     * @return array
     */
    public static function createReportConfig(array $overrides = []): array
    {
        $defaults = [
            'name' => 'Monthly Performance Report',
            'description' => 'Comprehensive analytics report for January 2024',
            'metrics' => ['revenue', 'clicks', 'conversions', 'impressions', 'roi'],
            'dimensions' => ['date', 'segment', 'channel'],
            'filters' => [
                'segment' => ['vip', 'standard'],
                'channel' => ['email', 'web']
            ],
            'period_from' => '2024-01-01',
            'period_to' => '2024-01-31',
            'aggregation' => 'daily',
            'format' => 'table'
        ];

        return array_merge($defaults, $overrides);
    }

    /**
     * Create sample report data rows
     *
     * @param int $count
     * @param array $overrides
     * @return array
     */
    public static function createReportRows(int $count = 5, array $overrides = []): array
    {
        $rows = [];

        for ($i = 0; $i < $count; $i++) {
            $date = date('Y-m-d', strtotime("+{$i} days", strtotime('2024-01-01')));

            $row = [
                'date' => $date,
                'segment' => ['vip', 'standard', 'new'][array_rand(['vip', 'standard', 'new'])],
                'channel' => ['email', 'web', 'mobile'][array_rand(['email', 'web', 'mobile'])],
                'revenue' => mt_rand(1000, 5000),
                'clicks' => mt_rand(50, 200),
                'conversions' => mt_rand(5, 50),
                'impressions' => mt_rand(5000, 20000),
                'roi' => mt_rand(100, 500) / 100
            ];

            $rows[] = array_merge($row, $overrides);
        }

        return $rows;
    }

    /**
     * Create sample touchpoint data
     *
     * @param int $count
     * @param array $overrides
     * @return array
     */
    public static function createTouchpoints(int $count = 3, array $overrides = []): array
    {
        $touchpoints = [];
        $baseDate = strtotime('2024-01-01');

        for ($i = 0; $i < $count; $i++) {
            $touchpoint = [
                'recommendation_id' => $i + 1,
                'created_at' => date('Y-m-d H:i:s', $baseDate + ($i * 86400 * 5)),
                'event_type' => $i === ($count - 1) ? 'conversion' : 'impression',
                'revenue' => $i === ($count - 1) ? 100 : 0
            ];

            $touchpoints[] = array_merge($touchpoint, $overrides);
        }

        return $touchpoints;
    }

    /**
     * Create sample A/B test variants
     *
     * @param array $overrides
     * @return array
     */
    public static function createABTestVariants(array $overrides = []): array
    {
        $variants = [
            'variant_a' => [
                'id' => 1,
                'name' => 'Variant A',
                'impressions' => 10000,
                'clicks' => 250,
                'conversions' => 50,
                'revenue' => 5000,
                'ctr' => 2.5,
                'conversion_rate' => 0.5,
                'roi' => 350
            ],
            'variant_b' => [
                'id' => 2,
                'name' => 'Variant B',
                'impressions' => 10000,
                'clicks' => 200,
                'conversions' => 40,
                'revenue' => 4000,
                'ctr' => 2.0,
                'conversion_rate' => 0.4,
                'roi' => 300
            ]
        ];

        return $variants;
    }

    /**
     * Create sample segment data
     *
     * @param array $overrides
     * @return array
     */
    public static function createSegmentData(array $overrides = []): array
    {
        $defaults = [
            'segments' => [
                'vip' => [
                    'count' => 100,
                    'revenue' => 25000,
                    'average_clv' => 250,
                    'roi' => 450,
                    'retention_rate' => 0.95
                ],
                'standard' => [
                    'count' => 500,
                    'revenue' => 30000,
                    'average_clv' => 60,
                    'roi' => 300,
                    'retention_rate' => 0.70
                ],
                'new' => [
                    'count' => 200,
                    'revenue' => 5000,
                    'average_clv' => 25,
                    'roi' => 200,
                    'retention_rate' => 0.40
                ]
            ]
        ];

        return array_merge($defaults, $overrides);
    }

    /**
     * Create sample channel attribution
     *
     * @param array $overrides
     * @return array
     */
    public static function createChannelAttribution(array $overrides = []): array
    {
        $defaults = [
            'channels' => [
                'email' => [
                    'conversions' => 150,
                    'revenue' => 7500,
                    'average_revenue' => 50
                ],
                'web' => [
                    'conversions' => 120,
                    'revenue' => 6000,
                    'average_revenue' => 50
                ],
                'mobile' => [
                    'conversions' => 80,
                    'revenue' => 4000,
                    'average_revenue' => 50
                ],
                'social' => [
                    'conversions' => 50,
                    'revenue' => 2500,
                    'average_revenue' => 50
                ]
            ]
        ];

        return array_merge($defaults, $overrides);
    }

    /**
     * Create sample time-to-conversion data
     *
     * @param array $overrides
     * @return array
     */
    public static function createTimeToConversionData(array $overrides = []): array
    {
        $defaults = [
            'average_days' => 14,
            'minimum_days' => 1,
            'maximum_days' => 120,
            'median_days' => 7,
            'distribution' => [
                'same_day' => [
                    'count' => 100,
                    'revenue' => 5000,
                    'percentage' => 20
                ],
                '1_7_days' => [
                    'count' => 200,
                    'revenue' => 12000,
                    'percentage' => 40
                ],
                '8_30_days' => [
                    'count' => 150,
                    'revenue' => 9000,
                    'percentage' => 30
                ],
                '30plus_days' => [
                    'count' => 50,
                    'revenue' => 3000,
                    'percentage' => 10
                ]
            ]
        ];

        return array_merge($defaults, $overrides);
    }

    /**
     * Create sample revenue by model data
     *
     * @param array $overrides
     * @return array
     */
    public static function createRevenueByModelData(array $overrides = []): array
    {
        $defaults = [
            'linear' => [
                'total_revenue' => 15000,
                'count' => 300,
                'average_revenue' => 50
            ],
            'first' => [
                'total_revenue' => 14000,
                'count' => 300,
                'average_revenue' => 46.67
            ],
            'last' => [
                'total_revenue' => 16000,
                'count' => 300,
                'average_revenue' => 53.33
            ],
            'time_decay' => [
                'total_revenue' => 15500,
                'count' => 300,
                'average_revenue' => 51.67
            ]
        ];

        return array_merge($defaults, $overrides);
    }

    /**
     * Create edge case data for testing
     *
     * @return array
     */
    public static function createEdgeCaseData(): array
    {
        return [
            'zero_values' => [
                'revenue' => 0,
                'clicks' => 0,
                'conversions' => 0,
                'impressions' => 0
            ],
            'large_values' => [
                'revenue' => 999999999.99,
                'clicks' => 1000000,
                'conversions' => 50000,
                'impressions' => 100000000
            ],
            'negative_values' => [
                'revenue' => -1000,
                'refund_count' => 5,
                'churn_rate' => -0.15
            ],
            'decimal_precision' => [
                'revenue' => 1234.5678,
                'percentage' => 33.333333,
                'ratio' => 0.123456789
            ],
            'boundary_values' => [
                'retention_rate' => 1.0,
                'churn_rate' => 0.0,
                'roi' => 0.01
            ]
        ];
    }

    /**
     * Create mock customer data
     *
     * @param int $count
     * @return array
     */
    public static function createCustomers(int $count = 10): array
    {
        $customers = [];

        for ($i = 1; $i <= $count; $i++) {
            $customers[] = [
                'entity_id' => $i,
                'email' => "customer{$i}@example.com",
                'firstname' => "Customer",
                'lastname' => $i,
                'created_at' => date('Y-m-d H:i:s', strtotime("-{$i} months")),
                'group_id' => ($i % 3) + 1
            ];
        }

        return $customers;
    }

    /**
     * Create mock order data
     *
     * @param int $count
     * @return array
     */
    public static function createOrders(int $count = 10): array
    {
        $orders = [];
        $baseDate = strtotime('2024-01-01');

        for ($i = 1; $i <= $count; $i++) {
            $orders[] = [
                'entity_id' => $i,
                'customer_id' => ($i % 10) + 1,
                'grand_total' => mt_rand(100, 1000),
                'created_at' => date('Y-m-d H:i:s', $baseDate + (mt_rand(0, 2592000))),
                'status' => 'complete',
                'subtotal' => mt_rand(100, 1000)
            ];
        }

        return $orders;
    }

    /**
     * Create mock product data
     *
     * @param int $count
     * @return array
     */
    public static function createProducts(int $count = 10): array
    {
        $products = [];

        for ($i = 1; $i <= $count; $i++) {
            $products[] = [
                'entity_id' => $i,
                'sku' => "PROD{$i}",
                'name' => "Product {$i}",
                'type_id' => 'simple',
                'attribute_set_id' => 4,
                'status' => 1,
                'visibility' => 4
            ];
        }

        return $products;
    }

    /**
     * Create mock event data
     *
     * @param int $count
     * @return array
     */
    public static function createEvents(int $count = 20): array
    {
        $events = [];
        $eventTypes = ['impression', 'click', 'conversion', 'view', 'cart_add'];
        $baseDate = strtotime('2024-01-01');

        for ($i = 1; $i <= $count; $i++) {
            $events[] = [
                'event_id' => $i,
                'customer_id' => mt_rand(1, 10),
                'recommendation_id' => mt_rand(1, 50),
                'product_id' => mt_rand(1, 10),
                'event_type' => $eventTypes[array_rand($eventTypes)],
                'order_value' => mt_rand(0, 500),
                'created_at' => date('Y-m-d H:i:s', $baseDate + (mt_rand(0, 2592000)))
            ];
        }

        return $events;
    }
}
