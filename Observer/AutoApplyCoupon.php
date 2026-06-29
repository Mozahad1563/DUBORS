<?php
declare(strict_types=1);

namespace BrainStation23\Dubors\Observer;

use BrainStation23\Dubors\Api\Data\RecommendationInterface;
use BrainStation23\Dubors\Model\ResourceModel\Recommendation\CollectionFactory;
use Magento\Customer\Model\Session;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Psr\Log\LoggerInterface;

class AutoApplyCoupon implements ObserverInterface
{
    public function __construct(
        private readonly Session $customerSession,
        private readonly CollectionFactory $recommendationCollectionFactory,
        private readonly CartRepositoryInterface $cartRepository,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(Observer $observer): void
    {
        $customerId = (int)$this->customerSession->getCustomerId();
        if (!$customerId) {
            return;
        }

        /** @var \Magento\Quote\Model\Quote\Item $quoteItem */
        $quoteItem = $observer->getEvent()->getData('quote_item');
        if (!$quoteItem) {
            return;
        }

        $productId = (int)$quoteItem->getProductId();
        $quote = $quoteItem->getQuote();

        if ($quote->getCouponCode()) {
            return;
        }

        $coupon = $this->findApprovedCoupon($customerId, $productId);
        if (!$coupon) {
            return;
        }

        try {
            $quote->setCouponCode($coupon)->collectTotals();
            $this->cartRepository->save($quote);
            $this->logger->info('Auto-applied DUBORS coupon', [
                'customer_id' => $customerId,
                'product_id' => $productId,
                'coupon' => $coupon,
            ]);
        } catch (\Exception $e) {
            $this->logger->warning('Failed to auto-apply DUBORS coupon', [
                'coupon' => $coupon,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function findApprovedCoupon(int $customerId, int $productId): ?string
    {
        $collection = $this->recommendationCollectionFactory->create();
        $collection->addFieldToFilter('customer_id', $customerId);
        $collection->addFieldToFilter('product_id', $productId);
        $collection->addFieldToFilter('status', RecommendationInterface::STATUS_APPROVED);
        $collection->addFieldToFilter('coupon_code', ['notnull' => true]);
        $collection->addFieldToFilter(
            ['expires_at', 'expires_at'],
            [['gteq' => date('Y-m-d H:i:s')], ['null' => true]]
        );
        $collection->setOrder('discount_percent', 'DESC');
        $collection->setPageSize(1);

        $recommendation = $collection->getFirstItem();
        return $recommendation->getId() ? $recommendation->getCouponCode() : null;
    }
}
