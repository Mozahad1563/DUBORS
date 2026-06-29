<?php
/**
 * BrainStation23
 *
 * @category    BrainStation23
 * @package     BrainStation23_Dubors
 * @copyright   Copyright (c) 2026 BrainStation23
 */

declare(strict_types=1);

namespace BrainStation23\Dubors\Block\Adminhtml\System;

use BrainStation23\Dubors\Helper\Config;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;

class Dashboard extends Template
{
    /**
     * @param Context $context
     * @param Config $config
     * @param array $data
     */
    public function __construct(
        Context $context,
        private readonly Config $config,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Browser-accessible base URL of the ML service (e.g. http://localhost:8000)
     *
     * Uses the dedicated browser URL config when set (needed when the server-side URL is a
     * Docker-internal hostname like http://ml:8000 that the browser cannot resolve).
     * Falls back to the server URL if the browser URL field is left empty.
     *
     * @return string
     */
    public function getMlServiceBaseUrl(): string
    {
        return rtrim((string)($this->config->getMlBrowserUrl() ?? 'http://localhost:8000'), '/');
    }

    /**
     * WebSocket URL derived from the HTTP service URL
     *
     * @return string
     */
    public function getMlServiceWsUrl(): string
    {
        $base = $this->getMlServiceBaseUrl();
        // http:// → ws://    https:// → wss://
        $ws = preg_replace('/^http(s?):\/\//', 'ws$1://', $base);
        return $ws . '/ws/live';
    }

    /**
     * Dashboard iframe / direct URL
     *
     * @return string
     */
    public function getMlDashboardUrl(): string
    {
        return $this->getMlServiceBaseUrl() . '/dashboard';
    }

    /**
     * Whether the ML service is configured and enabled
     *
     * @return bool
     */
    public function isMlServiceConfigured(): bool
    {
        return $this->config->isMlServiceEnabled()
            && !empty($this->config->getMlServiceUrl());
    }
}
