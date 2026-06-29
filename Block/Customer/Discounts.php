<?php
declare(strict_types=1);

namespace BrainStation23\Dubors\Block\Customer;

use BrainStation23\Dubors\Api\Data\RecommendationInterface;
use BrainStation23\Dubors\Model\ResourceModel\Recommendation\CollectionFactory;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Customer\Model\Session;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;

class Discounts extends Template
{
    public function __construct(
        Context $context,
        private readonly Session $customerSession,
        private readonly CollectionFactory $recommendationCollectionFactory,
        private readonly ProductRepositoryInterface $productRepository,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * @return RecommendationInterface[]
     */
    public function getApprovedDiscounts(): array
    {
        $customerId = (int)$this->customerSession->getCustomerId();
        if (!$customerId) {
            return [];
        }

        $collection = $this->recommendationCollectionFactory->create();
        $collection->addFieldToFilter('customer_id', $customerId);
        $collection->addFieldToFilter('status', RecommendationInterface::STATUS_APPROVED);
        $collection->addFieldToFilter('coupon_code', ['notnull' => true]);
        $collection->setOrder('created_at', 'DESC');

        return $collection->getItems();
    }

    public function getProductName(int $productId): string
    {
        if ($productId <= 0) {
            return (string)__('Any Product');
        }
        try {
            $product = $this->productRepository->getById($productId);
            return $product->getName();
        } catch (\Exception $e) {
            return (string)__('Product #%1', $productId);
        }
    }

    public function getProductUrl(int $productId): string
    {
        if ($productId <= 0) {
            return '';
        }
        try {
            $product = $this->productRepository->getById($productId);
            return $product->getProductUrl();
        } catch (\Exception $e) {
            return '';
        }
    }

    public function isExpired(?string $expiresAt = null): bool
    {
        if (!$expiresAt) {
            return false;
        }
        return strtotime($expiresAt) < time();
    }
}
