<?php
/**
 * BrainStation23
 *
 * @category    BrainStation23
 * @package     BrainStation23_Dubors
 * @copyright   Copyright (c) 2026 BrainStation23
 */

namespace BrainStation23\Dubors\Api;

interface RecommendationRepositoryInterface
{
    /**
     * Save Recommendation
     *
     * @param \BrainStation23\Dubors\Api\Data\RecommendationInterface $recommendation
     * @return \BrainStation23\Dubors\Api\Data\RecommendationInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function save(\BrainStation23\Dubors\Api\Data\RecommendationInterface $recommendation);

    /**
     * Get Recommendation by ID
     *
     * @param int $id
     * @return \BrainStation23\Dubors\Api\Data\RecommendationInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getById($id);

    /**
     * Delete Recommendation
     *
     * @param \BrainStation23\Dubors\Api\Data\RecommendationInterface $recommendation
     * @return bool
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function delete(\BrainStation23\Dubors\Api\Data\RecommendationInterface $recommendation);

    /**
     * Delete Recommendation by ID
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
