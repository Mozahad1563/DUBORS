<?php
/**
 * BrainStation23
 *
 * @category    BrainStation23
 * @package     BrainStation23_Dubors
 * @copyright   Copyright (c) 2026 BrainStation23
 */

declare(strict_types=1);

namespace BrainStation23\Dubors\Controller\Adminhtml\Recommendation;

use BrainStation23\Dubors\Service\RecommendationEngine;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Exception\LocalizedException;

class TrainModel extends Action
{
    public const ADMIN_RESOURCE = 'BrainStation23_Dubors::recommendations';

    /**
     * @param Context $context
     * @param RecommendationEngine $recommendationEngine
     */
    public function __construct(
        Context $context,
        private readonly RecommendationEngine $recommendationEngine
    ) {
        parent::__construct($context);
    }

    /**
     * @return \Magento\Framework\Controller\Result\Redirect
     */
    public function execute()
    {
        $resultRedirect = $this->resultRedirectFactory->create();

        try {
            $this->recommendationEngine->queueTraining();
            $this->messageManager->addSuccessMessage(
                __('Model training has been queued. Results will appear in the ML dashboard once complete.')
            );
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(
                __('An unexpected error occurred while queuing model training: %1', $e->getMessage())
            );
        }

        return $resultRedirect->setPath('*/*/');
    }
}
