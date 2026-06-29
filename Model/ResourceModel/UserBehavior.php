<?php
/**
 * BrainStation23
 *
 * @category    BrainStation23
 * @package     BrainStation23_Dubors
 * @copyright   Copyright (c) 2026 BrainStation23
 */

namespace BrainStation23\Dubors\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class UserBehavior extends AbstractDb
{
    /**
     * Initialize table name
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init('vendor_dubors_user_behavior', 'entity_id');
    }
}
