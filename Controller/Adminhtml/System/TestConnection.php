<?php
/**
 * BrainStation23
 *
 * @category    BrainStation23
 * @package     BrainStation23_Dubors
 * @copyright   Copyright (c) 2026 BrainStation23
 */

declare(strict_types=1);

namespace BrainStation23\Dubors\Controller\Adminhtml\System;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\Serialize\Serializer\Json;
use Psr\Log\LoggerInterface;

class TestConnection extends Action
{
    public const ADMIN_RESOURCE = 'BrainStation23_Dubors::config';

    /**
     * @param Context $context
     * @param JsonFactory $jsonFactory
     * @param Curl $curl
     * @param Json $json
     * @param LoggerInterface $logger
     */
    public function __construct(
        Context $context,
        private readonly JsonFactory $jsonFactory,
        private readonly Curl $curl,
        private readonly Json $json,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct($context);
    }

    /**
     * Test ML service connectivity using form values (not saved config)
     *
     * @return \Magento\Framework\Controller\Result\Json
     */
    public function execute()
    {
        $result = $this->jsonFactory->create();

        $serviceUrl = trim((string)$this->getRequest()->getParam('service_url', ''));
        $apiKey     = trim((string)$this->getRequest()->getParam('api_key', ''));
        $timeout    = max(5, min(30, (int)$this->getRequest()->getParam('timeout', 10)));

        if (!$serviceUrl) {
            return $result->setData(['success' => false, 'message' => 'ML Service URL is empty.']);
        }

        $healthUrl = rtrim($serviceUrl, '/') . '/health';

        try {
            $this->curl->setTimeout($timeout);
            $this->curl->addHeader('Content-Type', 'application/json');
            if ($apiKey) {
                $this->curl->addHeader('Authorization', 'Bearer ' . $apiKey);
            }
            $this->curl->addHeader('User-Agent', 'Magento-2-Dubors/1.0.0');
            $this->curl->get($healthUrl);

            $status = (int)$this->curl->getStatus();
            $body   = $this->curl->getBody();

            if ($status === 200) {
                $details = [];
                try {
                    $decoded = $this->json->unserialize($body);
                    if (is_array($decoded)) {
                        $stage   = $decoded['stage']   ?? 'unknown';
                        $samples = $decoded['sample_count'] ?? '?';
                        $model   = $decoded['model_id'] ?? 'none';
                        $details = "Stage: {$stage} | Samples: {$samples} | Model: {$model}";
                    }
                } catch (\Exception $e) {
                    $details = 'Service is up.';
                }

                return $result->setData([
                    'success' => true,
                    'message' => 'ML service is reachable. ' . $details,
                ]);
            }

            if ($status === 401 || $status === 403) {
                return $result->setData([
                    'success' => false,
                    'message' => "Authentication failed (HTTP {$status}). Check your API Key.",
                ]);
            }

            return $result->setData([
                'success' => false,
                'message' => "Service returned HTTP {$status}. Check the URL and service logs.",
            ]);
        } catch (\Exception $e) {
            $this->logger->warning('DUBORS test connection failed: ' . $e->getMessage());
            return $result->setData([
                'success' => false,
                'message' => 'Could not connect: ' . $e->getMessage(),
            ]);
        }
    }
}
