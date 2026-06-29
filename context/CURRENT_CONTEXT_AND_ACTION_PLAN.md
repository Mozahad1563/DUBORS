# BrainStation23 Dubors - Current Context and Action Plan

**Updated:** 2026-05-08

## Canonical Contract

The module now uses `vendor_dubors_*` tables consistently. Core models, admin UI, GraphQL, queues, cron, and ML orchestration are aligned to:

- `vendor_dubors_user_behavior`
- `vendor_dubors_recommendation`
- `entity_id` as primary key
- `behavior_type` for tracked behavior names
- product recommendation fields: `product_id`, `recommendation_type`, `confidence_score`, `score`, `position`, `status`, `created_at`, `updated_at`, `expires_at`

Offer fields remain on recommendations: `coupon_code`, `discount_percent`, `approved_at`, `redeemed_at`.

## Completed

- PHP syntax blocker fixed.
- `InstallSchema.php` and `db_schema.xml` aligned.
- Resource models now use `entity_id`.
- Behavior and recommendation models expose the methods used by services, admin UI, GraphQL, queues, and cron.
- Direct SQL table references now use the `vendor_dubors_*` prefix.
- GraphQL schema now only exposes implemented root fields and maps them to resolvers.
- Cron config defaults are present.

## Verified

- All module PHP files pass `php -l`.
- All module XML files parse successfully.
- `BrainStation23_Dubors` is enabled in Magento.

## Blocked Validation

Magento DI compile is blocked by local filesystem permissions on `generated/code`, which is owned by `root:root`.

`setup:upgrade --keep-generated` currently fails in Magento core dependency resolution:

```text
Impossible to process constructor argument Parameter #2 [ <required> Magento\Framework\Mview\ViewFactory $viewFactory ] of Magento\Framework\Mview\TriggerCleaner class
```

After fixing ownership/permissions and generated metadata, run:

```bash
php bin/magento setup:upgrade
php bin/magento setup:di:compile
```

## Remaining Build Work

1. Run Magento setup/compile after permissions are fixed.
2. Smoke test admin recommendation grid and approve action.
3. Smoke test frontend tracking endpoint.
4. Smoke test queue consumers.
5. Add or intentionally defer the external `ml-service`; it is still absent from the repository.
