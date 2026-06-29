<?php
declare(strict_types=1);

namespace BrainStation23\Dubors\Cron;

use BrainStation23\Dubors\Helper\Config;
use BrainStation23\Dubors\Service\RecommendationEngine;
use Psr\Log\LoggerInterface;

class WeeklyModelRetrain
{
    public function __construct(
        private readonly Config $config,
        private readonly RecommendationEngine $recommendationEngine,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(): void
    {
        if (!$this->config->isMlServiceEnabled()) {
            return;
        }

        try {
            $this->logger->info('DUBORS: Starting weekly automated model retrain');
            $this->recommendationEngine->trainModel(2000, 90);
            $this->logger->info('DUBORS: Weekly retrain completed successfully');
        } catch (\Exception $e) {
            $this->logger->error('DUBORS: Weekly retrain failed', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
