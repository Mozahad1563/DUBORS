<?php
/**
 * BrainStation23
 *
 * @category    BrainStation23
 * @package     BrainStation23_Dubors
 * @copyright   Copyright (c) 2026 BrainStation23
 */

declare(strict_types=1);

namespace BrainStation23\Dubors\Model\ResourceModel\Recommendation\Grid;

class AcceptedCollection extends Collection
{
    /**
     * Filter by accepted status (approved or redeemed)
     *
     * @return $this
     */
    protected function _initSelect()
    {
        parent::_initSelect();
        $this->addFieldToFilter('status', ['in' => ['approved', 'redeemed']]);
        return $this;
    }
}
