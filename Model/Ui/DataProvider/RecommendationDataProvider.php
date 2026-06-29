<?php
/**
 * BrainStation23
 *
 * @category    BrainStation23
 * @package     BrainStation23_Dubors
 * @copyright   Copyright (c) 2026 BrainStation23
 */

declare(strict_types=1);

namespace BrainStation23\Dubors\Model\Ui\DataProvider;

use BrainStation23\Dubors\Model\ResourceModel\Recommendation\Collection;
use Magento\Framework\App\RequestInterface;
use Magento\Ui\DataProvider\AbstractDataProvider;

class RecommendationDataProvider extends AbstractDataProvider
{
    /**
     * @param string $name
     * @param string $primaryFieldName
     * @param string $requestFieldName
     * @param Collection $collection
     * @param RequestInterface $request
     */
    public function __construct(
        string $name,
        string $primaryFieldName,
        string $requestFieldName,
        Collection $collection,
        private readonly RequestInterface $request
    ) {
        parent::__construct($name, $primaryFieldName, $requestFieldName);
        $this->collection = $collection;
    }

    /**
     * Get data
     *
     * @return array
     */
    public function getData(): array
    {
        if (!$this->getCollection()->isLoaded()) {
            $this->getCollection()->load();
        }

        $items = $this->getCollection()->toArray();

        return [
            'totalRecords' => $this->getCollection()->getSize(),
            'items' => $items['items'] ?? []
        ];
    }

    /**
     * Add filter to collection
     *
     * @param \Magento\Framework\Api\Filter $filter
     * @return void
     */
    public function addFilter(\Magento\Framework\Api\Filter $filter): void
    {
        $field = $filter->getField();
        $value = $filter->getValue();

        if ($field === 'status') {
            $this->getCollection()->addFieldToFilter('status', ['eq' => $value]);
        } elseif ($field === 'customer_id') {
            $this->getCollection()->addFieldToFilter('customer_id', ['eq' => $value]);
        } elseif ($field === 'confidence_score') {
            $this->getCollection()->addFieldToFilter('confidence_score', ['gteq' => $value]);
        } else {
            parent::addFilter($filter);
        }
    }
}
