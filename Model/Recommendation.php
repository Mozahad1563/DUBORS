<?php
/**
 * BrainStation23
 *
 * @category    BrainStation23
 * @package     BrainStation23_Dubors
 * @copyright   Copyright (c) 2026 BrainStation23
 */

namespace BrainStation23\Dubors\Model;

use BrainStation23\Dubors\Api\Data\RecommendationInterface;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb as DbAbstractModel;

class Recommendation extends AbstractModel implements RecommendationInterface
{
    protected $_idFieldName = 'entity_id';

    protected function _construct()
    {
        $this->_init(ResourceModel\Recommendation::class);
    }

    /**
     * {@inheritdoc}
     */
    public function getId()
    {
        return $this->getData('entity_id');
    }

    /**
     * {@inheritdoc}
     */
    public function setId($id)
    {
        return $this->setData('entity_id', $id);
    }

    public function getEntityId()
    {
        return $this->getId();
    }

    public function setEntityId($entityId)
    {
        return $this->setId($entityId);
    }

    /**
     * {@inheritdoc}
     */
    public function getCustomerId()
    {
        return $this->getData('customer_id');
    }

    /**
     * {@inheritdoc}
     */
    public function setCustomerId($customerId)
    {
        return $this->setData('customer_id', $customerId);
    }

    public function getProductId()
    {
        return $this->getData('product_id');
    }

    public function setProductId($productId)
    {
        return $this->setData('product_id', $productId);
    }

    public function getRecommendationType()
    {
        return $this->getData('recommendation_type');
    }

    public function setRecommendationType($recommendationType)
    {
        return $this->setData('recommendation_type', $recommendationType);
    }

    /**
     * {@inheritdoc}
     */
    public function getCouponCode()
    {
        return $this->getData('coupon_code');
    }

    /**
     * {@inheritdoc}
     */
    public function setCouponCode($couponCode)
    {
        return $this->setData('coupon_code', $couponCode);
    }

    /**
     * {@inheritdoc}
     */
    public function getDiscountPercent()
    {
        return $this->getData('discount_percent');
    }

    /**
     * {@inheritdoc}
     */
    public function setDiscountPercent($discountPercent)
    {
        return $this->setData('discount_percent', $discountPercent);
    }

    /**
     * {@inheritdoc}
     */
    public function getConfidenceScore()
    {
        return $this->getData('confidence_score');
    }

    /**
     * {@inheritdoc}
     */
    public function setConfidenceScore($confidenceScore)
    {
        return $this->setData('confidence_score', $confidenceScore);
    }

    /**
     * {@inheritdoc}
     */
    public function getStatus()
    {
        return $this->getData('status');
    }

    /**
     * {@inheritdoc}
     */
    public function setStatus($status)
    {
        return $this->setData('status', $status);
    }

    /**
     * {@inheritdoc}
     */
    public function getReason()
    {
        return $this->getData('reason');
    }

    /**
     * {@inheritdoc}
     */
    public function setReason($reason)
    {
        return $this->setData('reason', $reason);
    }

    /**
     * {@inheritdoc}
     */
    public function getCreatedAt()
    {
        return $this->getData('created_at');
    }

    /**
     * {@inheritdoc}
     */
    public function setCreatedAt($createdAt)
    {
        return $this->setData('created_at', $createdAt);
    }

    /**
     * {@inheritdoc}
     */
    public function getApprovedAt()
    {
        return $this->getData('approved_at');
    }

    /**
     * {@inheritdoc}
     */
    public function setApprovedAt($approvedAt)
    {
        return $this->setData('approved_at', $approvedAt);
    }

    /**
     * {@inheritdoc}
     */
    public function getRedeemedAt()
    {
        return $this->getData('redeemed_at');
    }

    /**
     * {@inheritdoc}
     */
    public function setRedeemedAt($redeemedAt)
    {
        return $this->setData('redeemed_at', $redeemedAt);
    }

    public function getUpdatedAt()
    {
        return $this->getData('updated_at');
    }

    public function setUpdatedAt($updatedAt)
    {
        return $this->setData('updated_at', $updatedAt);
    }

    public function getExpiresAt()
    {
        return $this->getData('expires_at');
    }

    public function setExpiresAt($expiresAt)
    {
        return $this->setData('expires_at', $expiresAt);
    }

    public function getRejectionFeedback()
    {
        return $this->getData('rejection_feedback');
    }

    public function setRejectionFeedback($feedback)
    {
        return $this->setData('rejection_feedback', $feedback);
    }

    public function getScore()
    {
        return $this->getData('score');
    }

    public function setScore($score)
    {
        return $this->setData('score', $score);
    }

    public function getPosition()
    {
        return $this->getData('position');
    }

    public function setPosition($position)
    {
        return $this->setData('position', $position);
    }
}
