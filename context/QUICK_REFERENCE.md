# DUBORS Quick Reference Guide

**Module**: BrainStation23_Dubors v1.0.0  
**Status**: Phases 1-4 Complete  
**Last Updated**: 2025-01-16

---

## 🚀 Quick Start

### Enable Module
```bash
bin/magento module:enable BrainStation23_Dubors
bin/magento setup:upgrade
bin/magento setup:di:compile
```

### Start Message Queue Consumers
```bash
bin/magento queue:consumers:start dubors.user.behavior.event.consumer &
bin/magento queue:consumers:start dubors.recommendation.generate.consumer &
bin/magento queue:consumers:start dubors.notification.send.consumer &
```

### Check Module Status
```bash
bin/magento module:status | grep BrainStation23_Dubors
```

---

## 📍 Key URLs

| URL | Purpose | Access |
|-----|---------|--------|
| `/admin/dubors/recommendation/index` | View recommendations grid | Admin only |
| `/admin/system/config/edit/section/dubors` | Configure DUBORS | Admin only |
| `/dubors/track/event` | Track custom events | POST, public |
| `/graphql` | GraphQL API | GET/POST, public |

---

## 🔧 Configuration Options

### Access Configuration
**Stores > Configuration > BrainStation23 > DUBORS**

### Important Settings
- `dubors/general/enabled` - Enable/disable module
- `dubors/ml_service/service_enabled` - Enable ML integration
- `dubors/recommendations/min_confidence` - Minimum score threshold
- `dubors/recommendations/auto_approve` - Auto-approve recommendations

### Programmatic Access
```php
// Inject Config helper
private readonly Config $config

// Get configuration
$isEnabled = $this->config->isEnabled($storeId);
$minScore = $this->config->getMinConfidenceScore();
$apiKey = $this->config->getMlServiceApiKey();
```

---

## 📊 Database Tables

### vendor_dubors_user_behavior
```sql
SELECT * FROM vendor_dubors_user_behavior 
WHERE customer_id = 123 
ORDER BY created_at DESC;
```

**Columns**: entity_id, customer_id, behavior_type, product_id, metadata, ip_address, user_agent, created_at, updated_at

### vendor_dubors_recommendation
```sql
SELECT * FROM vendor_dubors_recommendation 
WHERE status = 'pending' 
ORDER BY confidence_score DESC;
```

**Columns**: entity_id, customer_id, product_id, recommendation_type, confidence_score, status, created_at, updated_at

---

## 📱 GraphQL Examples

### Query Recommendations
```graphql
query {
  duborsRecommendations(
    customerId: 123
    status: "pending"
    limit: 5
  ) {
    items {
      id
      product_id
      confidence_score
    }
    total_count
    page_info {
      has_next_page
      current_page
    }
  }
}
```

### Approve Recommendation
```graphql
mutation {
  duborsApproveRecommendation(id: 456) {
    success
    message
    recommendation {
      id
      status
    }
  }
}
```

### Query User Behavior
```graphql
query {
  duborsUserBehaviors(
    customerId: 123
    behaviorType: "product_view"
    limit: 10
  ) {
    items {
      id
      behavior_type
      product_id
      created_at
    }
    total_count
  }
}
```

---

## 🔄 Message Queue Topics

### Publish Event
```php
// Inject publisher
private readonly PublisherInterface $publisher

// Publish behavior event
$this->publisher->publish('dubors.user.behavior.event', json_encode([
    'customer_id' => $customerId,
    'behavior_type' => 'product_view',
    'product_id' => $productId,
    'ip_address' => $_SERVER['REMOTE_ADDR'],
    'user_agent' => $_SERVER['HTTP_USER_AGENT']
]));

// Publish recommendation
$this->publisher->publish('dubors.recommendation.generate', json_encode([
    'customer_id' => $customerId,
    'product_id' => $productId,
    'recommendation_type' => 'collaborative',
    'confidence_score' => 87.5
]));

// Publish notification
$this->publisher->publish('dubors.notification.send', json_encode([
    'recipient_email' => 'customer@example.com',
    'subject' => 'New Recommendations',
    'template_id' => 'dubors_notification',
    'template_vars' => ['name' => 'John']
]));
```

### Topics Available
- `dubors.user.behavior.event` → UserBehaviorConsumer
- `dubors.recommendation.generate` → RecommendationConsumer
- `dubors.notification.send` → NotificationConsumer

---

## 🎛️ Admin Grid Actions

### Approve Multiple
1. Select items in grid
2. Choose action: "Approve"
3. Click "Submit"

### Reject Recommendation
1. Click "Reject" in Actions column
2. Confirm in dialog
3. Status updated to "rejected"

### Filter by Status
- Pending
- Approved
- Rejected
- Expired

---

## 🛠️ Developer Integration

### Tracking Custom Events
```php
// From any PHP code
public function __construct(
    private readonly \Magento\Framework\MessageQueue\PublisherInterface $publisher
) {}

public function trackCustomEvent($customerId, $type, $data) {
    $this->publisher->publish('dubors.user.behavior.event', json_encode([
        'customer_id' => $customerId,
        'behavior_type' => $type,
        'metadata' => json_encode($data)
    ]));
}
```

### Getting Recommendations
```php
public function __construct(
    private readonly \BrainStation23\Dubors\Model\RecommendationRepository $repo
) {}

public function getApproved($customerId) {
    $collection = $this->repo->getByCustomer($customerId);
    $collection->addFieldToFilter('status', 'approved');
    return $collection;
}
```

### Accessing User Behaviors
```php
public function __construct(
    private readonly \BrainStation23\Dubors\Model\UserBehaviorRepository $repo
) {}

public function getViewEvents($customerId) {
    $collection = $this->repo->getByCustomer($customerId);
    $collection->addFieldToFilter('behavior_type', 'product_view');
    return $collection;
}
```

---

## 📝 Event Tracking (Automatic)

### Tracked Events (5 Built-in)
| Event | Observer | Data Captured |
|-------|----------|---------------|
| Product View | TrackProductView | product_id, customer_id |
| Add to Cart | TrackAddToCart | product_id, qty, customer_id |
| Purchase | TrackOrderSuccess | product_ids, customer_id |
| Wishlist Add | TrackWishlistAdd | product_id, customer_id |
| Search | TrackSearch | query, results_count |

### Manual Tracking (Frontend JS)
```javascript
// Include tracker script in template
<script src="/static/frontend/web/js/dubors-tracker.js"></script>

// Track product view
DuborsTracker.trackProductView(productId);

// Track add to cart
DuborsTracker.trackAddToCart(productId, quantity);

// Track search
DuborsTracker.trackSearch(query);

// Track custom event
DuborsTracker.trackCustom('custom_type', {extra: 'data'});
```

---

## 🔍 Logs & Debugging

### Enable Debug Mode
1. Go to Configuration > DUBORS > General
2. Set "Debug Mode" to Yes
3. Events logged to `var/log/system.log`

### View Consumer Logs
```bash
tail -f var/log/system.log | grep "Processed user behavior"
```

### Check Message Queue
```bash
# List queues (if using admin panel or CLI)
sudo rabbitmqctl list_queues

# Check consumer status
ps aux | grep queue:consumers:start
```

---

## 🎯 Common Tasks

### Approve All Pending Recommendations
```sql
UPDATE vendor_dubors_recommendation 
SET status = 'approved', updated_at = NOW() 
WHERE status = 'pending';
```

### Get Top Customers by Activity
```sql
SELECT customer_id, COUNT(*) as event_count 
FROM vendor_dubors_user_behavior 
GROUP BY customer_id 
ORDER BY event_count DESC 
LIMIT 10;
```

### Export Recommendations for ML Training
```php
// Use GraphQL query with large limit
$url = '/graphql';
$query = 'query { duborsRecommendations(limit: 10000) { items { ... } } }';
// POST to endpoint and process response
```

### Purge Old Behavior Data
```sql
DELETE FROM vendor_dubors_user_behavior 
WHERE created_at < DATE_SUB(NOW(), INTERVAL 365 DAY);
```

---

## ⚙️ Troubleshooting

### GraphQL Endpoint Not Found
- Check: Module enabled
- Check: DI compilation successful
- Check: Resolver classes properly namespaced

### Message Queue Not Processing
- Start consumers: `bin/magento queue:consumers:start`
- Check: RabbitMQ service running
- Check: Permissions on queue directories

### Admin Grid Empty
- Check: Recommendations exist in database
- Check: Current store scope has data
- Check: Filters not too restrictive

### Configuration Not Saving
- Check: ACL permissions granted
- Check: Required fields filled
- Check: No validation errors

### Events Not Tracking
- Check: Observer registered in events.xml
- Check: Event name matches exactly
- Check: Tracking enabled in config

---

## 📚 File Locations

| Component | Location |
|-----------|----------|
| Models | `Model/*.php` |
| Repositories | `Model/*Repository.php` |
| Controllers | `Controller/Adminhtml/*` |
| Observers | `Observer/*.php` |
| API | `Controller/Track/Event.php` |
| GraphQL | `Model/Resolver/*.php` |
| Config | `Helper/Config.php` |
| Queues | `Model/MessageQueue/*.php` |
| Database | `Setup/InstallSchema.php` |
| JavaScript | `view/frontend/web/js/` |
| Templates | `view/frontend/templates/` |

---

## 🔐 Security Checklist

- [ ] API Key encrypted in configuration
- [ ] Admin routes protected by ACL
- [ ] Input validation on all API endpoints
- [ ] SQL injection prevention (use filters)
- [ ] XSS prevention (UI components)
- [ ] CSRF protection (Magento native)

---

## 📞 Support Resources

- **Module Documentation**: `/app/code/BrainStation23/Dubors/docs/`
- **Phase Completion Reports**: `PHASE_*_COMPLETION.md`
- **Database Schema**: `Setup/InstallSchema.php`
- **Configuration Reference**: `etc/config.xml`
- **GraphQL Schema**: `etc/graphql/schema.graphqls`

---

## 🚀 Next Steps (Phase 5+)

1. Implement ML Service Integration
2. Add Cron Jobs for batch processing
3. Build Testing Suite
4. Optimize Performance
5. Create User Documentation

---

**Quick Links**:
- Admin Grid: `/admin/dubors/recommendation/index`
- Configuration: `/admin/system/config/edit/section/dubors`
- GraphQL Explorer: `/graphql` (with GraphQL IDE extension)
- API Endpoint: `/dubors/track/event`

**Version**: 1.0.0  
**Magento**: 2.4.x  
**PHP**: 8.2+
