<?php
/**
 * BrainStation23
 *
 * @category    BrainStation23
 * @package     BrainStation23_Dubors
 * @copyright   Copyright (c) 2026 BrainStation23
 */

declare(strict_types=1);

namespace BrainStation23\Dubors\Ui\Component\Listing\Column;

use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

class RecommendationActions extends Column
{
    /**
     * @param ContextInterface    $context
     * @param UiComponentFactory  $uiComponentFactory
     * @param UrlInterface        $urlBuilder
     * @param array               $components
     * @param array               $data
     */
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        private readonly UrlInterface $urlBuilder,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    /**
     * Prepare Data Source
     *
     * @param array $dataSource
     * @return array
     */
    public function prepareDataSource(array $dataSource): array
    {
        if (isset($dataSource['data']['items'])) {
            foreach ($dataSource['data']['items'] as &$item) {
                if (isset($item['entity_id'])) {
                    $item[$this->getData('name')] = [
                        'approve' => [
                            'href' => $this->urlBuilder->getUrl(
                                'dubors/recommendation/approve',
                                ['id' => $item['entity_id']]
                            ),
                            'label'   => __('Approve'),
                            'confirm' => [
                                'title'   => __('Approve Recommendation'),
                                'message' => __('Are you sure you want to approve this recommendation?')
                            ]
                        ],
                        'reject' => [
                            'href' => $this->urlBuilder->getUrl(
                                'dubors/recommendation/rejectForm',
                                ['id' => $item['entity_id']]
                            ),
                            'label'   => __('Reject'),
                        ],
                        'delete' => [
                            'href' => $this->urlBuilder->getUrl(
                                'dubors/recommendation/delete',
                                ['id' => $item['entity_id']]
                            ),
                            'label'   => __('Delete'),
                            'confirm' => [
                                'title'   => __('Delete Recommendation'),
                                'message' => __('Are you sure you want to delete this recommendation?')
                            ]
                        ],
                    ];
                }
            }
        }

        return $dataSource;
    }
}
