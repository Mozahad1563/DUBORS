<?php

namespace BrainStation23\Dubors\Controller\Adminhtml\Analytics;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;
use BrainStation23\Dubors\Service\Analytics\KpiCalculator;

/**
 * Analytics Dashboard Controller
 * 
 * Displays KPI metrics and performance data
 */
class Dashboard extends Action
{
    /**
     * Authorization level of a basic admin session
     */
    const ADMIN_RESOURCE = 'BrainStation23_Dubors::analytics';

    /**
     * @var PageFactory
     */
    private PageFactory $resultPageFactory;

    /**
     * @var KpiCalculator
     */
    private KpiCalculator $kpiCalculator;

    /**
     * Dashboard constructor
     *
     * @param Context $context
     * @param PageFactory $resultPageFactory
     * @param KpiCalculator $kpiCalculator
     */
    public function __construct(
        Context $context,
        PageFactory $resultPageFactory,
        KpiCalculator $kpiCalculator
    ) {
        parent::__construct($context);
        $this->resultPageFactory = $resultPageFactory;
        $this->kpiCalculator = $kpiCalculator;
    }

    /**
     * Execute action
     *
     * @return \Magento\Framework\View\Result\Page
     */
    public function execute()
    {
        try {
            // Get date range from request
            $from = $this->getRequest()->getParam('from', date('Y-m-d', strtotime('-30 days')));
            $to = $this->getRequest()->getParam('to', date('Y-m-d'));

            // Calculate KPIs
            $kpis = $this->kpiCalculator->calculateOverallKpis([
                'period_from' => $from,
                'period_to' => $to
            ]);

            // Get trends
            $trends = $this->kpiCalculator->getDailyTrends($from, $to);

            // Get KPIs by segment
            $segmentKpis = $this->kpiCalculator->getKpisBySegment($from, $to);

            // Store data in session for display
            $this->_getSession()->setDashboardData([
                'kpis' => $kpis,
                'trends' => $trends,
                'segment_kpis' => $segmentKpis,
                'period_from' => $from,
                'period_to' => $to
            ]);

            $resultPage = $this->resultPageFactory->create();
            $resultPage->getConfig()->getTitle()->prepend(__('Analytics Dashboard'));
            return $resultPage;
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
            return $this->resultRedirectFactory->create()->setPath('*/*/');
        }
    }
}
