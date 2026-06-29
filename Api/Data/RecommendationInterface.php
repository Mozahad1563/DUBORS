<?php
/**
 * BrainStation23
 *
 * @category    BrainStation23
 * @package     BrainStation23_Dubors
 * @copyright   Copyright (c) 2026 BrainStation23
 */

namespace BrainStation23\Dubors\Api\Data;

interface RecommendationInterface
{
    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';
    const STATUS_REDEEMED = 'redeemed';
    const STATUS_EXPIRED = 'expired';

    /**
     * Get ID
     *
     * @return int|null
     */
    public function getId();

    /**
     * Set ID
     *
     * @param int $id
     * @return $this
     */
    public function setId($id);

    /**
     * Get Entity ID
     *
     * @return int|null
     */
    public function getEntityId();

    /**
     * Set Entity ID
     *
     * @param int $entityId
     * @return $this
     */
    public function setEntityId($entityId);

    /**
     * Get Customer ID
     *
     * @return int
     */
    public function getCustomerId();

    /**
     * Set Customer ID
     *
     * @param int $customerId
     * @return $this
     */
    public function setCustomerId($customerId);

    /**
     * Get Product ID
     *
     * @return int|null
     */
    public function getProductId();

    /**
     * Set Product ID
     *
     * @param int|null $productId
     * @return $this
     */
    public function setProductId($productId);

    /**
     * Get Recommendation Type
     *
     * @return string|null
     */
    public function getRecommendationType();

    /**
     * Set Recommendation Type
     *
     * @param string|null $recommendationType
     * @return $this
     */
    public function setRecommendationType($recommendationType);

    /**
     * Get Coupon Code
     *
     * @return string|null
     */
    public function getCouponCode();

    /**
     * Set Coupon Code
     *
     * @param string $couponCode
     * @return $this
     */
    public function setCouponCode($couponCode);

    /**
     * Get Discount Percent
     *
     * @return float|null
     */
    public function getDiscountPercent();

    /**
     * Set Discount Percent
     *
     * @param float $discountPercent
     * @return $this
     */
    public function setDiscountPercent($discountPercent);

    /**
     * Get Confidence Score
     *
     * @return int|null
     */
    public function getConfidenceScore();

    /**
     * Set Confidence Score
     *
     * @param int $confidenceScore
     * @return $this
     */
    public function setConfidenceScore($confidenceScore);

    /**
     * Get Status
     *
     * @return string
     */
    public function getStatus();

    /**
     * Set Status
     *
     * @param string $status
     * @return $this
     */
    public function setStatus($status);

    /**
     * Get Reason
     *
     * @return string|null
     */
    public function getReason();

    /**
     * Set Reason
     *
     * @param string $reason
     * @return $this
     */
    public function setReason($reason);

    /**
     * Get Created At
     *
     * @return string
     */
    public function getCreatedAt();

    /**
     * Set Created At
     *
     * @param string $createdAt
     * @return $this
     */
    public function setCreatedAt($createdAt);

    /**
     * Get Approved At
     *
     * @return string|null
     */
    public function getApprovedAt();

    /**
     * Set Approved At
     *
     * @param string $approvedAt
     * @return $this
     */
    public function setApprovedAt($approvedAt);

    /**
     * Get Redeemed At
     *
     * @return string|null
     */
    public function getRedeemedAt();

    /**
     * Set Redeemed At
     *
     * @param string $redeemedAt
     * @return $this
     */
    public function setRedeemedAt($redeemedAt);

    /**
     * Get Updated At
     *
     * @return string|null
     */
    public function getUpdatedAt();

    /**
     * Set Updated At
     *
     * @param string|null $updatedAt
     * @return $this
     */
    public function setUpdatedAt($updatedAt);

    /**
     * Get Expires At
     *
     * @return string|null
     */
    public function getExpiresAt();

    /**
     * Set Expires At
     *
     * @param string|null $expiresAt
     * @return $this
     */
    public function setExpiresAt($expiresAt);

    /**
     * Get Score
     *
     * @return float|null
     */
    public function getScore();

    /**
     * Set Score
     *
     * @param float|null $score
     * @return $this
     */
    public function setScore($score);

    /**
     * Get Position
     *
     * @return int|null
     */
    public function getPosition();

    /**
     * Set Position
     *
     * @param int|null $position
     * @return $this
     */
    public function setPosition($position);
}
