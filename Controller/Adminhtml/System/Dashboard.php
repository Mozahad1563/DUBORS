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

use Magento\Backend\App\Action;
use Magento\Framework\View\Result\PageFactory;

class Dashboard extends Action
{
    public const ADMIN_RESOURCE = 'BrainStation23_Dubors::config';

    /**
     * @param Action\Context $context
     * @param PageFactory $resultPageFactory
     */
    public function __construct(
        Action\Context $context,
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
        $resultPage->setActiveMenu('BrainStation23_Dubors::config');
        $resultPage->getConfig()->getTitle()->prepend(__('DUBORS ML Service Dashboard'));

        return $resultPage;
    }
}
