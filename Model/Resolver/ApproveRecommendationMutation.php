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
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;

class ApproveRecommendationMutation implements ResolverInterface
{
    /**
     * @param RecommendationRepository $recommendationRepository
     */
    public function __construct(
        private readonly RecommendationRepository $recommendationRepository
    ) {
    }

    /**
     * Resolve approve recommendation mutation
     *
     * @param Field $field
     * @param mixed $context
     * @param ResolveInfo $info
     * @param mixed $value
     * @param array|null $args
     * @return array
     * @throws LocalizedException
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        ?array $value = null,
        ?array $args = null
    ): array {
        try {
            if (!isset($args['id']) || !$args['id']) {
                throw new LocalizedException(__('Recommendation ID is required.'));
            }

            $recommendation = $this->recommendationRepository->getById((int)$args['id']);
            $recommendation->setStatus('approved');
            $this->recommendationRepository->save($recommendation);

            return [
                'success' => true,
                'message' => __('Recommendation has been approved successfully.'),
                'recommendation' => $this->formatRecommendation($recommendation)
            ];
        } catch (NoSuchEntityException $e) {
            throw new LocalizedException(
                __('Recommendation #%1 does not exist.', $args['id'] ?? 'unknown')
            );
        } catch (LocalizedException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new LocalizedException(
                __('An error occurred while approving the recommendation: %1', $e->getMessage())
            );
        }
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
