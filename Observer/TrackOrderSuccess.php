<?php
/**
 * BrainStation23
 *
 * @category    BrainStation23
 * @package     BrainStation23_Dubors
 * @copyright   Copyright (c) 2026 BrainStation23
 */

namespace BrainStation23\Dubors\Observer;

use BrainStation23\Dubors\Api\Data\UserBehaviorInterface;
use BrainStation23\Dubors\Api\UserBehaviorRepositoryInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\Sales\Model\Order;
use Psr\Log\LoggerInterface;

class TrackOrderSuccess implements ObserverInterface
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
     * @param ObjectManagerInterface $objectManager
     * @param UserBehaviorRepositoryInterface $userBehaviorRepository
     * @param CustomerSession $customerSession
     * @param LoggerInterface $logger
     */
    public function __construct(
        ObjectManagerInterface $objectManager,
        UserBehaviorRepositoryInterface $userBehaviorRepository,
        CustomerSession $customerSession,
        LoggerInterface $logger
    ) {
        $this->objectManager = $objectManager;
        $this->userBehaviorRepository = $userBehaviorRepository;
        $this->customerSession = $customerSession;
        $this->logger = $logger;
    }

    /**
     * Track order success event
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer)
    {
        try {
            // Get order from session or event
            if ($this->customerSession->isLoggedIn()) {
                $customerId = $this->customerSession->getCustomerId();
            } else {
                return; // Only track registered customers
            }

            // Get order from session or event
            $lastOrderId = $this->customerSession->getLastOrderId();
            if (!$lastOrderId) {
                return;
            }

            // Track each item in the order
            /** @var Order $order */
            $orderFactory = $this->objectManager->get('Magento\Sales\Model\OrderFactory');
            $order = $orderFactory->create()->load($lastOrderId);

            if (!$order || !$order->getId()) {
                return;
            }

            $totalValue = (float)$order->getGrandTotal();

            // Track purchase event with order total
            $behavior = $this->objectManager->create(UserBehaviorInterface::class);
            $behavior->setCustomerId($customerId)
                ->setEventType('purchase')
                ->setProductId(null)
                ->setCategoryId(null)
                ->setCartValue($totalValue)
                ->setCreatedAt(date('Y-m-d H:i:s'));

            $this->userBehaviorRepository->save($behavior);

            // Track individual items
            foreach ($order->getItems() as $item) {
                $itemBehavior = $this->objectManager->create(UserBehaviorInterface::class);
                $itemBehavior->setCustomerId($customerId)
                    ->setEventType('purchase_item')
                    ->setProductId($item->getProductId())
                    ->setCategoryId(null)
                    ->setCartValue((float)$item->getRowTotal())
                    ->setCreatedAt(date('Y-m-d H:i:s'));

                $this->userBehaviorRepository->save($itemBehavior);
            }

            $this->logger->info(
                'DUBORS: Tracked order success',
                [
                    'customer_id' => $customerId,
                    'order_id' => $lastOrderId,
                    'total' => $totalValue
                ]
            );
        } catch (\Exception $e) {
            $this->logger->error('DUBORS: Error tracking order success: ' . $e->getMessage());
        }
    }
}
