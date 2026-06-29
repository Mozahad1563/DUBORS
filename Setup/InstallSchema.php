<?php
/**
 * BrainStation23
 *
 * @category    BrainStation23
 * @package     BrainStation23_Dubors
 * @copyright   Copyright (c) 2026 BrainStation23
 */

namespace BrainStation23\Dubors\Setup;

use Magento\Framework\Setup\InstallSchemaInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;

class InstallSchema implements InstallSchemaInterface
{
    /**
     * {@inheritdoc}
     */
    public function install(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $installer = $setup;
        $installer->startSetup();

        // Create vendor_dubors_user_behavior table
        $behaviorTable = $installer->getConnection()->newTable(
            $installer->getTable('vendor_dubors_user_behavior')
        )
        ->addColumn(
            'entity_id',
            \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
            null,
            ['identity' => true, 'unsigned' => true, 'nullable' => false, 'primary' => true],
            'Entity ID'
        )
        ->addColumn(
            'customer_id',
            \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
            null,
            ['unsigned' => true, 'nullable' => false],
            'Customer ID'
        )
        ->addColumn(
            'behavior_type',
            \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
            50,
            ['nullable' => false],
            'Behavior Type'
        )
        ->addColumn(
            'product_id',
            \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
            null,
            ['unsigned' => true, 'nullable' => true],
            'Product ID'
        )
        ->addColumn(
            'category_id',
            \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
            null,
            ['unsigned' => true, 'nullable' => true],
            'Category ID'
        )
        ->addColumn(
            'cart_value',
            \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL,
            [12, 2],
            ['nullable' => true],
            'Cart Value'
        )
        ->addColumn(
            'metadata',
            \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
            null,
            ['nullable' => true],
            'JSON Metadata'
        )
        ->addColumn(
            'ip_address',
            \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
            45,
            ['nullable' => true],
            'IP Address'
        )
        ->addColumn(
            'user_agent',
            \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
            512,
            ['nullable' => true],
            'User Agent'
        )
        ->addColumn(
            'created_at',
            \Magento\Framework\DB\Ddl\Table::TYPE_TIMESTAMP,
            null,
            ['nullable' => false, 'default' => \Magento\Framework\DB\Ddl\Table::TIMESTAMP_INIT],
            'Created At'
        )
        ->addColumn(
            'updated_at',
            \Magento\Framework\DB\Ddl\Table::TYPE_TIMESTAMP,
            null,
            [
                'nullable' => false,
                'default' => \Magento\Framework\DB\Ddl\Table::TIMESTAMP_INIT_UPDATE
            ],
            'Updated At'
        )
        ->addIndex(
            $installer->getIdxName('vendor_dubors_user_behavior', ['customer_id']),
            ['customer_id']
        )
        ->addIndex(
            $installer->getIdxName('vendor_dubors_user_behavior', ['behavior_type']),
            ['behavior_type']
        )
        ->addIndex(
            $installer->getIdxName('vendor_dubors_user_behavior', ['created_at']),
            ['created_at']
        )
        ->setComment('DUBORS User Behavior Tracking');

        $installer->getConnection()->createTable($behaviorTable);

        // Create vendor_dubors_recommendation table
        $recommendationTable = $installer->getConnection()->newTable(
            $installer->getTable('vendor_dubors_recommendation')
        )
        ->addColumn(
            'entity_id',
            \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
            null,
            ['identity' => true, 'unsigned' => true, 'nullable' => false, 'primary' => true],
            'Entity ID'
        )
        ->addColumn(
            'customer_id',
            \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
            null,
            ['unsigned' => true, 'nullable' => false],
            'Customer ID'
        )
        ->addColumn(
            'product_id',
            \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
            null,
            ['unsigned' => true, 'nullable' => true],
            'Product ID'
        )
        ->addColumn(
            'recommendation_type',
            \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
            64,
            ['nullable' => false, 'default' => 'hybrid'],
            'Recommendation Type'
        )
        ->addColumn(
            'coupon_code',
            \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
            255,
            ['nullable' => true],
            'Coupon Code'
        )
        ->addColumn(
            'discount_percent',
            \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL,
            [5, 2],
            ['nullable' => true],
            'Discount Percentage'
        )
        ->addColumn(
            'confidence_score',
            \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL,
            [5, 2],
            ['nullable' => true],
            'Confidence Score (0-100)'
        )
        ->addColumn(
            'score',
            \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL,
            [12, 4],
            ['nullable' => true],
            'Final Score'
        )
        ->addColumn(
            'position',
            \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
            null,
            ['unsigned' => true, 'nullable' => true],
            'Display Position'
        )
        ->addColumn(
            'status',
            \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
            50,
            ['nullable' => false, 'default' => 'pending'],
            'Status'
        )
        ->addColumn(
            'reason',
            \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
            null,
            ['nullable' => true],
            'Recommendation Reason'
        )
        ->addColumn(
            'created_at',
            \Magento\Framework\DB\Ddl\Table::TYPE_TIMESTAMP,
            null,
            ['nullable' => false, 'default' => \Magento\Framework\DB\Ddl\Table::TIMESTAMP_INIT],
            'Created At'
        )
        ->addColumn(
            'updated_at',
            \Magento\Framework\DB\Ddl\Table::TYPE_TIMESTAMP,
            null,
            [
                'nullable' => false,
                'default' => \Magento\Framework\DB\Ddl\Table::TIMESTAMP_INIT_UPDATE
            ],
            'Updated At'
        )
        ->addColumn(
            'expires_at',
            \Magento\Framework\DB\Ddl\Table::TYPE_TIMESTAMP,
            null,
            ['nullable' => true],
            'Expires At'
        )
        ->addColumn(
            'approved_at',
            \Magento\Framework\DB\Ddl\Table::TYPE_TIMESTAMP,
            null,
            ['nullable' => true],
            'Approved At'
        )
        ->addColumn(
            'redeemed_at',
            \Magento\Framework\DB\Ddl\Table::TYPE_TIMESTAMP,
            null,
            ['nullable' => true],
            'Redeemed At'
        )
        ->addIndex(
            $installer->getIdxName('vendor_dubors_recommendation', ['customer_id']),
            ['customer_id']
        )
        ->addIndex(
            $installer->getIdxName('vendor_dubors_recommendation', ['status']),
            ['status']
        )
        ->addIndex(
            $installer->getIdxName('vendor_dubors_recommendation', ['product_id']),
            ['product_id']
        )
        ->addIndex(
            $installer->getIdxName('vendor_dubors_recommendation', ['coupon_code']),
            ['coupon_code']
        )
        ->setComment('DUBORS Recommendations');

        $installer->getConnection()->createTable($recommendationTable);

        $installer->endSetup();
    }
}
