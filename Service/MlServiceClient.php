<?php
/**
 * BrainStation23
 *
 * @category    BrainStation23
 * @package     BrainStation23_Dubors
 * @copyright   Copyright (c) 2026 BrainStation23
 */

declare(strict_types=1);

namespace BrainStation23\Dubors\Service;

use BrainStation23\Dubors\Helper\Config;
use BrainStation23\Dubors\Service\Exception\MlServiceException;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\Serialize\Serializer\Json;
use Psr\Log\LoggerInterface;

/**
 * ML Service Client for communicating with microservice
 *
 * @api
 */
class MlServiceClient
{
    /**
     * Retry configuration
     */
    private const MAX_RETRIES = 3;
    private const INITIAL_RETRY_DELAY = 1; // seconds
    private const RETRY_BACKOFF_MULTIPLIER = 2;

    /**
     * Rate limiting
     */
    private const RATE_LIMIT_KEY = 'dubors_ml_rate_limit';
    private const RATE_LIMIT_REQUESTS = 100;
    private const RATE_LIMIT_WINDOW = 60; // seconds

    /**
     * Cache for rate limit tracking
     *
     * @var array
     */
    private array $requestTimestamps = [];

    /**
     * @param Config $config
     * @param Curl $curl
     * @param Json $json
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly Config $config,
        private readonly Curl $curl,
        private readonly Json $json,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Generate recommendations for customer
     *
     * FastAPI endpoint: POST /recommend
     * Expects UserContext: {customer_id, visit_count, cart_value,
     *                       days_since_last_purchase, avg_order_value, cart_abandonment_count}
     *
     * @param int $customerId
     * @param array $userBehaviors  Raw behavior rows from vendor_dubors_user_behavior
     * @param array $options
     * @return array
     * @throws MlServiceException
     */
    public function generateRecommendations(
        int $customerId,
        array $userBehaviors,
        array $options = []
    ): array {
        if (!$this->config->isMlServiceEnabled()) {
            throw new MlServiceException(
                'ML Service is not enabled',
                MlServiceException::ERROR_SERVICE_UNAVAILABLE,
                ['service_enabled' => false]
            );
        }

        $this->checkRateLimit();

        $payload = $this->buildUserContext($customerId, $userBehaviors);

        if (!empty($options['product_ids'])) {
            $payload['product_ids'] = array_map('intval', $options['product_ids']);

            $viewCounts = $this->getLastProductViewCounts();
            $productContexts = [];
            foreach ($options['product_ids'] as $pid) {
                $pid = (int)$pid;
                $price = 0.0;
                $cost = 0.0;
                $categoryId = 0;
                if (!empty($options['product_data'][$pid])) {
                    $price = (float)($options['product_data'][$pid]['price'] ?? 0);
                    $cost = (float)($options['product_data'][$pid]['cost'] ?? 0);
                    $categoryId = (int)($options['product_data'][$pid]['category_id'] ?? 0);
                }
                $productContexts[] = [
                    'product_id' => $pid,
                    'price' => $price,
                    'cost' => $cost,
                    'category_id' => $categoryId,
                    'user_views' => $viewCounts[$pid] ?? 0,
                ];
            }
            $payload['product_contexts'] = $productContexts;
        }

        if (!empty($options['segment'])) {
            $payload['segment'] = (string)$options['segment'];
        }
        if (isset($options['rfm_score'])) {
            $payload['rfm_score'] = (float)$options['rfm_score'];
        }
        if (isset($options['discounts_received_30d'])) {
            $payload['discounts_received_30d'] = (int)$options['discounts_received_30d'];
        }
        if (isset($options['discounts_redeemed_30d'])) {
            $payload['discounts_redeemed_30d'] = (int)$options['discounts_redeemed_30d'];
        }

        $attempt = 0;
        $lastException = null;

        while ($attempt < self::MAX_RETRIES) {
            try {
                $this->logger->debug(
                    'Calling ML service for recommendations',
                    ['customer_id' => $customerId, 'attempt' => $attempt + 1]
                );

                $response = $this->makeRequest('/recommend', $payload);

                $this->logger->info(
                    'Successfully generated recommendations',
                    ['customer_id' => $customerId, 'count' => count($response['recommendations'] ?? [])]
                );

                return $response;
            } catch (MlServiceException $e) {
                $lastException = $e;
                $attempt++;

                if (!$e->isRetryable() || $attempt >= self::MAX_RETRIES) {
                    throw $e;
                }

                $waitTime = self::INITIAL_RETRY_DELAY * (self::RETRY_BACKOFF_MULTIPLIER ** ($attempt - 1));
                $this->logger->warning(
                    'ML service request failed, retrying',
                    ['attempt' => $attempt, 'error_code' => $e->getErrorCode(), 'wait_time' => $waitTime]
                );

                sleep($waitTime);
            }
        }

        if ($lastException) {
            throw $lastException;
        }

        throw new MlServiceException(
            'Failed to generate recommendations after multiple attempts',
            MlServiceException::ERROR_PROCESSING_FAILED,
            ['customer_id' => $customerId]
        );
    }

    /**
     * Train model with historical data
     *
     * FastAPI endpoint: POST /train
     * Expects TrainingRequest: {data: [{customer_id, features: float[], label, weight, source}]}
     *
     * @param array $trainingData  Output of FeatureExtractor::extractTrainingData()
     * @return array
     * @throws MlServiceException
     */
    public function trainModel(array $trainingData): array
    {
        $this->checkRateLimit();

        $payload = ['data' => $this->buildTrainingPayload($trainingData)];

        $this->logger->info('Starting model training', ['data_points' => count($payload['data'])]);

        $response = $this->makeRequest('/train', $payload, 300);

        $this->logger->info(
            'Model training completed',
            ['model_id' => $response['model_id'] ?? 'unknown', 'mae' => $response['mae'] ?? 'unknown']
        );

        return $response;
    }

    /**
     * Get model metrics / status
     *
     * FastAPI endpoint: GET /metrics
     *
     * @return array
     * @throws MlServiceException
     */
    public function getModelStatus(): array
    {
        $this->checkRateLimit();

        $this->logger->debug('Fetching model metrics');

        return $this->makeRequest('/metrics', [], timeout: 10, method: 'GET');
    }

    /**
     * Transform behavior rows into the UserContext shape expected by POST /recommend
     *
     * @param int $customerId
     * @param array $userBehaviors
     * @return array
     */
    private function buildUserContext(int $customerId, array $userBehaviors): array
    {
        $visitCount = 0;
        $cartValue = 0.0;
        $cartAbandonmentCount = 0;
        $purchaseValues = [];
        $lastPurchaseTs = null;
        $cartedProducts = [];
        $purchasedProducts = [];
        $productViewCounts = [];
        $eventsLast7d = 0;
        $eventsLast30d = 0;
        $totalOrders = 0;
        $earliestEvent = null;

        $now = time();
        $cutoff7d = $now - (7 * 86400);
        $cutoff30d = $now - (30 * 86400);

        foreach ($userBehaviors as $behavior) {
            $type = is_array($behavior) ? ($behavior['behavior_type'] ?? '') : $behavior->getBehaviorType();
            $productId = is_array($behavior) ? ($behavior['product_id'] ?? null) : $behavior->getProductId();
            $value = (float)(is_array($behavior) ? ($behavior['cart_value'] ?? 0) : $behavior->getCartValue());
            $createdAt = is_array($behavior) ? ($behavior['created_at'] ?? null) : $behavior->getCreatedAt();
            $eventTs = $createdAt ? strtotime($createdAt) : null;

            if ($eventTs) {
                if ($eventTs >= $cutoff7d) {
                    $eventsLast7d++;
                }
                if ($eventTs >= $cutoff30d) {
                    $eventsLast30d++;
                }
                if ($earliestEvent === null || $eventTs < $earliestEvent) {
                    $earliestEvent = $eventTs;
                }
            }

            switch ($type) {
                case 'product_view':
                    $visitCount++;
                    if ($productId) {
                        $productViewCounts[$productId] = ($productViewCounts[$productId] ?? 0) + 1;
                    }
                    break;
                case 'add_to_cart':
                    $cartValue += $value;
                    if ($productId) {
                        $cartedProducts[$productId] = true;
                        $productViewCounts[$productId] = ($productViewCounts[$productId] ?? 0) + 1;
                    }
                    break;
                case 'purchase':
                    $totalOrders++;
                    $purchaseValues[] = $value;
                    if ($createdAt && !$lastPurchaseTs) {
                        $lastPurchaseTs = $eventTs;
                    }
                    break;
                case 'purchase_item':
                    $purchaseValues[] = $value;
                    if ($productId) {
                        $purchasedProducts[$productId] = true;
                    }
                    break;
                case 'wishlist_add':
                    if ($productId) {
                        $productViewCounts[$productId] = ($productViewCounts[$productId] ?? 0) + 2;
                    }
                    break;
            }
        }

        foreach (array_keys($cartedProducts) as $pid) {
            if (!isset($purchasedProducts[$pid])) {
                $cartAbandonmentCount++;
            }
        }

        $daysSinceLastPurchase = $lastPurchaseTs
            ? (int)floor(($now - $lastPurchaseTs) / 86400)
            : null;

        $avgOrderValue = !empty($purchaseValues)
            ? array_sum($purchaseValues) / count($purchaseValues)
            : 0.0;

        $accountAgeDays = $earliestEvent ? max(1, (int)floor(($now - $earliestEvent) / 86400)) : 1;

        $this->_productViewCounts = $productViewCounts;

        return [
            'customer_id'               => $customerId,
            'visit_count'               => $visitCount,
            'cart_value'                => $cartValue,
            'days_since_last_purchase'  => $daysSinceLastPurchase,
            'avg_order_value'           => $avgOrderValue,
            'cart_abandonment_count'    => $cartAbandonmentCount,
            'events_last_7d'            => $eventsLast7d,
            'events_last_30d'           => $eventsLast30d,
            'total_orders'              => $totalOrders,
            'account_age_days'          => $accountAgeDays,
        ];
    }

    /**
     * @return array
     */
    public function getLastProductViewCounts(): array
    {
        return $this->_productViewCounts ?? [];
    }

    private array $_productViewCounts = [];

    /**
     * Convert FeatureExtractor output into the TrainingData list expected by POST /train
     *
     * @param array $trainingData
     * @return array
     */
    private function buildTrainingPayload(array $trainingData): array
    {
        $result = [];

        foreach ($trainingData as $item) {
            $f = $item['features'] ?? [];
            $result[] = [
                'customer_id' => (int)($f['customer_id'] ?? 0),
                'features'    => array_values($f['features'] ?? []),
                'label'       => (float)($item['label'] ?? 0),
                'weight'      => (float)($item['weight'] ?? 1.0),
                'source'      => 'magento_train',
            ];
        }

        return $result;
    }

    /**
     * Send approval/rejection feedback to the ML service for RL learning.
     *
     * @param string $recommendationId
     * @param int $customerId
     * @param float $originalDiscount
     * @param bool $approved
     * @param float|null $correctedDiscount
     * @return array
     * @throws MlServiceException
     */
    public function sendFeedback(
        string $recommendationId,
        int $customerId,
        float $originalDiscount,
        bool $approved,
        ?float $correctedDiscount = null
    ): array {
        $this->checkRateLimit();

        $payload = [
            'recommendation_id' => $recommendationId,
            'customer_id' => $customerId,
            'original_discount' => $originalDiscount,
            'approved' => $approved,
            'converted' => null,
        ];

        if ($correctedDiscount !== null) {
            $payload['corrected_discount'] = $correctedDiscount;
        }

        $this->logger->info('Sending feedback to ML service', [
            'recommendation_id' => $recommendationId,
            'approved' => $approved,
        ]);

        return $this->makeRequest('/feedback', $payload, 15);
    }

    /**
     * @return bool
     */
    public function isServiceAvailable(): bool
    {
        try {
            $this->makeRequest('/health', [], timeout: 5, method: 'GET');
            return true;
        } catch (MlServiceException $e) {
            $this->logger->warning(
                'ML service health check failed',
                ['error_code' => $e->getErrorCode()]
            );
            return false;
        }
    }

    /**
     * Make HTTP request to ML service
     *
     * @param string $endpoint
     * @param array $payload
     * @param int $timeout
     * @param string $method
     * @return array
     * @throws MlServiceException
     */
    private function makeRequest(
        string $endpoint,
        array $payload = [],
        int $timeout = 30,
        string $method = 'POST'
    ): array {
        try {
            $url = rtrim($this->config->getMlServiceUrl(), '/') . $endpoint;
            $apiKey = $this->config->getMlServiceApiKey();

            if (!$url) {
                throw new MlServiceException(
                    'ML Service URL is not configured',
                    MlServiceException::ERROR_INVALID_INPUT,
                    ['has_url' => false]
                );
            }

            // Configure CURL
            $this->curl->setTimeout($timeout);
            $this->curl->addHeader('Content-Type', 'application/json');
            if ($apiKey) {
                $this->curl->addHeader('Authorization', 'Bearer ' . $apiKey);
            }
            $this->curl->addHeader('User-Agent', 'Magento-2-Dubors/1.0.0');

            // Make request
            if ($method === 'GET') {
                $this->curl->get($url);
            } else {
                $this->curl->post($url, $this->json->serialize($payload));
            }

            // Check HTTP status
            $status = (int)$this->curl->getStatus();
            $responseBody = $this->curl->getBody();

            if ($status === 401 || $status === 403) {
                throw new MlServiceException(
                    'Authentication failed with ML service',
                    MlServiceException::ERROR_AUTHENTICATION_FAILED,
                    ['status_code' => $status],
                    null
                );
            }

            if ($status === 429) {
                throw new MlServiceException(
                    'Rate limit exceeded by ML service',
                    MlServiceException::ERROR_RATE_LIMIT_EXCEEDED,
                    ['status_code' => $status],
                    null
                );
            }

            if ($status >= 500) {
                throw new MlServiceException(
                    'ML service is unavailable',
                    MlServiceException::ERROR_SERVICE_UNAVAILABLE,
                    ['status_code' => $status],
                    null
                );
            }

            if ($status >= 400) {
                throw new MlServiceException(
                    'ML service returned error: ' . $responseBody,
                    MlServiceException::ERROR_INVALID_RESPONSE,
                    ['status_code' => $status, 'response' => $responseBody],
                    null
                );
            }

            if ($status < 200 || $status >= 300) {
                throw new MlServiceException(
                    'Unexpected status code from ML service',
                    MlServiceException::ERROR_INVALID_RESPONSE,
                    ['status_code' => $status],
                    null
                );
            }

            // Parse response
            try {
                $response = $this->json->unserialize($responseBody);
            } catch (\Exception $e) {
                throw new MlServiceException(
                    'Failed to parse ML service response',
                    MlServiceException::ERROR_INVALID_RESPONSE,
                    ['error' => $e->getMessage()],
                    $e
                );
            }

            if (!is_array($response)) {
                throw new MlServiceException(
                    'ML service response is not a valid array',
                    MlServiceException::ERROR_INVALID_RESPONSE,
                    ['response_type' => gettype($response)],
                    null
                );
            }

            return $response;
        } catch (MlServiceException $e) {
            throw $e;
        } catch (\Exception $e) {
            $this->logger->error(
                'ML service request failed',
                [
                    'endpoint' => $endpoint,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]
            );

            throw new MlServiceException(
                'Failed to communicate with ML service',
                MlServiceException::ERROR_CONNECTION_FAILED,
                ['error' => $e->getMessage()],
                $e
            );
        }
    }

    /**
     * Check rate limiting
     *
     * @return void
     * @throws MlServiceException
     */
    private function checkRateLimit(): void
    {
        $now = time();

        // Clean old timestamps (outside window)
        $this->requestTimestamps = array_filter(
            $this->requestTimestamps,
            fn($timestamp) => ($now - $timestamp) <= self::RATE_LIMIT_WINDOW
        );

        // Check if limit exceeded
        if (count($this->requestTimestamps) >= self::RATE_LIMIT_REQUESTS) {
            throw new MlServiceException(
                'Rate limit exceeded for ML service requests',
                MlServiceException::ERROR_RATE_LIMIT_EXCEEDED,
                [
                    'limit' => self::RATE_LIMIT_REQUESTS,
                    'window' => self::RATE_LIMIT_WINDOW,
                    'requests_in_window' => count($this->requestTimestamps)
                ]
            );
        }

        // Add current request
        $this->requestTimestamps[] = $now;
    }

    /**
     * Get request statistics
     *
     * @return array
     */
    public function getStats(): array
    {
        return [
            'pending_requests' => count($this->requestTimestamps),
            'rate_limit' => self::RATE_LIMIT_REQUESTS . '/' . self::RATE_LIMIT_WINDOW . 's',
            'max_retries' => self::MAX_RETRIES
        ];
    }
}
