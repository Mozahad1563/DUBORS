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

use BrainStation23\Dubors\Model\RecommendationRepository;
use BrainStation23\Dubors\Model\ResourceModel\Recommendation\CollectionFactory;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;

class RecommendationsQuery implements ResolverInterface
{
    /**
     * @param RecommendationRepository $recommendationRepository
     * @param CollectionFactory $collectionFactory
     */
    public function __construct(
        private readonly RecommendationRepository $recommendationRepository,
        private readonly CollectionFactory $collectionFactory
    ) {
    }

    /**
     * Resolve recommendations query
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

        // Apply filters
        if (isset($args['customerId']) && $args['customerId']) {
            $collection->addFieldToFilter('customer_id', ['eq' => $args['customerId']]);
        }

        if (isset($args['status']) && $args['status']) {
            $collection->addFieldToFilter('status', ['eq' => $args['status']]);
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

        foreach ($collection as $recommendation) {
            $items[] = $this->formatRecommendation($recommendation);
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
     * Format recommendation for GraphQL response
     *
     * @param mixed $recommendation
     * @return array
     */
    private function formatRecommendation($recommendation): array
    {
        return [
            'id' => (int)$recommendation->getEntityId(),
            'customer_id' => (int)$recommendation->getCustomerId(),
            'product_id' => (int)$recommendation->getProductId(),
            'recommendation_type' => (string)$recommendation->getRecommendationType(),
            'confidence_score' => (float)$recommendation->getConfidenceScore(),
            'status' => (string)$recommendation->getStatus(),
            'created_at' => (string)$recommendation->getCreatedAt(),
            'updated_at' => (string)$recommendation->getUpdatedAt()
        ];
    }
}
