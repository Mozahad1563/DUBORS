# Phase 7 Advanced Features - Quick Reference Guide

## 🎯 Service Quick Access

### Customer Segmentation Service
**File**: `app/code/BrainStation23/Dubors/Service/Segmentation/CustomerSegmentation.php`

```php
// Dependency Injection
use BrainStation23\Dubors\Service\Segmentation\CustomerSegmentation;

// Segment all customers
$segmentation->segmentCustomers();

// Segment specific customer
$segmentation->classifyCustomer($customerId);

// Get segment members
$customers = $segmentation->getSegmentCustomers('vip');
// Returns: Array of customer IDs in VIP segment

// Calculate RFM metrics
$rfm = $segmentation->calculateRfm($customerId);
// Returns: ['recency' => 30, 'frequency' => 20, 'monetary' => 1500.00]
```

**Segments** (5 levels):
- `vip` - Best customers (recency ≤ 30d, frequency ≥ 10, monetary ≥ $500)
- `active` - Regular customers (recency ≤ 60d, frequency ≥ 5)
- `at_risk` - Declining engagement (recency 60-120d, frequency ≥ 3)
- `dormant` - Inactive (recency > 120d)
- `new` - New/low activity customers

---

### A/B Testing Engine
**File**: `app/code/BrainStation23/Dubors/Service/Testing/AbTestingEngine.php`

```php
// Create test
$testId = $engine->createTest([
    'name' => 'Algorithm Comparison',
    'description' => 'ML vs Popularity',
    'start_date' => date('Y-m-d H:i:s')
]);

// Add variants (2-4 typically)
$variantA = $engine->addVariant($testId, [
    'key' => 'ml_model',
    'name' => 'ML Recommendation',
    'traffic_percentage' => 50
]);

$variantB = $engine->addVariant($testId, [
    'key' => 'popularity',
    'name' => 'Popularity-Based',
    'traffic_percentage' => 50
]);

// Assign customer consistently
$assignedVariant = $engine->assignCustomerVariant($customerId, $testId);
// Returns: 'ml_model' or 'popularity' (same for customer across calls)

// Track impressions (when shown)
$engine->trackImpression($testId, $variantA, $customerId);

// Track conversions (click/purchase)
$engine->trackConversion($testId, $variantA, $customerId);

// Get results
$results = $engine->getTestResults($testId);
// Returns: [
//   'variant_a' => ['impressions' => 5000, 'conversions' => 775, 'rate' => 15.5%],
//   'variant_b' => ['impressions' => 5000, 'conversions' => 615, 'rate' => 12.3%]
// ]

// Determine winner (statistical significance)
$winner = $engine->determineWinner($testId);
// Returns: [
//   'winner' => 'variant_a',
//   'confidence_level' => 0.95,
//   'is_significant' => true
// ]

// End test when complete
$engine->endTest($testId);
```

**Common Patterns**:
- Traffic split: 50/50 for equal comparison, 80/20 for low-risk testing
- Duration: 1-4 weeks typical
- Impressions needed: ~1,000+ per variant for significance
- Rerun tests: After deployment or significant changes

---

### Rule-Based Filter
**File**: `app/code/BrainStation23/Dubors/Service/Filtering/RuleBasedFilter.php`

```php
// Create filtering rule
$ruleId = $filter->createRule([
    'name' => 'Exclude Out of Stock',
    'action' => 'exclude',  // 'exclude', 'include', 'prioritize'
    'product_ids' => [45, 78, 123],
    'conditions' => [
        [
            'type' => 'segment',
            'operator' => 'equals',
            'value' => 'all'  // or 'vip', 'active', etc.
        ]
    ],
    'priority' => 10,  // Higher = executed first
    'status' => 1
]);

// Create rule with price range
$ruleId = $filter->createRule([
    'name' => 'Budget Products',
    'action' => 'include',
    'conditions' => [
        [
            'type' => 'price_range',
            'operator' => 'between',
            'value' => [0, 50]
        ]
    ],
    'priority' => 5
]);

// Get active rules
$rules = $filter->getActiveRules();

// Apply filtering to recommendations
$filtered = $filter->filterRecommendations($customerId, $recommendations);
// Input: [['product_id' => 123, 'score' => 85.5], ...]
// Returns: Filtered list based on rules

// Update rule
$filter->updateRule($ruleId, ['priority' => 20]);

// Delete rule
$filter->deleteRule($ruleId);

// Get rules for segment
$segmentRules = $filter->getActiveRules('vip');
```

**Rule Actions**:
- `exclude` - Remove products from recommendations
- `include` - Only show these products
- `prioritize` - Boost these products higher

**Condition Types**:
- `segment` - Apply based on customer segment
- `price_range` - Filter by product price
- `category` - Filter by category
- `purchase_history` - Based on past purchases

---

### Custom Scoring Engine
**File**: `app/code/BrainStation23/Dubors/Service/Scoring/CustomScoringEngine.php`

```php
// Score recommendations
$scored = $engine->scoreRecommendations(
    $customerId,
    $recommendations,  // Array of products with base scores
    $customerProfile   // Customer data
);
// Returns: Array with new scores and component breakdown

// Update weights (customize algorithm)
$engine->updateScoringWeights([
    'ml_model' => 0.40,          // ML algorithm weight
    'engagement' => 0.30,        // Category match weight
    'popularity' => 0.20,        // Product popularity weight
    'category' => 0.07,          // Customer category preference
    'price' => 0.03              // Price range matching
]);

// Get current weights
$weights = $engine->getWeights();
// Default: ML(0.35), Engagement(0.25), Popularity(0.20), Category(0.15), Price(0.05)

// Create custom scoring rule
$ruleId = $engine->createScoringRule([
    'name' => 'VIP Scoring',
    'conditions' => [
        ['type' => 'segment', 'value' => 'vip']
    ],
    'weights' => [
        'ml_model' => 0.30,
        'engagement' => 0.20,
        'popularity' => 0.30,  // Boost popularity for VIPs
        'category' => 0.15,
        'price' => 0.05
    ]
]);

// Example result structure
$scoredRec = [
    'product_id' => 123,
    'base_score' => 75.5,
    'final_score' => 78.3,
    'components' => [
        'ml_model' => ['score' => 82, 'weight' => 0.35, 'contribution' => 28.7],
        'engagement' => ['score' => 65, 'weight' => 0.25, 'contribution' => 16.25],
        'popularity' => ['score' => 80, 'weight' => 0.20, 'contribution' => 16.0],
        'category' => ['score' => 70, 'weight' => 0.15, 'contribution' => 10.5],
        'price' => ['score' => 60, 'weight' => 0.05, 'contribution' => 3.0]
    ]
];
```

**Scoring Dimensions**:
1. **ML Model** (35%) - Base recommendation from ML
2. **Engagement** (25%) - Match with customer preferences
3. **Popularity** (20%) - Product view/purchase count
4. **Category** (15%) - Alignment with past purchases
5. **Price** (5%) - Budget compatibility

**Customization Options**:
- Adjust weights per segment
- Create rules for VIP vs regular customers
- Dynamic weighting based on seasonality
- A/B test scoring algorithms

---

## 🔧 Admin Operations

### Trigger Manual Segmentation
**Endpoint**: `/admin/dubors/segmentation/run`
**Method**: POST
**Response**: JSON with statistics

```php
// Via admin UI (if panel available)
// Or via direct controller call
$segmentationController->executeSegmentation();

// Response:
{
    "success": true,
    "message": "Segmentation completed",
    "statistics": {
        "total_customers": 1250,
        "vip": 125,
        "active": 450,
        "at_risk": 300,
        "dormant": 200,
        "new": 175,
        "processing_time_seconds": 12.5
    }
}
```

---

## 📊 Typical Workflows

### Workflow 1: Segment-Based Recommendations
```php
// 1. Identify customer segment
$segment = $segmentation->classifyCustomer($customerId);

// 2. Get base recommendations
$recommendations = $mlService->getRecommendations($customerId);

// 3. Apply segment-specific rules
$filtered = $filter->filterRecommendations($customerId, $recommendations);

// 4. Apply segment-specific scoring
$scored = $engine->scoreRecommendations($customerId, $filtered, $profile);

// 5. Return top recommendations
return array_slice($scored, 0, 10);
```

### Workflow 2: A/B Test Comparison
```php
// 1. Create test
$testId = $engine->createTest(['name' => 'New Algorithm']);

// 2. Add current and new variants
$engine->addVariant($testId, ['key' => 'current', 'traffic_percentage' => 50]);
$engine->addVariant($testId, ['key' => 'new', 'traffic_percentage' => 50]);

// 3. Assign and track during recommender call
$variant = $engine->assignCustomerVariant($customerId, $testId);
$recommendations = recommendationEngineWithVariant($customerId, $variant);
$engine->trackImpression($testId, $variant, $customerId);

// 4. Track conversions from events
// (in event observer)
$engine->trackConversion($testId, $variant, $customerId);

// 5. After sufficient data, determine winner
$winner = $engine->determineWinner($testId);
if ($winner['is_significant']) {
    // Deploy winning variant
    deployRecommendationUpdate($winner['winner']);
}
```

### Workflow 3: Implement Business Rules
```php
// Create rules to handle business logic
$filter->createRule([
    'name' => 'VIP Premium Products',
    'action' => 'include',
    'conditions' => [['type' => 'segment', 'value' => 'vip']],
    'product_ids' => [1001, 1002, 1003],  // Premium SKUs
    'priority' => 100
]);

$filter->createRule([
    'name' => 'Exclude Clearance',
    'action' => 'exclude',
    'conditions' => [['type' => 'segment', 'value' => 'all']],
    'categories' => ['clearance'],
    'priority' => 50
]);

$filter->createRule([
    'name' => 'Budget Conscious',
    'action' => 'prioritize',
    'conditions' => [['type' => 'price_range', 'value' => [0, 50]]],
    'priority' => 30
]);
```

---

## 🐛 Troubleshooting

| Issue | Cause | Solution |
|-------|-------|----------|
| Segmentation not running | Cron disabled | Check Phase 6 cron config |
| All customers marked "new" | No historical data | Wait for events to accumulate |
| A/B test shows no winner | Insufficient data | Run longer, more impressions needed |
| Filters not applying | Rule priority wrong | Check rule priority order |
| Scores too high/low | Weight misconfiguration | Verify weight sum ~1.0 |
| Segment assignments changing | Hash inconsistency | Check customer ID consistency |

---

## 📈 Monitoring & Optimization

### Key Metrics to Track
```
Segmentation:
├─ VIP % of customer base
├─ Churn rate by segment
├─ Segment migration rate (month-over-month)
└─ RFM score distributions

A/B Testing:
├─ Conversion rate lift (% improvement)
├─ Statistical significance level
├─ Sample size per variant
└─ Test duration vs decision time

Filtering:
├─ Recommendations filtered per run
├─ Rule application frequency
├─ Excluded products impact on revenue
└─ Rule conflict detection

Scoring:
├─ Score distribution (mean, median, std dev)
├─ Component contribution analysis
├─ Customer satisfaction correlation
└─ Revenue correlation by score quartile
```

### Performance Tuning
```php
// Batch segmentation for large catalogs
$segmentation->segmentCustomers(null, ['batch_size' => 500]);

// Reduce scoring precision for real-time
$simplified_weights = ['ml_model' => 1.0];  // Use only ML score

// Cache filter results
$cache->save($filter->getActiveRules(), 'dubors_rules_cache', 3600);

// Limit A/B test variants
// 2 variants: < 2 weeks
// 3 variants: 2-4 weeks
// 4+ variants: 4+ weeks or use multi-armed bandit
```

---

## 🔒 Security Checklist

- ✅ Only admins can trigger segmentation: `/Adminhtml/Segmentation/Run.php` ACL check
- ✅ Filter rules validated before save
- ✅ Scoring weights must sum to ~1.0
- ✅ Customer segment assignment deterministic (same result every time)
- ✅ Conversion tracking only on valid events
- ✅ Test variants immutable once started

---

## 📚 Related Documentation

- **Phase 1**: Data Models & Tables
- **Phase 5**: ML Service Integration
- **Phase 6**: Cron Job Configuration
- **Phase 3**: Admin UI Framework
- **Phase 2**: Event System

---

## 🚀 Next Steps

1. **Configure Thresholds**: Adjust segmentation boundaries in constants
2. **Plan A/B Tests**: Define test hypothesis and expected lift
3. **Create Business Rules**: Implement company-specific filtering
4. **Set Scoring Weights**: Tune for your product/customer mix
5. **Monitor Metrics**: Track key performance indicators
6. **Optimize Continuously**: Iterate based on results

---

**Phase 7 Status**: ✅ COMPLETE - Ready for production deployment
