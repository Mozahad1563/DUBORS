<?php
/**
 * BrainStation23
 *
 * @category    BrainStation23
 * @package     BrainStation23_Dubors
 * @copyright   Copyright (c) 2026 BrainStation23
 */

namespace BrainStation23\Dubors\Api\Data;

interface UserBehaviorInterface
{
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
     * Get Event Type
     *
     * @return string
     */
    public function getEventType();

    /**
     * Set Event Type
     *
     * @param string $eventType
     * @return $this
     */
    public function setEventType($eventType);

    /**
     * Get Behavior Type
     *
     * @return string
     */
    public function getBehaviorType();

    /**
     * Set Behavior Type
     *
     * @param string $behaviorType
     * @return $this
     */
    public function setBehaviorType($behaviorType);

    /**
     * Get Product ID
     *
     * @return int|null
     */
    public function getProductId();

    /**
     * Set Product ID
     *
     * @param int $productId
     * @return $this
     */
    public function setProductId($productId);

    /**
     * Get Category ID
     *
     * @return int|null
     */
    public function getCategoryId();

    /**
     * Set Category ID
     *
     * @param int $categoryId
     * @return $this
     */
    public function setCategoryId($categoryId);

    /**
     * Get Cart Value
     *
     * @return float|null
     */
    public function getCartValue();

    /**
     * Set Cart Value
     *
     * @param float $cartValue
     * @return $this
     */
    public function setCartValue($cartValue);

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
     * Get Metadata
     *
     * @return string|null
     */
    public function getMetadata();

    /**
     * Set Metadata
     *
     * @param string|null $metadata
     * @return $this
     */
    public function setMetadata($metadata);

    /**
     * Get IP Address
     *
     * @return string|null
     */
    public function getIpAddress();

    /**
     * Set IP Address
     *
     * @param string|null $ipAddress
     * @return $this
     */
    public function setIpAddress($ipAddress);

    /**
     * Get User Agent
     *
     * @return string|null
     */
    public function getUserAgent();

    /**
     * Set User Agent
     *
     * @param string|null $userAgent
     * @return $this
     */
    public function setUserAgent($userAgent);

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
}
