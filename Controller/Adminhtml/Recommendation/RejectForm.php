<?php
declare(strict_types=1);

namespace BrainStation23\Dubors\Controller\Adminhtml\Recommendation;

use Magento\Backend\App\Action;
use Magento\Framework\View\Result\PageFactory;
use Magento\Backend\App\Action\Context;

class RejectForm extends Action
{
    public const ADMIN_RESOURCE = 'BrainStation23_Dubors::recommendations';

    public function __construct(
        Context $context,
        private readonly PageFactory $resultPageFactory
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $id = $this->getRequest()->getParam('id');
        if (!$id) {
            $this->messageManager->addErrorMessage(__('Recommendation ID is required.'));
            return $this->resultRedirectFactory->create()->setPath('*/*/');
        }

        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('BrainStation23_Dubors::recommendations');
        $resultPage->getConfig()->getTitle()->prepend(__('Reject Recommendation #%1', $id));
        return $resultPage;
    }
}
