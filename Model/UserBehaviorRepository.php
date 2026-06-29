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
use BrainStation23\Dubors\Api\UserBehaviorRepositoryInterface;
use BrainStation23\Dubors\Model\ResourceModel\UserBehavior as UserBehaviorResourceModel;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\ObjectManagerInterface;

class UserBehaviorRepository implements UserBehaviorRepositoryInterface
{
    /**
     * @var ObjectManagerInterface
     */
    private $objectManager;

    /**
     * @var UserBehaviorResourceModel
     */
    private $resourceModel;

    /**
     * @var UserBehavior[]
     */
    private $instances = [];

    /**
     * Constructor
     *
     * @param ObjectManagerInterface $objectManager
     * @param UserBehaviorResourceModel $resourceModel
     */
    public function __construct(
        ObjectManagerInterface $objectManager,
        UserBehaviorResourceModel $resourceModel
    ) {
        $this->objectManager = $objectManager;
        $this->resourceModel = $resourceModel;
    }

    /**
     * {@inheritdoc}
     */
    public function save(UserBehaviorInterface $userBehavior)
    {
        try {
            $this->resourceModel->save($userBehavior);
            unset($this->instances[$userBehavior->getId()]);
        } catch (\Exception $e) {
            throw new LocalizedException(
                __('Unable to save user behavior: %1', $e->getMessage())
            );
        }
        return $userBehavior;
    }

    /**
     * {@inheritdoc}
     */
    public function getById($id)
    {
        if (!isset($this->instances[$id])) {
            /** @var UserBehavior $userBehavior */
            $userBehavior = $this->objectManager->create(UserBehavior::class);
            $this->resourceModel->load($userBehavior, $id);
            if (!$userBehavior->getId()) {
                throw new NoSuchEntityException(
                    __('User behavior with id "%1" does not exist.', $id)
                );
            }
            $this->instances[$id] = $userBehavior;
        }
        return $this->instances[$id];
    }

    /**
     * {@inheritdoc}
     */
    public function delete(UserBehaviorInterface $userBehavior)
    {
        try {
            $this->resourceModel->delete($userBehavior);
            unset($this->instances[$userBehavior->getId()]);
        } catch (\Exception $e) {
            throw new LocalizedException(
                __('Unable to delete user behavior: %1', $e->getMessage())
            );
        }
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function deleteById($id)
    {
        $userBehavior = $this->getById($id);
        return $this->delete($userBehavior);
    }

    /**
     * {@inheritdoc}
     */
    public function getList(SearchCriteriaInterface $searchCriteria)
    {
        $collection = $this->objectManager->create(
            'BrainStation23\Dubors\Model\ResourceModel\UserBehavior\Collection'
        );
        
        $searchResults = $this->objectManager->create(
            'Magento\Framework\Api\SearchResults'
        );
        $searchResults->setItems($collection->getItems());
        $searchResults->setTotalCount($collection->getSize());
        $searchResults->setSearchCriteria($searchCriteria);
        
        return $searchResults;
    }
}
