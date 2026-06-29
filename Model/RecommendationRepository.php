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
use BrainStation23\Dubors\Api\RecommendationRepositoryInterface;
use BrainStation23\Dubors\Model\ResourceModel\Recommendation as RecommendationResourceModel;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\ObjectManagerInterface;

class RecommendationRepository implements RecommendationRepositoryInterface
{
    /**
     * @var ObjectManagerInterface
     */
    private $objectManager;

    /**
     * @var RecommendationResourceModel
     */
    private $resourceModel;

    /**
     * @var Recommendation[]
     */
    private $instances = [];

    /**
     * Constructor
     *
     * @param ObjectManagerInterface $objectManager
     * @param RecommendationResourceModel $resourceModel
     */
    public function __construct(
        ObjectManagerInterface $objectManager,
        RecommendationResourceModel $resourceModel
    ) {
        $this->objectManager = $objectManager;
        $this->resourceModel = $resourceModel;
    }

    /**
     * {@inheritdoc}
     */
    public function save(RecommendationInterface $recommendation)
    {
        try {
            $this->resourceModel->save($recommendation);
            unset($this->instances[$recommendation->getId()]);
        } catch (\Exception $e) {
            throw new LocalizedException(
                __('Unable to save recommendation: %1', $e->getMessage())
            );
        }
        return $recommendation;
    }

    /**
     * {@inheritdoc}
     */
    public function getById($id)
    {
        if (!isset($this->instances[$id])) {
            /** @var Recommendation $recommendation */
            $recommendation = $this->objectManager->create(Recommendation::class);
            $this->resourceModel->load($recommendation, $id);
            if (!$recommendation->getId()) {
                throw new NoSuchEntityException(
                    __('Recommendation with id "%1" does not exist.', $id)
                );
            }
            $this->instances[$id] = $recommendation;
        }
        return $this->instances[$id];
    }

    /**
     * {@inheritdoc}
     */
    public function delete(RecommendationInterface $recommendation)
    {
        try {
            $this->resourceModel->delete($recommendation);
            unset($this->instances[$recommendation->getId()]);
        } catch (\Exception $e) {
            throw new LocalizedException(
                __('Unable to delete recommendation: %1', $e->getMessage())
            );
        }
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function deleteById($id)
    {
        $recommendation = $this->getById($id);
        return $this->delete($recommendation);
    }

    /**
     * {@inheritdoc}
     */
    public function getList(SearchCriteriaInterface $searchCriteria)
    {
        $collection = $this->objectManager->create(
            'BrainStation23\Dubors\Model\ResourceModel\Recommendation\Collection'
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
