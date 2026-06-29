<?php
/**
 * BrainStation23
 *
 * @category    BrainStation23
 * @package     BrainStation23_Dubors
 * @copyright   Copyright (c) 2026 BrainStation23
 */

declare(strict_types=1);

namespace BrainStation23\Dubors\Controller\Adminhtml\Recommendation;

use BrainStation23\Dubors\Helper\Config;
use BrainStation23\Dubors\Model\RecommendationRepository;
use BrainStation23\Dubors\Service\CouponGenerator;
use BrainStation23\Dubors\Service\MlServiceClient;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Psr\Log\LoggerInterface;

class Approve extends Action
{
    public const ADMIN_RESOURCE = 'BrainStation23_Dubors::recommendations';

    public function __construct(
        Context $context,
        private readonly RecommendationRepository $recommendationRepository,
        private readonly CouponGenerator $couponGenerator,
        private readonly MlServiceClient $mlClient,
        private readonly Config $config,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $id = $this->getRequest()->getParam('id');

        try {
            $recommendation = $this->recommendationRepository->getById((int)$id);

            $couponCode = $this->couponGenerator->generateForRecommendation($recommendation);
            $recommendation->setCouponCode($couponCode);
            $recommendation->setStatus('approved');
            $recommendation->setApprovedAt(date('Y-m-d H:i:s'));

            $validityDays = $this->config->getRecommendationValidityDays();
            $recommendation->setExpiresAt(date('Y-m-d H:i:s', strtotime("+{$validityDays} days")));

            $this->recommendationRepository->save($recommendation);

            try {
                $this->mlClient->sendFeedback(
                    (string)$recommendation->getEntityId(),
                    (int)$recommendation->getCustomerId(),
                    (float)$recommendation->getDiscountPercent(),
                    true
                );
            } catch (\Exception $e) {
                $this->logger->warning('ML feedback failed on approve', ['error' => $e->getMessage()]);
            }

            $this->messageManager->addSuccessMessage(
                __('Recommendation #%1 approved. Coupon %2 generated.', $id, $couponCode)
            );
        } catch (NoSuchEntityException $e) {
            $this->messageManager->addErrorMessage(
                __('Recommendation #%1 does not exist.', $id)
            );
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(
                __('An error occurred while approving the recommendation: %1', $e->getMessage())
            );
        }

        return $resultRedirect->setPath('*/*/');
    }
}
