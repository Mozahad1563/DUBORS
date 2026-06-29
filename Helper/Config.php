<?php
/**
 * BrainStation23
 *
 * @category    BrainStation23
 * @package     BrainStation23_Dubors
 * @copyright   Copyright (c) 2026 BrainStation23
 */

declare(strict_types=1);

namespace BrainStation23\Dubors\Helper;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Store\Model\ScopeInterface;

class Config
{
    public const CONFIG_PATH_ENABLED = 'dubors/general/enabled';
    public const CONFIG_PATH_TRACKING_ENABLED = 'dubors/general/tracking_enabled';
    public const CONFIG_PATH_DEBUG_MODE = 'dubors/general/debug_mode';

    public const CONFIG_PATH_ML_SERVICE_ENABLED = 'dubors/ml_service/service_enabled';
    public const CONFIG_PATH_ML_SERVICE_URL = 'dubors/ml_service/service_url';
    public const CONFIG_PATH_ML_BROWSER_URL = 'dubors/ml_service/browser_url';
    public const CONFIG_PATH_ML_SERVICE_TIMEOUT = 'dubors/ml_service/service_timeout';
    public const CONFIG_PATH_ML_SERVICE_API_KEY = 'dubors/ml_service/api_key';

    public const CONFIG_PATH_MIN_CONFIDENCE = 'dubors/recommendations/min_confidence';
    public const CONFIG_PATH_MAX_RECOMMENDATIONS = 'dubors/recommendations/max_recommendations';
    public const CONFIG_PATH_VALIDITY_DAYS = 'dubors/recommendations/validity_days';
    public const CONFIG_PATH_AUTO_APPROVE = 'dubors/recommendations/auto_approve';

    public const CONFIG_PATH_DATA_RETENTION_DAYS = 'dubors/advanced/data_retention_days';
    public const CONFIG_PATH_BATCH_SIZE = 'dubors/advanced/batch_size';
    public const CONFIG_PATH_ENABLE_EXPORT = 'dubors/advanced/enable_export';

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param EncryptionInterface $encryption
     */
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly EncryptorInterface $encryption
    ) {
    }

    /**
     * Check if DUBORS is enabled
     *
     * @param int|string|null $storeId
     * @return bool
     */
    public function isEnabled($storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::CONFIG_PATH_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Check if event tracking is enabled
     *
     * @param int|string|null $storeId
     * @return bool
     */
    public function isTrackingEnabled($storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::CONFIG_PATH_TRACKING_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Check if debug mode is enabled
     *
     * @param int|string|null $storeId
     * @return bool
     */
    public function isDebugMode($storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::CONFIG_PATH_DEBUG_MODE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Check if ML service is enabled
     *
     * @param int|string|null $storeId
     * @return bool
     */
    public function isMlServiceEnabled($storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::CONFIG_PATH_ML_SERVICE_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Get ML service URL
     *
     * @param int|string|null $storeId
     * @return string|null
     */
    public function getMlServiceUrl($storeId = null): ?string
    {
        return $this->scopeConfig->getValue(
            self::CONFIG_PATH_ML_SERVICE_URL,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Get browser-accessible ML service URL (used for WebSocket and dashboard links in the admin UI)
     *
     * Falls back to the server URL when not explicitly configured.
     *
     * @param int|string|null $storeId
     * @return string|null
     */
    public function getMlBrowserUrl($storeId = null): ?string
    {
        $url = $this->scopeConfig->getValue(
            self::CONFIG_PATH_ML_BROWSER_URL,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
        return $url ?: $this->getMlServiceUrl($storeId);
    }

    /**
     * Get ML service timeout
     *
     * @param int|string|null $storeId
     * @return int
     */
    public function getMlServiceTimeout($storeId = null): int
    {
        $timeout = $this->scopeConfig->getValue(
            self::CONFIG_PATH_ML_SERVICE_TIMEOUT,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
        return (int)($timeout ?? 30);
    }

    /**
     * Get ML service API key (encrypted)
     *
     * @param int|string|null $storeId
     * @return string|null
     */
    public function getMlServiceApiKey($storeId = null): ?string
    {
        $encryptedKey = $this->scopeConfig->getValue(
            self::CONFIG_PATH_ML_SERVICE_API_KEY,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        if ($encryptedKey) {
            return $this->encryption->decrypt($encryptedKey);
        }

        return null;
    }

    /**
     * Get minimum confidence score
     *
     * @param int|string|null $storeId
     * @return int
     */
    public function getMinConfidenceScore($storeId = null): int
    {
        $score = $this->scopeConfig->getValue(
            self::CONFIG_PATH_MIN_CONFIDENCE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
        return (int)($score ?? 75);
    }

    /**
     * Get maximum recommendations per customer
     *
     * @param int|string|null $storeId
     * @return int
     */
    public function getMaxRecommendationsPerCustomer($storeId = null): int
    {
        $max = $this->scopeConfig->getValue(
            self::CONFIG_PATH_MAX_RECOMMENDATIONS,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
        return (int)($max ?? 5);
    }

    /**
     * Get recommendation validity in days
     *
     * @param int|string|null $storeId
     * @return int
     */
    public function getRecommendationValidityDays($storeId = null): int
    {
        $days = $this->scopeConfig->getValue(
            self::CONFIG_PATH_VALIDITY_DAYS,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
        return (int)($days ?? 30);
    }

    /**
     * Check if auto-approve is enabled
     *
     * @param int|string|null $storeId
     * @return bool
     */
    public function isAutoApproveEnabled($storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::CONFIG_PATH_AUTO_APPROVE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Get data retention days
     *
     * @param int|string|null $storeId
     * @return int
     */
    public function getDataRetentionDays($storeId = null): int
    {
        $days = $this->scopeConfig->getValue(
            self::CONFIG_PATH_DATA_RETENTION_DAYS,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
        return (int)($days ?? 365);
    }

    /**
     * Get batch processing size
     *
     * @param int|string|null $storeId
     * @return int
     */
    public function getBatchSize($storeId = null): int
    {
        $size = $this->scopeConfig->getValue(
            self::CONFIG_PATH_BATCH_SIZE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
        return (int)($size ?? 500);
    }

    /**
     * Check if data export is enabled
     *
     * @param int|string|null $storeId
     * @return bool
     */
    public function isDataExportEnabled($storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::CONFIG_PATH_ENABLE_EXPORT,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }
}
