<?php

namespace BrainStation23\Dubors\Block\Adminhtml\Analytics;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;

/**
 * Analytics Dashboard Block
 * 
 * Displays KPI metrics and charts in the admin dashboard
 */
class Dashboard extends Template
{
    /**
     * @var string
     */
    protected $_template = 'BrainStation23_Dubors::analytics/dashboard.phtml';

    /**
     * Dashboard constructor
     *
     * @param Context $context
     * @param array $data
     */
    public function __construct(
        Context $context,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Get dashboard data
     *
     * @return array
     */
    public function getDashboardData(): array
    {
        return $this->_getSession()->getDashboardData() ?? [];
    }

    /**
     * Get KPI metrics
     *
     * @return array
     */
    public function getKpis(): array
    {
        $data = $this->getDashboardData();
        return $data['kpis'] ?? [];
    }

    /**
     * Get daily trends
     *
     * @return array
     */
    public function getTrends(): array
    {
        $data = $this->getDashboardData();
        return $data['trends'] ?? [];
    }

    /**
     * Get segment KPIs
     *
     * @return array
     */
    public function getSegmentKpis(): array
    {
        $data = $this->getDashboardData();
        return $data['segment_kpis'] ?? [];
    }

    /**
     * Get period from
     *
     * @return string
     */
    public function getPeriodFrom(): string
    {
        $data = $this->getDashboardData();
        return $data['period_from'] ?? '';
    }

    /**
     * Get period to
     *
     * @return string
     */
    public function getPeriodTo(): string
    {
        $data = $this->getDashboardData();
        return $data['period_to'] ?? '';
    }

    /**
     * Format currency value
     *
     * @param float $value
     * @return string
     */
    public function formatCurrency(float $value): string
    {
        return $this->_storeManager->getStore()->getBaseCurrency()->format($value);
    }

    /**
     * Format percentage
     *
     * @param float $value
     * @return string
     */
    public function formatPercentage(float $value): string
    {
        return round($value, 2) . '%';
    }
}
