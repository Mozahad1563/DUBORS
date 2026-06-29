<?php

namespace BrainStation23\Dubors\Controller\Adminhtml\Reports;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use BrainStation23\Dubors\Service\Analytics\ReportBuilder;

/**
 * Report Builder Controller
 * 
 * Handles custom report creation and generation
 */
class Generate extends Action
{
    /**
     * Authorization level of a basic admin session
     */
    const ADMIN_RESOURCE = 'BrainStation23_Dubors::reports';

    /**
     * @var JsonFactory
     */
    private JsonFactory $resultJsonFactory;

    /**
     * @var ReportBuilder
     */
    private ReportBuilder $reportBuilder;

    /**
     * Generate constructor
     *
     * @param Context $context
     * @param JsonFactory $resultJsonFactory
     * @param ReportBuilder $reportBuilder
     */
    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        ReportBuilder $reportBuilder
    ) {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->reportBuilder = $reportBuilder;
    }

    /**
     * Execute action
     *
     * @return \Magento\Framework\Controller\Result\Json
     */
    public function execute()
    {
        $resultJson = $this->resultJsonFactory->create();

        try {
            $params = $this->getRequest()->getParams();
            $reportId = (int)$params['report_id'] ?? 0;

            if ($reportId == 0) {
                // Create new report
                $reportId = $this->reportBuilder->createReport([
                    'name' => $params['name'],
                    'description' => $params['description'] ?? '',
                    'metrics' => explode(',', $params['metrics'] ?? ''),
                    'dimensions' => explode(',', $params['dimensions'] ?? ''),
                    'period_from' => $params['period_from'] ?? date('Y-m-d', strtotime('-30 days')),
                    'period_to' => $params['period_to'] ?? date('Y-m-d')
                ]);

                if (!$reportId) {
                    return $resultJson->setData([
                        'success' => false,
                        'message' => 'Failed to create report'
                    ]);
                }
            }

            // Generate report data
            $report = $this->reportBuilder->generateReport($reportId);

            // Save run history
            $this->reportBuilder->saveReportRun($reportId, count($report['data'] ?? []));

            return $resultJson->setData([
                'success' => true,
                'message' => 'Report generated successfully',
                'report' => $report
            ]);
        } catch (\Exception $e) {
            return $resultJson->setData([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }
}
