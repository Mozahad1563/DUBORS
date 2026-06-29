<?php
/**
 * BrainStation23
 *
 * @category    BrainStation23
 * @package     BrainStation23_Dubors
 * @copyright   Copyright (c) 2026 BrainStation23
 */

namespace BrainStation23\Dubors\Observer;

use BrainStation23\Dubors\Api\Data\UserBehaviorInterface;
use BrainStation23\Dubors\Api\UserBehaviorRepositoryInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\ObjectManagerInterface;
use Psr\Log\LoggerInterface;

class TrackSearch implements ObserverInterface
{
    /**
     * @var ObjectManagerInterface
     */
    private $objectManager;

    /**
     * @var UserBehaviorRepositoryInterface
     */
    private $userBehaviorRepository;

    /**
     * @var CustomerSession
     */
    private $customerSession;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * Constructor
     *
     * @param ObjectManagerInterface $objectManager
     * @param UserBehaviorRepositoryInterface $userBehaviorRepository
     * @param CustomerSession $customerSession
     * @param LoggerInterface $logger
     */
    public function __construct(
        ObjectManagerInterface $objectManager,
        UserBehaviorRepositoryInterface $userBehaviorRepository,
        CustomerSession $customerSession,
        LoggerInterface $logger
    ) {
        $this->objectManager = $objectManager;
        $this->userBehaviorRepository = $userBehaviorRepository;
        $this->customerSession = $customerSession;
        $this->logger = $logger;
    }

    /**
     * Track search event
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer)
    {
        try {
            // Track search even for guest users (optional)
            if (!$this->customerSession->isLoggedIn()) {
                return;
            }

            $customerId = $this->customerSession->getCustomerId();

            // Get query from event
            $query = $observer->getEvent()->getQuery();

            if (!$query || !$query->getQueryText()) {
                return;
            }

            // Create user behavior record
            $behavior = $this->objectManager->create(UserBehaviorInterface::class);
            $behavior->setCustomerId($customerId)
                ->setEventType('search')
                ->setProductId(null)
                ->setCategoryId(null)
                ->setCartValue(0)
                ->setCreatedAt(date('Y-m-d H:i:s'));

            $this->userBehaviorRepository->save($behavior);

            $this->logger->info(
                'DUBORS: Tracked search',
                [
                    'customer_id' => $customerId,
                    'query' => $query->getQueryText()
                ]
            );
        } catch (\Exception $e) {
            $this->logger->error('DUBORS: Error tracking search: ' . $e->getMessage());
        }
    }
}
