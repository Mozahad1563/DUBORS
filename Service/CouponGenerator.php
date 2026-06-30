<?php
/**
 * BrainStation23
 *
 * @category    BrainStation23
 * @package     BrainStation23_Dubors
 * @copyright   Copyright (c) 2026 BrainStation23
 */

declare(strict_types=1);

namespace BrainStation23\Dubors\Service;

use BrainStation23\Dubors\Api\Data\RecommendationInterface;
use BrainStation23\Dubors\Helper\Config;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Math\Random;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\SalesRule\Api\CouponRepositoryInterface;
use Magento\SalesRule\Api\Data\CouponInterfaceFactory;
use Magento\SalesRule\Api\Data\RuleInterface;
use Magento\SalesRule\Api\Data\RuleInterfaceFactory;
use Magento\SalesRule\Api\RuleRepositoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Service for generating personalized coupons based on recommendations
 */
class CouponGenerator
{
    private const COUPON_PREFIX = 'DUBORS-';

    /**
     * @param RuleInterfaceFactory $ruleFactory
     * @param RuleRepositoryInterface $ruleRepository
     * @param CouponInterfaceFactory $couponFactory
     * @param CouponRepositoryInterface $couponRepository
     * @param Random $random
     * @param DateTime $dateTime
     * @param Config $config
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly RuleInterfaceFactory $ruleFactory,
        private readonly RuleRepositoryInterface $ruleRepository,
        private readonly CouponInterfaceFactory $couponFactory,
        private readonly CouponRepositoryInterface $couponRepository,
        private readonly Random $random,
        private readonly DateTime $dateTime,
        private readonly Config $config,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Generate coupon for recommendation
     *
     * @param RecommendationInterface $recommendation
     * @return string Generated coupon code
     * @throws LocalizedException
     */
    public function generateForRecommendation(RecommendationInterface $recommendation): string
    {
        $customerId = $recommendation->getCustomerId();
        $discountPercent = $recommendation->getDiscountPercent();

        if (!$discountPercent || $discountPercent <= 0) {
            throw new LocalizedException(__('Invalid discount percentage for recommendation'));
        }

        try {
            // 1. Create or find SalesRule for DUBORS
            $rule = $this->getOrCreateDuborsRule((float)$discountPercent);

            // 2. Generate unique coupon code
            $couponCode = $this->generateUniqueCode();

            // 3. Create coupon record
            $this->createCoupon($rule->getRuleId(), $couponCode, (int)$customerId);

            $this->logger->info('Generated coupon for recommendation', [
                'customer_id' => $customerId,
                'coupon_code' => $couponCode,
                'discount' => $discountPercent
            ]);

            return $couponCode;
        } catch (\Exception $e) {
            $this->logger->error('Failed to generate coupon', [
                'recommendation_id' => $recommendation->getId(),
                'error' => $e->getMessage()
            ]);
            throw new LocalizedException(__('Could not generate personalized coupon: %1', $e->getMessage()));
        }
    }

    /**
     * Get or create a SalesRule for the specific discount percentage
     *
     * @param float $discount
     * @return RuleInterface
     */
    private function getOrCreateDuborsRule(float $discount): RuleInterface
    {
        $ruleName = 'DUBORS Personalized Offer ' . (int)$discount . '%';

        // In a real production system, you'd probably search for existing rule first.
        // For simplicity in this phase, we create a rule. 
        // Note: To avoid duplicate rules, a more robust implementation would check by name.

        /** @var RuleInterface $rule */
        $rule = $this->ruleFactory->create();
        $rule->setName($ruleName);
        $rule->setIsActive(true);
        $rule->setSimpleAction(RuleInterface::DISCOUNT_ACTION_BY_PERCENT);
        $rule->setDiscountAmount($discount);
        $rule->setCouponType(RuleInterface::COUPON_TYPE_SPECIFIC_COUPON);
        $rule->setUseAutoGeneration(false); // We generate manually for more control
        $rule->setWebsiteIds([1]); // Default to first website, should be dynamic in multi-website setups
        $rule->setCustomerGroupIds([0, 1, 2, 3]); // NOT_LOGGED_IN and standard groups
        $rule->setStopRulesProcessing(false);
        $rule->setSortOrder(0);

        return $this->ruleRepository->save($rule);
    }

    /**
     * Generate a unique coupon code
     *
     * @return string
     */
    private function generateUniqueCode(): string
    {
        return self::COUPON_PREFIX . strtoupper($this->random->getRandomString(8));
    }

    /**
     * Create coupon for rule
     *
     * @param string|int $ruleId
     * @param string $code
     * @param int $customerId
     * @return void
     */
    private function createCoupon($ruleId, string $code, int $customerId): void
    {
        $validityDays = $this->config->getRecommendationValidityDays();
        $expirationDate = $this->dateTime->gmtDate('Y-m-d H:i:s', strtotime("+{$validityDays} days"));

        $coupon = $this->couponFactory->create();
        $coupon->setRuleId($ruleId);
        $coupon->setCode($code);
        $coupon->setUsageLimit(1);
        $coupon->setUsagePerCustomer(1);
        $coupon->setExpirationDate($expirationDate);
        $coupon->setType(0); // 0 = generated

        $this->couponRepository->save($coupon);
    }
}
