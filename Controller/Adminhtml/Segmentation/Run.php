<?php
/**
 * BrainStation23 Dubors Extension
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * @category   BrainStation23
 * @package    BrainStation23_Dubors
 * @author     BrainStation23 Team
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace BrainStation23\Dubors\Controller\Adminhtml\Segmentation;

use BrainStation23\Dubors\Service\Segmentation\CustomerSegmentation;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Psr\Log\LoggerInterface;

class Run extends Action
{
    public const ADMIN_RESOURCE = 'BrainStation23_Dubors::segmentation';

    /**
     * @var CustomerSegmentation
     */
    private CustomerSegmentation $segmentation;

    /**
     * @var JsonFactory
     */
    private JsonFactory $resultJsonFactory;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * Run constructor.
     *
     * @param Context $context
     * @param CustomerSegmentation $segmentation
     * @param JsonFactory $resultJsonFactory
     * @param LoggerInterface $logger
     */
    public function __construct(
        Context $context,
        CustomerSegmentation $segmentation,
        JsonFactory $resultJsonFactory,
        LoggerInterface $logger
    ) {
        parent::__construct($context);
        $this->segmentation = $segmentation;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->logger = $logger;
    }

    /**
     * Execute customer segmentation.
     *
     * @return Json
     */
    public function execute(): Json
    {
        $result = $this->resultJsonFactory->create();

        try {
            $segments = $this->segmentation->segmentCustomers();

            $stats = [];
            $total = 0;
            foreach ($segments as $name => $customers) {
                $count = count($customers);
                $stats[$name] = $count;
                $total += $count;
            }

            $this->messageManager->addSuccessMessage(
                sprintf('Segmented %d customers into 5 segments', $total)
            );

            return $result->setData([
                'success' => true,
                'message' => 'Segmentation completed',
                'statistics' => $stats,
                'total' => $total,
            ]);
        } catch (\Exception $e) {
            $this->logger->error(sprintf('Segmentation error: %s', $e->getMessage()));
            $this->messageManager->addErrorMessage('Error during segmentation: ' . $e->getMessage());

            return $result->setData([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
