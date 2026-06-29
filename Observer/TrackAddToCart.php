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
use Magento\Catalog\Model\ProductRepository;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\ObjectManagerInterface;
use Psr\Log\LoggerInterface;

class TrackAddToCart implements ObserverInterface
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
     * @var ProductRepository
     */
    private $productRepository;

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
     * @param ProductRepository $productRepository
     * @param LoggerInterface $logger
     */
    public function __construct(
        ObjectManagerInterface $objectManager,
        UserBehaviorRepositoryInterface $userBehaviorRepository,
        CustomerSession $customerSession,
        ProductRepository $productRepository,
        LoggerInterface $logger
    ) {
        $this->objectManager = $objectManager;
        $this->userBehaviorRepository = $userBehaviorRepository;
        $this->customerSession = $customerSession;
        $this->productRepository = $productRepository;
        $this->logger = $logger;
    }

    /**
     * Track add to cart event
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

            // Get product and quantity from event
            $product = $observer->getEvent()->getProduct();
            $quoteItem = $observer->getEvent()->getQuoteItem();

            if (!$product || !$product->getId()) {
                return;
            }

            $qty = $quoteItem ? $quoteItem->getQty() : 1;
            $price = $product->getPrice() ?: 0;
            $cartValue = (float)$price * (float)$qty;

            // Create user behavior record
            $behavior = $this->objectManager->create(UserBehaviorInterface::class);
            $behavior->setCustomerId($customerId)
                ->setEventType('add_to_cart')
                ->setProductId($product->getId())
                ->setCategoryId($this->getPrimaryCategory($product))
                ->setCartValue($cartValue)
                ->setCreatedAt(date('Y-m-d H:i:s'));

            $this->userBehaviorRepository->save($behavior);

            $this->logger->info(
                'DUBORS: Tracked add to cart',
                [
                    'customer_id' => $customerId,
                    'product_id' => $product->getId(),
                    'qty' => $qty,
                    'value' => $cartValue
                ]
            );
        } catch (\Exception $e) {
            $this->logger->error('DUBORS: Error tracking add to cart: ' . $e->getMessage());
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
