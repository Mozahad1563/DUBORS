<?php
/**
 * BrainStation23
 *
 * @category    BrainStation23
 * @package     BrainStation23_Dubors
 * @copyright   Copyright (c) 2026 BrainStation23
 */

declare(strict_types=1);

namespace BrainStation23\Dubors\Controller\Adminhtml\System;

use BrainStation23\Dubors\Service\RecommendationEngine;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;

class ServiceHealth extends Action
{
    public const ADMIN_RESOURCE = 'BrainStation23_Dubors::config';

    /**
     * @param Context $context
     * @param RecommendationEngine $recommendationEngine
     * @param JsonFactory $jsonFactory
     */
    public function __construct(
        Context $context,
        private readonly RecommendationEngine $recommendationEngine,
        private readonly JsonFactory $jsonFactory
    ) {
        parent::__construct($context);
    }

    /**
     * Execute action
     *
     * @return \Magento\Framework\Controller\Result\Json
     */
    public function execute()
    {
        $resultJson = $this->jsonFactory->create();

        try {
            $health = $this->recommendationEngine->checkServiceHealth();
            $resultJson->setData($health);
        } catch (\Exception $e) {
            $resultJson->setData([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }

        return $resultJson;
    }
}
