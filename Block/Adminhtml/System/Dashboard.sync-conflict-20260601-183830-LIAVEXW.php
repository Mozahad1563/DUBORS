<?php
/**
 * BrainStation23
 *
 * @category    BrainStation23
 * @package     BrainStation23_Dubors
 * @copyright   Copyright (c) 2026 BrainStation23
 */

declare(strict_types=1);

namespace BrainStation23\Dubors\Block\Adminhtml\System;

use BrainStation23\Dubors\Service\RecommendationEngine;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;

class Dashboard extends Template
{
    /**
     * @param Context $context
     * @param RecommendationEngine $recommendationEngine
     * @array $data
     */
    public function __construct(
        Context $context,
        private readonly RecommendationEngine $recommendationEngine,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Get service health data
     *
     * @return array
     */
    public function getServiceHealth(): array
    {
        return $this->recommendationEngine->checkServiceHealth();
    }

    /**
     * Get health check URL
     *
     * @return string
     */
    public function getHealthCheckUrl(): string
    {
        return $this->getUrl('dubors/system/serviceHealth');
    }
}
