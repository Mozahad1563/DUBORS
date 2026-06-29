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

use BrainStation23\Dubors\Model\UserBehavior;
use BrainStation23\Dubors\Model\UserBehaviorFactory;
use BrainStation23\Dubors\Model\UserBehaviorRepository;
use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface;

class UserBehaviorConsumer
{
    /**
     * @param UserBehaviorFactory $userBehaviorFactory
     * @param UserBehaviorRepository $userBehaviorRepository
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly UserBehaviorFactory $userBehaviorFactory,
        private readonly UserBehaviorRepository $userBehaviorRepository,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Process user behavior event from queue
     *
     * @param string $message
     * @return void
     */
    public function process(string $message): void
    {
        try {
            $data = \json_decode($message, true);

            if (!isset($data['customer_id']) || !isset($data['behavior_type'])) {
                throw new LocalizedException(
                    __('Invalid behavior event: missing required fields')
                );
            }

            /** @var UserBehavior $userBehavior */
            $userBehavior = $this->userBehaviorFactory->create();
            $userBehavior->setCustomerId((int)$data['customer_id']);
            $userBehavior->setBehaviorType($data['behavior_type']);
            $userBehavior->setProductId($data['product_id'] ?? null);
            $userBehavior->setMetadata($data['metadata'] ?? null);
            $userBehavior->setIpAddress($data['ip_address'] ?? null);
            $userBehavior->setUserAgent($data['user_agent'] ?? null);

            $this->userBehaviorRepository->save($userBehavior);

            $this->logger->info(
                sprintf(
                    'Processed user behavior event for customer %d: %s',
                    $data['customer_id'],
                    $data['behavior_type']
                )
            );
        } catch (\Exception $e) {
            $this->logger->error(
                sprintf('Error processing user behavior event: %s', $e->getMessage())
            );
            throw $e;
        }
    }
}
