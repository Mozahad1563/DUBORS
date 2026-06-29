<?php
/**
 * BrainStation23
 *
 * @category    BrainStation23
 * @package     BrainStation23_Dubors
 * @copyright   Copyright (c) 2026 BrainStation23
 */

namespace BrainStation23\Dubors\Api;

interface UserBehaviorRepositoryInterface
{
    /**
     * Save User Behavior
     *
     * @param \BrainStation23\Dubors\Api\Data\UserBehaviorInterface $behavior
     * @return \BrainStation23\Dubors\Api\Data\UserBehaviorInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function save(\BrainStation23\Dubors\Api\Data\UserBehaviorInterface $behavior);

    /**
     * Get User Behavior by ID
     *
     * @param int $id
     * @return \BrainStation23\Dubors\Api\Data\UserBehaviorInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getById($id);

    /**
     * Delete User Behavior
     *
     * @param \BrainStation23\Dubors\Api\Data\UserBehaviorInterface $behavior
     * @return bool
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function delete(\BrainStation23\Dubors\Api\Data\UserBehaviorInterface $behavior);

    /**
     * Delete User Behavior by ID
     *
     * @param int $id
     * @return bool
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function deleteById($id);

    /**
     * Get List
     *
     * @param \Magento\Framework\Api\SearchCriteriaInterface $searchCriteria
     * @return \Magento\Framework\Api\SearchResults
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getList(\Magento\Framework\Api\SearchCriteriaInterface $searchCriteria);
}
