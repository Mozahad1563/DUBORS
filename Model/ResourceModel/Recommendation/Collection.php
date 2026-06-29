<?php
/**
 * BrainStation23
 *
 * @category    BrainStation23
 * @package     BrainStation23_Dubors
 * @copyright   Copyright (c) 2026 BrainStation23
 */

namespace BrainStation23\Dubors\Model\ResourceModel\Recommendation;

use BrainStation23\Dubors\Model\Recommendation;
use BrainStation23\Dubors\Model\ResourceModel\Recommendation as RecommendationResourceModel;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    /**
     * Initialize collection
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init(
            Recommendation::class,
            RecommendationResourceModel::class
        );
    }
}
