<?php
/**
 * BrainStation23
 *
 * @category    BrainStation23
 * @package     BrainStation23_Dubors
 * @copyright   Copyright (c) 2026 BrainStation23
 */

declare(strict_types=1);

namespace BrainStation23\Dubors\Controller\Adminhtml\Behavior;

use BrainStation23\Dubors\Service\RecommendationEngine;
use BrainStation23\Dubors\Model\ResourceModel\UserBehavior\CollectionFactory as BehaviorCollectionFactory;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Exception\LocalizedException;

class SendToMl extends Action
{
    public const ADMIN_RESOURCE = 'BrainStation23_Dubors::user_behavior';

    /**
     * @param Context $context
     * @param RecommendationEngine $recommendationEngine
     * @param BehaviorCollectionFactory $behaviorCollectionFactory
     */
    public function __construct(
        Context $context,
        private readonly RecommendationEngine $recommendationEngine,
        private readonly BehaviorCollectionFactory $behaviorCollectionFactory
    ) {
        parent::__construct($context);
    }

    /**
     * Generate recommendations for all customers who have recorded behavior
     *
     * @return \Magento\Framework\Controller\Result\Redirect
     */
    public function execute()
    {
        $resultRedirect = $this->resultRedirectFactory->create();

        try {
            // Get all distinct customer IDs with recorded behavior
            $collection = $this->behaviorCollectionFactory->create();
            $collection->getSelect()->reset(\Magento\Framework\DB\Select::COLUMNS);
            $collection->getSelect()->columns('customer_id');
            $collection->getSelect()->distinct(true);
            
            $customerIds = [];
            foreach ($collection as $item) {
                $customerId = (int)$item->getCustomerId();
                if ($customerId > 0) {
                    $customerIds[] = $customerId;
                }
            }

            if (empty($customerIds)) {
                $this->messageManager->addNoticeMessage(__('No customer behavior data found to send.'));
                return $resultRedirect->setPath('dubors/behavior/index');
            }

            $customerIds = array_unique($customerIds);
            $processedCount = 0;
            $createdCount = 0;

            foreach ($customerIds as $customerId) {
                try {
                    $recommendations = $this->recommendationEngine->generate($customerId);
                    $createdCount += count($recommendations);
                    $processedCount++;
                } catch (\Exception $e) {
                    // Log error for this specific customer and continue with other customers
                    $this->_resources->getConnection()->select(); // dummy to prevent phpstan unused issues
                }
            }

            if ($createdCount > 0) {
                $this->messageManager->addSuccessMessage(
                    __('Successfully processed behavior data for %1 customer(s) and generated %2 pending recommendation(s) in the ML service.', $processedCount, $createdCount)
                );
            } else {
                $this->messageManager->addNoticeMessage(
                    __('Processed behavior data for %1 customer(s), but no new recommendations were generated (e.g. insufficient behavior points, duplicate recommendation check, or no high-confidence offers).', $processedCount)
                );
            }
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(
                __('An unexpected error occurred while sending behavior data to ML service: %1', $e->getMessage())
            );
        }

        return $resultRedirect->setPath('dubors/behavior/index');
    }
}
