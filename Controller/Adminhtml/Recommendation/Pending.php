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

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;

class Pending extends Action
{
    public const ADMIN_RESOURCE = 'BrainStation23_Dubors::recommendations';

    /**
     * @param Context $context
     * @param PageFactory $resultPageFactory
     */
    public function __construct(
        Context $context,
        private readonly PageFactory $resultPageFactory
    ) {
        parent::__construct($context);
    }

    /**
     * Execute action
     *
     * @return \Magento\Framework\View\Result\Page
     */
    public function execute()
    {
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('BrainStation23_Dubors::recommendations');
        $resultPage->addBreadcrumb(__('DUBORS'), __('DUBORS'));
        $resultPage->addBreadcrumb(__('Pending Recommendations'), __('Pending Recommendations'));
        $resultPage->getConfig()->getTitle()->prepend(__('Pending Recommendations'));

        return $resultPage;
    }
}
