<?php
/**
 * BrainStation23
 *
 * @category    BrainStation23
 * @package     BrainStation23_Dubors
 * @copyright   Copyright (c) 2026 BrainStation23
 */

namespace BrainStation23\Dubors\Controller\Track;

use BrainStation23\Dubors\Api\Data\UserBehaviorInterface;
use BrainStation23\Dubors\Api\UserBehaviorRepositoryInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\Json as JsonResult;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\ObjectManagerInterface;
use Psr\Log\LoggerInterface;

class Event extends Action
{
    /**
     * @var ObjectManagerInterface
     */
    private $objectManager;

    /**
     * @var UserBehaviorRepositoryInterface
     */
    private $userBehaviorRepository;

    /**
     * @var CustomerSession
     */
    private $customerSession;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * Constructor
     *
     * @param Context $context
     * @param ObjectManagerInterface $objectManager
     * @param UserBehaviorRepositoryInterface $userBehaviorRepository
     * @param CustomerSession $customerSession
     * @param LoggerInterface $logger
     */
    public function __construct(
        Context $context,
        ObjectManagerInterface $objectManager,
        UserBehaviorRepositoryInterface $userBehaviorRepository,
        CustomerSession $customerSession,
        LoggerInterface $logger
    ) {
        parent::__construct($context);
        $this->objectManager = $objectManager;
        $this->userBehaviorRepository = $userBehaviorRepository;
        $this->customerSession = $customerSession;
        $this->logger = $logger;
    }

    /**
     * Track user event
     * AJAX endpoint for frontend tracking
     *
     * POST /dubors/track/event
     * Params:
     *   - event_type: string (product_view, add_to_cart, purchase, etc.)
     *   - product_id: int (optional)
     *   - category_id: int (optional)
     *   - cart_value: float (optional)
     *
     * @return JsonResult
     */
    public function execute()
    {
        /** @var JsonResult $result */
        $result = $this->resultFactory->create(ResultFactory::TYPE_JSON);

        try {
            // Only track for logged-in customers
            if (!$this->customerSession->isLoggedIn()) {
                return $result->setData([
                    'success' => false,
                    'message' => 'Customer not logged in'
                ]);
            }

            $customerId = $this->customerSession->getCustomerId();
            $request = $this->getRequest();

            // Get parameters from request
            $eventType = $request->getParam('event_type');
            $productId = $request->getParam('product_id');
            $categoryId = $request->getParam('category_id');
            $cartValue = $request->getParam('cart_value', 0);

            // Validate required parameter
            if (!$eventType) {
                return $result->setData([
                    'success' => false,
                    'message' => 'Missing required parameter: event_type'
                ]);
            }

            // Sanitize inputs
            $eventType = substr($eventType, 0, 50);
            $productId = $productId ? (int)$productId : null;
            $categoryId = $categoryId ? (int)$categoryId : null;
            $cartValue = (float)$cartValue;

            // Create user behavior record
            $behavior = $this->objectManager->create(UserBehaviorInterface::class);
            $behavior->setCustomerId($customerId)
                ->setEventType($eventType)
                ->setProductId($productId)
                ->setCategoryId($categoryId)
                ->setCartValue($cartValue)
                ->setCreatedAt(date('Y-m-d H:i:s'));

            $this->userBehaviorRepository->save($behavior);

            $this->logger->info(
                'DUBORS: Tracked custom event via API',
                [
                    'customer_id' => $customerId,
                    'event_type' => $eventType,
                    'product_id' => $productId
                ]
            );

            return $result->setData([
                'success' => true,
                'message' => 'Event tracked successfully'
            ]);
        } catch (\Exception $e) {
            $this->logger->error('DUBORS: Error tracking event: ' . $e->getMessage());

            return $result->setData([
                'success' => false,
                'message' => 'Error tracking event'
            ]);
        }
    }

    /**
     * Check if request is allowed for this action
     *
     * @return bool
     */
    protected function _isAllowed()
    {
        return true; // Allow all logged-in users
    }
}
