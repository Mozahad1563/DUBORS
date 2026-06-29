<?php
declare(strict_types=1);

namespace BrainStation23\Dubors\Block\Product;

use BrainStation23\Dubors\Api\Data\RecommendationInterface;
use BrainStation23\Dubors\Model\ResourceModel\Recommendation\CollectionFactory;
use Magento\Customer\Model\Session;
use Magento\Framework\Registry;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;

class DiscountBadge extends Template
{
    public function __construct(
        Context $context,
        private readonly Session $customerSession,
        private readonly CollectionFactory $recommendationCollectionFactory,
        private readonly Registry $registry,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getActiveOffer(): ?RecommendationInterface
    {
        $customerId = (int)$this->customerSession->getCustomerId();
        $product = $this->registry->registry('current_product');

        if (!$customerId || !$product) {
            return null;
        }

        $productId = (int)$product->getId();

        $collection = $this->recommendationCollectionFactory->create();
        $collection->addFieldToFilter('customer_id', $customerId);
        $collection->addFieldToFilter('product_id', $productId);
        $collection->addFieldToFilter('status', RecommendationInterface::STATUS_APPROVED);
        $collection->addFieldToFilter('coupon_code', ['notnull' => true]);
        $collection->addFieldToFilter(
            ['expires_at', 'expires_at'],
            [['gteq' => date('Y-m-d H:i:s')], ['null' => true]]
        );
        $collection->setOrder('created_at', 'DESC');
        $collection->setPageSize(1);

        /** @var RecommendationInterface $recommendation */
        $recommendation = $collection->getFirstItem();
        return $recommendation->getId() ? $recommendation : null;
    }
}
