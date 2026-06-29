<?php
/**
 * BrainStation23
 *
 * @category    BrainStation23
 * @package     BrainStation23_Dubors
 * @copyright   Copyright (c) 2026 BrainStation23
 */

namespace BrainStation23\Dubors\Model;

use BrainStation23\Dubors\Api\Data\UserBehaviorInterface;
use Magento\Framework\Model\AbstractModel;

class UserBehavior extends AbstractModel implements UserBehaviorInterface
{
    protected $_idFieldName = 'entity_id';

    protected function _construct()
    {
        $this->_init(ResourceModel\UserBehavior::class);
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

    /**
     * {@inheritdoc}
     */
    public function getEventType()
    {
        return $this->getData('behavior_type');
    }

    /**
     * {@inheritdoc}
     */
    public function setEventType($eventType)
    {
        return $this->setData('behavior_type', $eventType);
    }

    public function getBehaviorType()
    {
        return $this->getEventType();
    }

    public function setBehaviorType($behaviorType)
    {
        return $this->setEventType($behaviorType);
    }

    /**
     * {@inheritdoc}
     */
    public function getProductId()
    {
        return $this->getData('product_id');
    }

    /**
     * {@inheritdoc}
     */
    public function setProductId($productId)
    {
        return $this->setData('product_id', $productId);
    }

    /**
     * {@inheritdoc}
     */
    public function getCategoryId()
    {
        return $this->getData('category_id');
    }

    /**
     * {@inheritdoc}
     */
    public function setCategoryId($categoryId)
    {
        return $this->setData('category_id', $categoryId);
    }

    /**
     * {@inheritdoc}
     */
    public function getCartValue()
    {
        return $this->getData('cart_value');
    }

    /**
     * {@inheritdoc}
     */
    public function setCartValue($cartValue)
    {
        return $this->setData('cart_value', $cartValue);
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

    public function getMetadata()
    {
        return $this->getData('metadata');
    }

    public function setMetadata($metadata)
    {
        return $this->setData('metadata', $metadata);
    }

    public function getIpAddress()
    {
        return $this->getData('ip_address');
    }

    public function setIpAddress($ipAddress)
    {
        return $this->setData('ip_address', $ipAddress);
    }

    public function getUserAgent()
    {
        return $this->getData('user_agent');
    }

    public function setUserAgent($userAgent)
    {
        return $this->setData('user_agent', $userAgent);
    }

    public function getUpdatedAt()
    {
        return $this->getData('updated_at');
    }

    public function setUpdatedAt($updatedAt)
    {
        return $this->setData('updated_at', $updatedAt);
    }
}
