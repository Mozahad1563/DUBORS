<?php
/**
 * BrainStation23
 *
 * @category    BrainStation23
 * @package     BrainStation23_Dubors
 * @copyright   Copyright (c) 2026 BrainStation23
 */

declare(strict_types=1);

namespace BrainStation23\Dubors\Model\Resolver;

use BrainStation23\Dubors\Model\ResourceModel\UserBehavior\CollectionFactory;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;

class UserBehaviorsQuery implements ResolverInterface
{
    /**
     * @param CollectionFactory $collectionFactory
     */
    public function __construct(
        private readonly CollectionFactory $collectionFactory
    ) {
    }

    /**
     * Resolve user behaviors query
     *
     * @param Field $field
     * @param mixed $context
     * @param ResolveInfo $info
     * @param array|null $args
     * @return array
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        ?array $value = null,
        ?array $args = null
    ) {
        $collection = $this->collectionFactory->create();

        // Apply required customer filter
        if (isset($args['customerId']) && $args['customerId']) {
            $collection->addFieldToFilter('customer_id', ['eq' => $args['customerId']]);
        }

        // Apply optional behavior type filter
        if (isset($args['behaviorType']) && $args['behaviorType']) {
            $collection->addFieldToFilter('behavior_type', ['eq' => $args['behaviorType']]);
        }

        // Apply pagination
        $limit = $args['limit'] ?? 10;
        $offset = $args['offset'] ?? 0;

        $collection->setPageSize($limit);
        $collection->setCurPage(($offset / $limit) + 1);

        // Sort by latest first
        $collection->setOrder('created_at', 'desc');

        $totalCount = $collection->getSize();
        $items = [];

        foreach ($collection as $behavior) {
            $items[] = $this->formatUserBehavior($behavior);
        }

        return [
            'items' => $items,
            'total_count' => $totalCount,
            'page_info' => [
                'page_size' => $limit,
                'current_page' => ceil(($offset + 1) / $limit),
                'total_pages' => ceil($totalCount / $limit),
                'has_previous_page' => $offset > 0,
                'has_next_page' => ($offset + $limit) < $totalCount
            ]
        ];
    }

    /**
     * Format user behavior for GraphQL response
     *
     * @param mixed $behavior
     * @return array
     */
    private function formatUserBehavior($behavior): array
    {
        return [
            'id' => (int)$behavior->getEntityId(),
            'customer_id' => (int)$behavior->getCustomerId(),
            'behavior_type' => (string)$behavior->getBehaviorType(),
            'product_id' => $behavior->getProductId() ? (int)$behavior->getProductId() : null,
            'metadata' => $behavior->getMetadata() ? (string)$behavior->getMetadata() : null,
            'ip_address' => $behavior->getIpAddress() ? (string)$behavior->getIpAddress() : null,
            'user_agent' => $behavior->getUserAgent() ? (string)$behavior->getUserAgent() : null,
            'created_at' => (string)$behavior->getCreatedAt()
        ];
    }
}
