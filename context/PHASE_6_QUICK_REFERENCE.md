# Phase 6 Quick Reference - Cron Jobs

## 📅 Cron Schedule Overview

```
TIME              FREQUENCY         JOB                           DURATION
─────────────────────────────────────────────────────────────────────────
02:00 AM          Daily             GenerateRecommendations       5-30 min
03:00 AM          Daily             PurgeOldBehavior              2-5 min
Every 30 min      Continuous        UpdateModelStatus             <1 min
Every 5 min       Continuous        MonitorMlService              <5 sec
04:00 AM (Sun)    Weekly            CleanupLogs                   1-2 min
```

---

## 🚀 Job Descriptions

### 1. GenerateRecommendations (2 AM Daily)
**Purpose**: Batch generate ML recommendations for all customers

**What it does**:
- Loads all customers in batches of 100
- Calls RecommendationEngine for each customer
- Saves 10 recommendations per customer
- Sets 30-day expiration on recommendations
- Deletes previous recommendations to keep fresh

**Configuration**:
- Batch size: 100 customers
- Recommendations per customer: 10
- Expiration: 30 days
- Error resilience: Continues on individual customer failures

**Log Output Example**:
```
INFO: Starting batch recommendation generation cron job
INFO: Processing 5000 customers for recommendations
INFO: Processed 500/5000 customers (10.00% complete)
INFO: Batch recommendation generation completed. Total: 5000, Successful: 4950, Failed: 50, Time: 600s
```

---

### 2. PurgeOldBehavior (3 AM Daily)
**Purpose**: Delete old behavior records and expired recommendations

**What it does**:
- Gets retention days from config (default: 90)
- Deletes all behavior records older than that
- Deletes all recommendations with expires_at < now
- Cleans up database to maintain performance

**Configuration**:
- Retention days: 90 (configurable)
- Behavior table: dubors_user_behavior
- Recommendation table: dubors_recommendation

**Benefits**:
- Reduces database size
- Improves query performance
- Privacy compliance (GDPR)
- Configurable retention policy

**Log Output Example**:
```
INFO: Starting old behavior records purge cron job
INFO: Purging behavior records older than 90 days (before 2023-10-15 03:00:00)
INFO: Deleted 15,234 old behavior records
INFO: Deleted 8,942 expired recommendations
INFO: Purge completed: 15234 behavior records and 8942 expired recommendations deleted
```

---

### 3. UpdateModelStatus (Every 30 Minutes)
**Purpose**: Check if ML models are stale and sync status from service

**What it does**:
- Marks models unchanged for 24+ hours as 'stale'
- Queries ML service for model status updates
- Updates accuracy metrics in database
- Cleans up training records older than 90 days
- Never crashes even if service is down

**Configuration**:
- Staleness threshold: 24 hours
- Query ML service: Yes
- Training log cleanup: 90+ days old

**Model Status Lifecycle**:
```
New → Training → Completed → (24h) → Stale → Retraining
```

**Log Output Example**:
```
INFO: Starting model status update cron job
INFO: Marked 2 models as stale
INFO: Updated 3 model training records
INFO: Cleaned up 5 old training records
INFO: Model status update completed: 2 stale, 3 updated, 5 cleanup
```

---

### 4. MonitorMlService (Every 5 Minutes)
**Purpose**: Health check the ML service and track failures

**What it does**:
- Calls ML service health check endpoint
- Measures response time
- Records health status in database
- Tracks consecutive failures
- Triggers alert at 3 consecutive failures

**Configuration**:
- Check timeout: 10 seconds
- Alert threshold: 3 consecutive failures (~15 minutes)
- Response time threshold: None (just logs)

**Service Status States**:
- Healthy: All checks passing
- Unhealthy: <3 failures (uses fallback)
- Critical: ≥3 failures (alerts triggered)

**Log Output Example**:
```
INFO: Starting ML service health check cron job
DEBUG: ML service health check response time: 245.32ms
INFO: ML service health check passed
WARNING: ML service health check failed
WARNING: ML service failure count incremented to: 1
CRITICAL: ML service has failed 3 consecutive health checks! Fallback mode activated.
```

---

### 5. CleanupLogs (Sunday 4 AM Weekly)
**Purpose**: Remove old Dubors log files to manage disk space

**What it does**:
- Scans var/log/dubors/ directory recursively
- Deletes .log files older than 30 days
- Calculates freed space
- Reports statistics

**Configuration**:
- Retention: 30 days
- Location: var/log/dubors/
- File pattern: *.log

**Space Management**:
- Calculates freed space in human-readable format
- Example: "Freed 125.5 MB"

**Log Output Example**:
```
INFO: Starting log cleanup cron job
DEBUG: Deleted old log file: system-2023-01-15.log (modified: 2023-01-15 10:30:45)
DEBUG: Deleted old log file: service-2023-01-14.log (modified: 2023-01-14 23:15:22)
INFO: Log cleanup completed: 45 files deleted, approximately 250.75 MB freed
```

---

## 🔧 Configuration & Customization

### Enable/Disable Jobs
Add to `etc/crontab.xml`:
```xml
<config_path>dubors/cron/generate_recommendations_enabled</config_path>
```

Then in admin: **Stores > Configuration > Dubors > Scheduled Tasks**

### Adjust Schedule
Edit `etc/crontab.xml`:
```xml
<schedule>0 2 * * *</schedule>  <!-- Current: 2 AM daily -->
<schedule>0 1 * * *</schedule>  <!-- Alternative: 1 AM daily -->
<schedule>*/15 * * * *</schedule> <!-- Every 15 minutes -->
```

### Cron Schedule Format (Crontab)
```
MIN  HOUR  DAY  MONTH  WEEKDAY
─────────────────────────────
 0    2    *    *     *      = 2:00 AM every day
 0    2    1    *     *      = 2:00 AM on 1st of month
 0    2    *    *     0      = 2:00 AM every Sunday
*/30  *    *    *     *      = Every 30 minutes
 0    *    *    *     *      = Every hour on the hour
 */5  *    *    *     *      = Every 5 minutes
```

---

## 🐛 Troubleshooting

### Cron Job Not Running
```bash
# Check if cron is configured in server
crontab -l | grep magento

# Manually trigger cron
php bin/magento cron:run --group=dubors_recommendations

# Check Magento cron job queue
php bin/magento cron:run
```

### Job Executing Too Long
1. **GenerateRecommendations**:
   - Reduce batch size: Edit code to 50 instead of 100
   - Check ML service response time
   - Verify database indexes on customer table

2. **PurgeOldBehavior**:
   - Large dataset: Run more frequently
   - Check database space available
   - Verify write permissions

### Service Health Check Failing
1. Verify ML service URL in config
2. Check API key/token
3. Test endpoint manually: `curl -H "Authorization: Bearer TOKEN" https://ml-service/health`
4. Check network connectivity

### Log File Cleanup Not Working
1. Verify `var/log/dubors/` directory exists
2. Check directory permissions (must be writable)
3. Verify log files have .log extension
4. Check disk space (cleanup may fail if full)

---

## 📊 Monitoring & Alerts

### Key Metrics to Watch
1. **GenerateRecommendations**
   - Execution time (should be <30 min)
   - Success/failure ratio (should be >95%)
   - Average time per customer (should be <200ms)

2. **PurgeOldBehavior**
   - Records deleted (depends on usage)
   - Execution time (should be <5 min)
   - No errors in logs

3. **MonitorMlService**
   - Response time (should be <5 sec)
   - Consecutive failures (should be 0)
   - Alert triggers (should be rare)

4. **CleanupLogs**
   - Files deleted (depends on log volume)
   - Space freed (aim for 100+ MB)
   - Execution time (should be <2 min)

### Alert Conditions
- ⚠️ GenerateRecommendations: >45 min = Warning
- 🚨 MonitorMlService: 3 failures = Critical
- ⚠️ CleanupLogs: Failed to delete = Warning
- ⚠️ Any job: Execution fails = Warning

---

## 📝 Log Locations

```
var/log/dubors/
├── system.log              # General system events
├── cron.log               # Cron job execution logs
├── ml-service.log         # ML service calls
├── recommendation.log     # Recommendation generation
├── behavior-cleanup.log   # Data purge events
└── [other logs]           # Additional logging
```

**View logs**:
```bash
tail -f var/log/dubors/system.log
tail -100 var/log/dubors/cron.log | grep "GenerateRecommendations"
grep "CRITICAL" var/log/dubors/*.log
```

---

## 🔐 Security Considerations

### Service Health Checks
- ✅ No sensitive data in health check responses
- ✅ Bearer token authentication
- ✅ Timeout prevents hanging

### Database Operations
- ✅ Parameterized queries (no SQL injection)
- ✅ Proper error handling
- ✅ Transaction safety

### Log Files
- ✅ No sensitive data logged
- ✅ Automatic cleanup prevents disk fill
- ✅ Logs stored in Magento var directory

---

## 🚀 Performance Optimization Tips

1. **GenerateRecommendations**:
   - Run at off-peak hours (2 AM chosen for this)
   - Batch size of 100 = optimal balance
   - Index on customer_id and created_at

2. **PurgeOldBehavior**:
   - Indexes on created_at and expires_at tables
   - Delete in batches if table very large
   - Run after GenerateRecommendations completes

3. **UpdateModelStatus**:
   - Lightweight (no heavy computation)
   - Quick database updates
   - Service timeout prevents hangs

4. **MonitorMlService**:
   - 5-minute interval = good balance
   - Lightweight health check
   - No database writes (only reads)

5. **CleanupLogs**:
   - Runs weekly = minimal impact
   - Off-peak time (4 AM Sunday)
   - Improves system performance

---

## 📋 Production Deployment

### Pre-Deployment Checklist
- [ ] All 6 cron files in Cron/ directory
- [ ] etc/crontab.xml configured
- [ ] Module enabled
- [ ] Cache cleared
- [ ] Database tables exist (Phase 1)
- [ ] Server crontab has Magento entry
- [ ] Logging configured
- [ ] Monitoring alerts set up

### Post-Deployment Verification
1. Manually trigger: `php bin/magento cron:run --group=dubors_recommendations`
2. Check logs for successful execution
3. Verify database updates (new recommendations, deleted old records)
4. Monitor first 24 hours
5. Verify each job runs on schedule

### Ongoing Monitoring
- Daily: Check logs for errors
- Weekly: Monitor disk space (log cleanup)
- Monthly: Review statistics and performance

---

## 📞 Common Questions

**Q: When should I run cron jobs?**
A: Use provided schedule (2 AM, 3 AM, etc.) or customize based on your traffic patterns.

**Q: Can I disable specific jobs?**
A: Yes, add `<config_path>` to crontab.xml and toggle in admin config.

**Q: What if ML service is down?**
A: Fallback strategy automatically activates. Health check detects it, future recommendations use fallback scoring.

**Q: How long do cron jobs take?**
A: GenerateRecommendations: 5-30 min (depends on customer count). Others: <5 min.

**Q: Can I run jobs more frequently?**
A: Yes, but consider system load. Minimum recommended: 30 min for GenerateRecommendations, 5 min for others.

---

**Quick Links**:
- Full Documentation: See `PHASE_6_COMPLETION.md`
- Project Progress: See `PROJECT_PROGRESS_REPORT.md`
- Architecture Guide: See `ARCHITECTURE.md`
- Admin Setup: Go to Stores > Configuration > Dubors
