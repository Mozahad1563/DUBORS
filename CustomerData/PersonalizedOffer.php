<?php
declare(strict_types=1);

namespace BrainStation23\Dubors\CustomerData;

use BrainStation23\Dubors\Api\Data\RecommendationInterface;
use BrainStation23\Dubors\Model\ResourceModel\Recommendation\CollectionFactory;
use Magento\Customer\CustomerData\SectionSourceInterface;
use Magento\Customer\Model\Session;
use Magento\Framework\DataObject;

class PersonalizedOffer extends DataObject implements SectionSourceInterface
{
    public function __construct(
        private readonly Session $customerSession,
        private readonly CollectionFactory $recommendationCollectionFactory,
        array $data = []
    ) {
        parent::__construct($data);
    }

    public function getSectionData(): array
    {
        $customerId = (int)$this->customerSession->getCustomerId();
        if (!$customerId) {
            return ['has_offer' => false, 'offers' => []];
        }

        $offers = $this->getApprovedOffers($customerId);
        if (empty($offers)) {
            return ['has_offer' => false, 'offers' => []];
        }

        $first = reset($offers);
        return [
            'has_offer' => true,
            'coupon_code' => $first['coupon_code'],
            'discount_percent' => $first['discount_percent'],
            'product_id' => $first['product_id'],
            'expires_at' => $first['expires_at'],
            'recommendation_id' => $first['recommendation_id'],
            'offers' => $offers,
            'offer_count' => count($offers),
        ];
    }

    private function getApprovedOffers(int $customerId): array
    {
        $collection = $this->recommendationCollectionFactory->create();
        $collection->addFieldToFilter('customer_id', $customerId);
        $collection->addFieldToFilter('status', RecommendationInterface::STATUS_APPROVED);
        $collection->addFieldToFilter('coupon_code', ['notnull' => true]);
        $collection->addFieldToFilter(
            ['expires_at', 'expires_at'],
            [['gteq' => date('Y-m-d H:i:s')], ['null' => true]]
        );
        $collection->setOrder('created_at', 'DESC');
        $collection->setPageSize(10);

        $offers = [];
        foreach ($collection as $rec) {
            $offers[] = [
                'recommendation_id' => (int)$rec->getEntityId(),
                'product_id' => (int)$rec->getProductId(),
                'coupon_code' => $rec->getCouponCode(),
                'discount_percent' => (float)$rec->getDiscountPercent(),
                'expires_at' => $rec->getExpiresAt(),
            ];
        }
        return $offers;
    }
}
