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

use BrainStation23\Dubors\Model\RecommendationRepository;
use BrainStation23\Dubors\Service\MlServiceClient;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Psr\Log\LoggerInterface;

class MoveToPending extends Action
{
    public const ADMIN_RESOURCE = 'BrainStation23_Dubors::recommendations';

    /**
     * @param Context $context
     * @param RecommendationRepository $recommendationRepository
     * @param MlServiceClient $mlClient
     * @param LoggerInterface $logger
     */
    public function __construct(
        Context $context,
        private readonly RecommendationRepository $recommendationRepository,
        private readonly MlServiceClient $mlClient,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct($context);
    }

    /**
     * Move an approved/redeemed recommendation back to pending and clear coupon
     *
     * @return \Magento\Framework\Controller\Result\Redirect
     */
    public function execute()
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $id = $this->getRequest()->getParam('id');

        try {
            $recommendation = $this->recommendationRepository->getById((int)$id);

            // Change status back to pending, clear coupon and approval time
            $recommendation->setStatus('pending');
            $recommendation->setCouponCode(null);
            $recommendation->setApprovedAt(null);
            $this->recommendationRepository->save($recommendation);

            // Send negative feedback to the ML model (since it is rejected from the accepted list)
            try {
                $this->mlClient->sendFeedback(
                    (string)$recommendation->getEntityId(),
                    (int)$recommendation->getCustomerId(),
                    (float)$recommendation->getDiscountPercent(),
                    false
                );
            } catch (\Exception $e) {
                $this->logger->warning('ML feedback failed on move to pending', ['error' => $e->getMessage()]);
            }

            $this->messageManager->addSuccessMessage(
                __('Recommendation #%1 has been moved back to the pending list, and its coupon has been removed.', $id)
            );
        } catch (NoSuchEntityException $e) {
            $this->messageManager->addErrorMessage(
                __('Recommendation #%1 does not exist.', $id)
            );
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(
                __('An error occurred while moving the recommendation to pending.')
            );
        }

        return $resultRedirect->setUrl($this->_redirect->getRefererUrl());
    }
}
