<?php
/**
 * BrainStation23
 *
 * @category    BrainStation23
 * @package     BrainStation23_Dubors
 * @copyright   Copyright (c) 2026 BrainStation23
 */

declare(strict_types=1);

namespace BrainStation23\Dubors\Model\Resolver;

use BrainStation23\Dubors\Api\Data\RecommendationInterface;
use BrainStation23\Dubors\Model\ResourceModel\Recommendation\CollectionFactory;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlAuthorizationException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;

/**
 * Resolver for personalized offer query
 */
class PersonalizedOffer implements ResolverInterface
{
    /**
     * @param CollectionFactory $recommendationCollectionFactory
     */
    public function __construct(
        private readonly CollectionFactory $recommendationCollectionFactory
    ) {
    }

    /**
     * @inheritdoc
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        ?array $value = null,
        ?array $args = null
    ) {
        $customerId = $context->getUserId();

        // Check if customer is logged in
        if (!$customerId || $context->getUserType() !== \Magento\Authorization\Model\UserContextInterface::USER_TYPE_CUSTOMER) {
            return null;
        }

        $recommendation = $this->getLatestApprovedRecommendation((int)$customerId);
        if (!$recommendation) {
            return null;
        }

        return [
            'id' => $recommendation->getEntityId(),
            'coupon_code' => $recommendation->getCouponCode(),
            'discount_percent' => $recommendation->getDiscountPercent(),
            'recommendation_type' => $recommendation->getRecommendationType(),
            'confidence_score' => $recommendation->getConfidenceScore(),
            'status' => $recommendation->getStatus(),
            'expires_at' => $recommendation->getExpiresAt(),
            'created_at' => $recommendation->getCreatedAt(),
            'updated_at' => $recommendation->getUpdatedAt()
        ];
    }

    /**
     * Get latest approved recommendation for customer
     *
     * @param int $customerId
     * @return RecommendationInterface|null
     */
    private function getLatestApprovedRecommendation(int $customerId): ?RecommendationInterface
    {
        $collection = $this->recommendationCollectionFactory->create();
        $collection->addFieldToFilter('customer_id', $customerId);
        $collection->addFieldToFilter('status', RecommendationInterface::STATUS_APPROVED);
        $collection->addFieldToFilter('coupon_code', ['null' => false]);
        $collection->setOrder('created_at', 'DESC');
        $collection->setPageSize(1);

        /** @var RecommendationInterface $recommendation */
        $recommendation = $collection->getFirstItem();
        
        return $recommendation->getId() ? $recommendation : null;
    }
}
