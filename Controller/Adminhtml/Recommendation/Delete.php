<?php
declare(strict_types=1);

namespace BrainStation23\Dubors\Controller\Adminhtml\Recommendation;

use BrainStation23\Dubors\Model\RecommendationRepository;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;

class Delete extends Action
{
    public const ADMIN_RESOURCE = 'BrainStation23_Dubors::recommendations';

    public function __construct(
        Context $context,
        private readonly RecommendationRepository $recommendationRepository
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $id = $this->getRequest()->getParam('id');

        try {
            $this->recommendationRepository->deleteById((int)$id);
            $this->messageManager->addSuccessMessage(
                __('Recommendation #%1 has been deleted.', $id)
            );
        } catch (NoSuchEntityException $e) {
            $this->messageManager->addErrorMessage(
                __('Recommendation #%1 does not exist.', $id)
            );
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(
                __('An error occurred while deleting the recommendation.')
            );
        }

        return $resultRedirect->setUrl($this->_redirect->getRefererUrl());
    }
}
