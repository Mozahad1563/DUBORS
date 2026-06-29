# Phase 8 Quick Reference Guide
## Analytics & Reporting - Developer Cheat Sheet

---

## 🚀 Quick Start

### Using KPI Calculator
```php
// Inject the service
protected $kpiCalculator;

// Calculate KPIs for date range
$kpis = $this->kpiCalculator->calculateOverallKpis(
    $from = '2024-01-01',
    $to = '2024-01-31'
);

// Access KPI values
echo "CTR: " . $kpis['ctr'] . "%";
echo "Conversion: " . $kpis['conversion_rate'] . "%";
echo "AOV: $" . $kpis['average_order_value'];
echo "Engagement: " . $kpis['engagement_score'] . "/100";
```

### Using Revenue Attribution
```php
// Track order attribution
$this->revenueAttribution->trackAttribution(
    $orderId = 123,
    $customerId = 456,
    $revenue = 99.99,
    $touchpoints = [
        ['recommendation_id' => 1, 'created_at' => '2024-01-15'],
        ['recommendation_id' => 2, 'created_at' => '2024-01-20'],
    ]
);

// Get top recommendations by revenue
$top = $this->revenueAttribution->getTopRecommendations(
    $limit = 10
);
```

### Using Customer Lifetime Value
```php
// Calculate total CLV for customer
$clv = $this->customerLifetimeValue->calculateTotalClv(
    $customerId = 456,
    $projectionDays = 365  // 1 year forecast
);

echo "Historical CLV: $" . $clv['historical'];
echo "Predicted CLV: $" . $clv['predicted'];
echo "Total CLV: $" . $clv['total'];
```

### Using ROI Analysis
```php
// Get segment ROI
$roi = $this->roiAnalysis->getRoiBySegment(
    $segment = 'vip',
    $from = '2024-01-01',
    $to = '2024-01-31'
);

echo "Segment ROI: " . $roi['roi_percentage'] . "%";
echo "Revenue: $" . $roi['revenue'];
echo "Cost: $" . $roi['cost'];
```

### Using Report Builder
```php
// Create custom report
$report = $this->reportBuilder->createReport(
    $name = 'Monthly Performance',
    $metrics = ['impressions', 'clicks', 'revenue'],
    $dimensions = ['segment', 'date'],
    $from = '2024-01-01',
    $to = '2024-01-31'
);

// Generate report data
$data = $this->reportBuilder->generateReport($report['report_id']);

// Export to CSV
$csv = $this->reportBuilder->exportToCSV($report['report_id']);
```

---

## 📊 Core Formulas Reference

### KPI Formulas
```
CTR = (Clicks / Impressions) × 100

Conversion Rate = (Conversions / Clicks) × 100

AOV = Total Revenue / Total Conversions

Revenue Per Recommendation = Total Revenue / Total Recommendations

Engagement Score = (CTR × 0.4) + (Conversion Rate × 0.6)
```

### Revenue Attribution
```
Linear Attribution = Order Revenue / Number of Touchpoints

Time to Conversion = Date(Conversion) - Date(First Impression)

Channel Split = Revenue × (Touchpoints for Channel / Total Touchpoints)
```

### Customer Lifetime Value
```
Historical CLV = Total Revenue from Customer

Predictive CLV = (Historical Revenue / Days as Customer) 
                × Projection Days 
                × Retention Adjustment

Total CLV = Historical CLV + Predictive CLV

CAC to CLV Ratio = Total CLV / Customer Acquisition Cost
```

### ROI Analysis
```
ROI % = ((Revenue - Cost) / Cost) × 100

Lift % = ((Variant A - Variant B) / Variant B) × 100

Revenue per Impression = Total Revenue / Total Impressions

Cost per Click = Total Cost / Total Clicks
```

---

## 🔧 Configuration & Customization

### Engagement Score Weights
```php
// In KpiCalculator::calculateEngagementScore()
$engagement = ($ctr * 0.4) + ($conversionRate * 0.6);

// Customize weights:
// CTR Weight: 0.4 (adjust to 0.3 for conversion-focused)
// Conversion Weight: 0.6 (adjust to 0.7 for conversion-focused)
```

### CLV Retention Rate
```php
// In CustomerLifetimeValue::calculatePredictiveClv()
$retentionRate = 0.95;  // 95% per month

// Customize based on your business:
// High retention: 0.97 (SaaS-like)
// Normal: 0.95 (e-commerce standard)
// Low retention: 0.90 (one-time purchase)
```

### Report Metrics
```php
// Available metrics for custom reports:
'impressions'      // Total recommendations shown
'clicks'           // Total clicks on recommendations
'conversions'      // Resulting purchases
'revenue'          // Total revenue attributed
'aov'              // Average order value
'ctr'              // Click-through rate %
'conversion_rate'  // Conversion rate %
```

### Report Dimensions
```php
// Available dimensions for grouping:
'date'             // Daily breakdown
'segment'          // Customer segment
'event_type'       // Type of recommendation event
'channel'          // Marketing channel
'recommendation_id' // Specific recommendation
```

---

## 📋 Database Table Reference

### KPI Tables
```sql
-- KPI Snapshots (historical tracking)
dubors_kpi_snapshot
├─ id (INT)
├─ period_from (DATETIME)
├─ period_to (DATETIME)
├─ ctr (DECIMAL 5,2)
├─ conversion_rate (DECIMAL 5,2)
├─ aov (DECIMAL 10,2)
├─ engagement_score (DECIMAL 5,2)
└─ created_at (TIMESTAMP)
```

### Revenue Attribution Tables
```sql
-- Revenue attribution tracking
dubors_revenue_attribution
├─ id (INT)
├─ order_id (INT)
├─ recommendation_id (INT)
├─ revenue_share (DECIMAL 10,2)
├─ model (VARCHAR 'linear', 'first', 'last')
├─ created_at (TIMESTAMP)
└─ updated_at (TIMESTAMP)
```

### CLV Tables
```sql
-- Customer lifetime value snapshots
dubors_clv_snapshot
├─ id (INT)
├─ customer_id (INT)
├─ historical_clv (DECIMAL 10,2)
├─ predicted_clv (DECIMAL 10,2)
├─ retention_rate (DECIMAL 3,2)
├─ projection_days (INT)
├─ created_at (TIMESTAMP)
└─ updated_at (TIMESTAMP)
```

### Report Tables
```sql
-- Custom report configurations
dubors_custom_report
├─ id (INT)
├─ name (VARCHAR 255)
├─ metrics (JSON)
├─ dimensions (JSON)
├─ period_from (DATETIME)
├─ period_to (DATETIME)
├─ created_at (TIMESTAMP)
└─ user_id (INT)

-- Report execution history
dubors_report_run
├─ id (INT)
├─ report_id (INT)
├─ row_count (INT)
├─ execution_time (INT ms)
├─ created_at (TIMESTAMP)
└─ created_by (INT)
```

---

## 🎯 Common Tasks

### Task: Get Monthly KPIs
```php
$from = date('Y-m-01', strtotime('first day of this month'));
$to = date('Y-m-t', strtotime('last day of this month'));

$kpis = $this->kpiCalculator->calculateOverallKpis($from, $to);

return $kpis; // Contains: ctr, conversion_rate, aov, etc.
```

### Task: Compare Two A/B Variants
```php
$from = date('Y-m-01', strtotime('-1 month'));
$to = date('Y-m-d');

$roiA = $this->roiAnalysis->getABTestROI(
    $variantIdA = 1,
    $variantIdB = 2,
    $from,
    $to
);

echo "Variant A ROI: " . $roiA['variant_a_roi'] . "%";
echo "Variant B ROI: " . $roiA['variant_b_roi'] . "%";
echo "Winner: " . $roiA['winner'];
echo "Lift: " . $roiA['lift'] . "%";
```

### Task: Identify Top Customers by Value
```php
$topCustomers = $this->customerLifetimeValue->getTopCustomersByCLV(
    $limit = 100,
    $segment = 'all'  // Or specific segment
);

foreach ($topCustomers as $customer) {
    echo $customer['customer_id'] . ": $" . $customer['total_clv'];
}
```

### Task: Generate Segment Performance Report
```php
$report = $this->reportBuilder->createReport(
    $name = 'Segment Performance ' . date('Y-m-d'),
    $metrics = ['revenue', 'conversions', 'aov'],
    $dimensions = ['segment', 'date'],
    $from = '2024-01-01',
    $to = '2024-01-31'
);

$data = $this->reportBuilder->generateReport($report['report_id']);
$csv = $this->reportBuilder->exportToCSV($report['report_id']);

// Save to file system
file_put_contents('/tmp/segment_report.csv', $csv);
```

### Task: Calculate CAC to CLV Ratio
```php
$ratio = $this->customerLifetimeValue->calculateCacToClvRatio(
    $customerId = 456,
    $acquisitionCost = 50.00  // How much it cost to acquire
);

// ratio['ratio'] = Total CLV / Acquisition Cost
// Healthy: >= 3.0x
// Excellent: >= 5.0x

if ($ratio['ratio'] >= 3.0) {
    echo "Healthy customer acquisition";
}
```

---

## 🚨 Error Handling

### Common Errors & Solutions

```php
// Error: Invalid date range
if (strtotime($from) >= strtotime($to)) {
    throw new \Exception('From date must be before to date');
}

// Error: No data for period
$kpis = $this->kpiCalculator->calculateOverallKpis($from, $to);
if (empty($kpis['impressions'])) {
    $this->logger->info('No impression data for period');
    return [];
}

// Error: Division by zero in calculations
if ($impressions == 0) {
    return 0;  // No impressions = 0% CTR
}

// Error: Failed to save snapshot
try {
    $this->kpiCalculator->saveKpiSnapshot($kpis);
} catch (\Exception $e) {
    $this->logger->error('Failed to save KPI snapshot', [
        'error' => $e->getMessage(),
        'kpis' => $kpis
    ]);
}
```

---

## 📈 Performance Tips

### Optimize KPI Calculations
```php
// ✅ GOOD: Cache for repeated calls
$cacheKey = 'kpi_' . date('Y-m-d');
$kpis = $this->cache->load($cacheKey);
if (!$kpis) {
    $kpis = $this->kpiCalculator->calculateOverallKpis($from, $to);
    $this->cache->save($kpis, $cacheKey, [], 86400);
}

// ❌ AVOID: Recalculating for every request
$kpis = $this->kpiCalculator->calculateOverallKpis($from, $to);
```

### Optimize Revenue Attribution
```php
// ✅ GOOD: Batch process orders
$orders = $this->orderCollection->getItems(); // Get all
foreach ($orders as $order) {
    // Process batch
    $this->revenueAttribution->trackAttribution(...);
}

// ❌ AVOID: Loop with database queries
$lastOrder = ...; // One by one
```

### Optimize Report Generation
```php
// ✅ GOOD: Use aggregate functions
// Database groups and sums automatically
$data = $this->reportBuilder->generateReport($reportId);

// ❌ AVOID: Fetching all rows then grouping
$rows = $this->collection->getItems();
foreach ($rows as $row) {
    // Manual grouping
}
```

---

## 🔐 Security Checklist

- ✅ All database queries use parameterized statements
- ✅ Admin controllers check ACL permissions
- ✅ Input validation on all endpoints
- ✅ No sensitive data in logs
- ✅ Error messages don't expose system details
- ✅ Timestamps immutable after creation
- ✅ User context tracked for audit

---

## 📞 Key Classes & Interfaces

### Service Classes
```php
// KPI Calculator
Service\Analytics\KpiCalculator
├─ calculateOverallKpis()
├─ calculateClickThroughRate()
├─ calculateEngagementScore()
├─ getDailyTrends()
└─ getKpisBySegment()

// Revenue Attribution
Service\Analytics\RevenueAttribution
├─ trackAttribution()
├─ getTopRecommendations()
├─ getTimeToConversion()
└─ getChannelAttribution()

// Customer Lifetime Value
Service\Analytics\CustomerLifetimeValue
├─ calculateTotalClv()
├─ getTopCustomersByCLV()
└─ calculateCacToClvRatio()

// ROI Analysis
Service\Analytics\RoiAnalysis
├─ getABTestROI()
├─ getRoiBySegment()
└─ calculateLift()

// Report Builder
Service\Analytics\ReportBuilder
├─ createReport()
├─ generateReport()
└─ exportToCSV()
```

### Controllers
```php
// Analytics Dashboard
Controller\Adminhtml\Analytics\Dashboard
└─ executeAction()

// Report Generator
Controller\Adminhtml\Reports\Generate
└─ executeAction()
```

---

## 🎓 Learning Resources

**Key Concepts**:
- Multi-touch attribution: How to credit multiple touchpoints
- CLV modeling: Predictive customer value calculation
- ROI analysis: Return on investment measurement
- KPI tracking: Key performance indicator monitoring

**Further Reading**:
- See PHASE_8_EXECUTION_SUMMARY.md for implementation details
- See PHASE_8_COMPLETION.md for architecture overview
- See database schema in Phase 1 documentation

---

## ✅ Testing Checklist

Before deploying Phase 8:
- [ ] KPI calculations return non-negative values
- [ ] Attribution splits sum to 100% of revenue
- [ ] CLV predictions >= historical CLV
- [ ] ROI calculations handle zero cost (∞ ROI)
- [ ] Reports export to valid CSV format
- [ ] All admin endpoints require authentication
- [ ] Error messages don't expose sensitive data
- [ ] Performance meets < 1 second benchmark

---

**Version**: 1.0  
**Module**: BrainStation23_Dubors Phase 8  
**Quality**: Production Ready ✅
