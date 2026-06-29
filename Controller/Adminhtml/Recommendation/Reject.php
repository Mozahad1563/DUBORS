<?php
declare(strict_types=1);

namespace BrainStation23\Dubors\Controller\Adminhtml\Recommendation;

use BrainStation23\Dubors\Model\RecommendationRepository;
use BrainStation23\Dubors\Service\MlServiceClient;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Psr\Log\LoggerInterface;

class Reject extends Action
{
    public const ADMIN_RESOURCE = 'BrainStation23_Dubors::recommendations';

    public function __construct(
        Context $context,
        private readonly RecommendationRepository $recommendationRepository,
        private readonly MlServiceClient $mlClient,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $id = $this->getRequest()->getParam('id');
        $feedback = trim((string)$this->getRequest()->getParam('rejection_feedback', ''));

        try {
            $recommendation = $this->recommendationRepository->getById((int)$id);

            $recommendation->setStatus('rejected');
            if ($feedback) {
                $recommendation->setRejectionFeedback($feedback);
            }
            $this->recommendationRepository->save($recommendation);

            try {
                $this->mlClient->sendFeedback(
                    (string)$recommendation->getEntityId(),
                    (int)$recommendation->getCustomerId(),
                    (float)$recommendation->getDiscountPercent(),
                    false
                );
            } catch (\Exception $e) {
                $this->logger->warning('ML feedback failed on reject', ['error' => $e->getMessage()]);
            }

            $this->messageManager->addSuccessMessage(
                __('Recommendation #%1 rejected. Feedback sent to AI.', $id)
            );
        } catch (NoSuchEntityException $e) {
            $this->messageManager->addErrorMessage(
                __('Recommendation #%1 does not exist.', $id)
            );
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(
                __('An error occurred while rejecting the recommendation.')
            );
        }

        return $resultRedirect->setPath('*/*/');
    }
}
