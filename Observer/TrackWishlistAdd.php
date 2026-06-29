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
use Psr\Log\LoggerInterface;

class TrackWishlistAdd implements ObserverInterface
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
     * Track wishlist add event
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer)
    {
        try {
            // Only track for logged-in customers
            if (!$this->customerSession->isLoggedIn()) {
                return;
            }

            $customerId = $this->customerSession->getCustomerId();

            // Get product from event
            $product = $observer->getEvent()->getProduct();

            if (!$product || !$product->getId()) {
                return;
            }

            // Create user behavior record
            $behavior = $this->objectManager->create(UserBehaviorInterface::class);
            $behavior->setCustomerId($customerId)
                ->setEventType('wishlist_add')
                ->setProductId($product->getId())
                ->setCategoryId($this->getPrimaryCategory($product))
                ->setCartValue((float)$product->getPrice())
                ->setCreatedAt(date('Y-m-d H:i:s'));

            $this->userBehaviorRepository->save($behavior);

            $this->logger->info(
                'DUBORS: Tracked wishlist add',
                [
                    'customer_id' => $customerId,
                    'product_id' => $product->getId()
                ]
            );
        } catch (\Exception $e) {
            $this->logger->error('DUBORS: Error tracking wishlist add: ' . $e->getMessage());
        }
    }

    /**
     * Get primary category ID from product
     *
     * @param \Magento\Catalog\Model\Product $product
     * @return int|null
     */
    private function getPrimaryCategory($product)
    {
        $categoryIds = $product->getCategoryIds();
        return !empty($categoryIds) ? (int)$categoryIds[0] : null;
    }
}
