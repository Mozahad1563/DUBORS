<?php
/**
 * BrainStation23
 *
 * @category    BrainStation23
 * @package     BrainStation23_Dubors
 * @copyright   Copyright (c) 2026 BrainStation23
 */

declare(strict_types=1);

namespace BrainStation23\Dubors\Model\MessageQueue;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\Translate\Inline\StateInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

class NotificationConsumer
{
    /**
     * @param TransportBuilder $transportBuilder
     * @param StateInterface $inlineTranslation
     * @param StoreManagerInterface $storeManager
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly TransportBuilder $transportBuilder,
        private readonly StateInterface $inlineTranslation,
        private readonly StoreManagerInterface $storeManager,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Process notification from queue
     *
     * @param string $message
     * @return void
     */
    public function process(string $message): void
    {
        try {
            $data = \json_decode($message, true);

            if (!isset($data['recipient_email']) || !isset($data['subject'])) {
                throw new LocalizedException(
                    __('Invalid notification: missing required fields')
                );
            }

            // Disable inline translations temporarily
            $this->inlineTranslation->suspend();

            try {
                // Build and send email
                $transport = $this->transportBuilder
                    ->setTemplateIdentifier($data['template_id'] ?? 'dubors_notification_email')
                    ->setTemplateOptions([
                        'area' => 'frontend',
                        'store' => $this->storeManager->getStore()->getId(),
                    ])
                    ->setTemplateVars($data['template_vars'] ?? [])
                    ->setFromByScope('general')
                    ->addTo($data['recipient_email'])
                    ->getTransport();

                $transport->sendMessage();

                $this->logger->info(
                    sprintf('Sent notification email to %s', $data['recipient_email'])
                );
            } finally {
                // Resume inline translations
                $this->inlineTranslation->resume();
            }
        } catch (\Exception $e) {
            $this->logger->error(
                sprintf('Error processing notification: %s', $e->getMessage())
            );
            throw $e;
        }
    }
}
