<?php
/**
 * BrainStation23
 *
 * @category    BrainStation23
 * @package     BrainStation23_Dubors
 * @copyright   Copyright (c) 2026 BrainStation23
 */

declare(strict_types=1);

namespace BrainStation23\Dubors\Test\Unit\Service;

use BrainStation23\Dubors\Api\Data\RecommendationInterface;
use BrainStation23\Dubors\Helper\Config;
use BrainStation23\Dubors\Service\CouponGenerator;
use Magento\Framework\Math\Random;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\SalesRule\Api\CouponRepositoryInterface;
use Magento\SalesRule\Api\Data\CouponInterface;
use Magento\SalesRule\Api\Data\CouponInterfaceFactory;
use Magento\SalesRule\Api\Data\RuleInterface;
use Magento\SalesRule\Api\Data\RuleInterfaceFactory;
use Magento\SalesRule\Api\RuleRepositoryInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class CouponGeneratorTest extends TestCase
{
    private CouponGenerator $couponGenerator;
    private RuleInterfaceFactory|MockObject $ruleFactory;
    private RuleRepositoryInterface|MockObject $ruleRepository;
    private CouponInterfaceFactory|MockObject $couponFactory;
    private CouponRepositoryInterface|MockObject $couponRepository;
    private Random|MockObject $random;
    private DateTime|MockObject $dateTime;
    private Config|MockObject $config;
    private LoggerInterface|MockObject $logger;

    protected function setUp(): void
    {
        $this->ruleFactory = $this->createMock(RuleInterfaceFactory::class);
        $this->ruleRepository = $this->createMock(RuleRepositoryInterface::class);
        $this->couponFactory = $this->createMock(CouponInterfaceFactory::class);
        $this->couponRepository = $this->createMock(CouponRepositoryInterface::class);
        $this->random = $this->createMock(Random::class);
        $this->dateTime = $this->createMock(DateTime::class);
        $this->config = $this->createMock(Config::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->couponGenerator = new CouponGenerator(
            $this->ruleFactory,
            $this->ruleRepository,
            $this->couponFactory,
            $this->couponRepository,
            $this->random,
            $this->dateTime,
            $this->config,
            $this->logger
        );
    }

    public function testGenerateForRecommendation(): void
    {
        $recommendation = $this->createMock(RecommendationInterface::class);
        $recommendation->method('getCustomerId')->willReturn(123);
        $recommendation->method('getDiscountPercent')->willReturn(15.0);

        $rule = $this->createMock(RuleInterface::class);
        $rule->method('getRuleId')->willReturn(1);
        $this->ruleFactory->method('create')->willReturn($rule);
        $this->ruleRepository->method('save')->willReturn($rule);

        $this->random->method('getRandomString')->willReturn('ABCDEFGH');

        $coupon = $this->createMock(CouponInterface::class);
        $this->couponFactory->method('create')->willReturn($coupon);
        
        $this->config->method('getRecommendationValidityDays')->willReturn(30);
        $this->dateTime->method('gmtDate')->willReturn('2026-06-08 00:00:00');

        $result = $this->couponGenerator->generateForRecommendation($recommendation);

        $this->assertEquals('DUBORS-ABCDEFGH', $result);
    }

    public function testGenerateForRecommendationWithInvalidDiscount(): void
    {
        $recommendation = $this->createMock(RecommendationInterface::class);
        $recommendation->method('getDiscountPercent')->willReturn(0.0);

        $this->expectException(\Magento\Framework\Exception\LocalizedException::class);
        $this->couponGenerator->generateForRecommendation($recommendation);
    }
}
