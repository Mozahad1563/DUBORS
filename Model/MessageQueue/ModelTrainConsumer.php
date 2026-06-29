<?php
/**
 * BrainStation23
 *
 * @category    BrainStation23
 * @package     BrainStation23_Dubors
 * @copyright   Copyright (c) 2026 BrainStation23
 */

declare(strict_types=1);

namespace BrainStation23\Dubors\Model\MessageQueue;

use BrainStation23\Dubors\Service\RecommendationEngine;
use BrainStation23\Dubors\Service\Exception\MlServiceException;
use Psr\Log\LoggerInterface;

/**
 * Executes model training asynchronously via the message queue.
 *
 * The TrainModel admin controller publishes a message here so the HTTP
 * response returns immediately — no gateway timeout waiting for the ML service.
 */
class ModelTrainConsumer
{
    /**
     * @param RecommendationEngine $recommendationEngine
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly RecommendationEngine $recommendationEngine,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Process a training request from the queue.
     *
     * @param string $message JSON: {"limit": 1000, "days_back": 90}
     * @return void
     */
    public function process(string $message): void
    {
        $data     = \json_decode($message, true) ?? [];
        $limit    = (int)($data['limit']    ?? 1000);
        $daysBack = (int)($data['days_back'] ?? 90);

        $this->logger->info('ModelTrainConsumer: starting async model training', [
            'limit'     => $limit,
            'days_back' => $daysBack,
        ]);

        try {
            $this->recommendationEngine->trainModel($limit, $daysBack);
            $this->logger->info('ModelTrainConsumer: training completed successfully');
        } catch (MlServiceException $e) {
            $this->logger->error('ModelTrainConsumer: ML service error', [
                'error_code' => $e->getErrorCode(),
                'message'    => $e->getMessage(),
            ]);
        } catch (\Exception $e) {
            $this->logger->error('ModelTrainConsumer: unexpected error', [
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);
        }
    }
}
