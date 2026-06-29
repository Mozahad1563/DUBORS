<?php
declare(strict_types=1);
/**
 * BrainStation23
 *
 * @category    BrainStation23
 * @package     BrainStation23_Dubors
 * @copyright   Copyright (c) 2026 BrainStation23
 */

namespace BrainStation23\Dubors\Service\Exception;

use Magento\Framework\Exception\LocalizedException;

/**
 * Exception for ML Service errors
 *
 * @api
 */
class MlServiceException extends LocalizedException
{
    /**
     * Error code constants
     */
    public const ERROR_CONNECTION_FAILED = 'connection_failed';
    public const ERROR_REQUEST_TIMEOUT = 'request_timeout';
    public const ERROR_INVALID_RESPONSE = 'invalid_response';
    public const ERROR_AUTHENTICATION_FAILED = 'authentication_failed';
    public const ERROR_SERVICE_UNAVAILABLE = 'service_unavailable';
    public const ERROR_RATE_LIMIT_EXCEEDED = 'rate_limit_exceeded';
    public const ERROR_INVALID_INPUT = 'invalid_input';
    public const ERROR_PROCESSING_FAILED = 'processing_failed';

    /**
     * @var string
     */
    private string $errorCode = '';

    /**
     * @var array
     */
    private array $context = [];

    /**
     * @var int
     */
    private int $retryCount = 0;

    /**
     * Constructor
     *
     * @param string $message
     * @param string $errorCode
     * @param array $context
     * @param \Exception|null $previous
     */
    public function __construct(
        string $message = '',
        string $errorCode = '',
        array $context = [],
        ?\Exception $previous = null
    ) {
        $this->errorCode = $errorCode;
        $this->context = $context;
        parent::__construct(__($message), $previous);
    }

    /**
     * Get error code
     *
     * @return string
     */
    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    /**
     * Get context data
     *
     * @return array
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Set retry count
     *
     * @param int $retryCount
     * @return self
     */
    public function setRetryCount(int $retryCount): self
    {
        $this->retryCount = $retryCount;
        return $this;
    }

    /**
     * Get retry count
     *
     * @return int
     */
    public function getRetryCount(): int
    {
        return $this->retryCount;
    }

    /**
     * Check if error is retryable
     *
     * @return bool
     */
    public function isRetryable(): bool
    {
        return in_array($this->errorCode, [
            self::ERROR_CONNECTION_FAILED,
            self::ERROR_REQUEST_TIMEOUT,
            self::ERROR_SERVICE_UNAVAILABLE,
            self::ERROR_RATE_LIMIT_EXCEEDED
        ], true);
    }

    /**
     * Get suggested wait time in seconds
     *
     * @return int
     */
    public function getSuggestedWaitTime(): int
    {
        return match($this->errorCode) {
            self::ERROR_RATE_LIMIT_EXCEEDED => 60,
            self::ERROR_SERVICE_UNAVAILABLE => 30,
            self::ERROR_REQUEST_TIMEOUT => 10,
            default => 5
        };
    }
}
