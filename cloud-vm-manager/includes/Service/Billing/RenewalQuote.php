<?php

/**
 * Renewal quote.
 *
 * @package CloudVmManager
 */

declare(strict_types=1);

namespace CloudVmManager\Service\Billing;

use JsonSerializable;

defined('ABSPATH') || exit;

/**
 * What the backend says a renewal or an upgrade will cost.
 *
 * The figures come from the backend calculation endpoint, never from a local
 * sum, so the amount shown is the amount that will be charged.
 */
final class RenewalQuote implements JsonSerializable
{
    public const COUPON_APPLIED = 'APPLIED';

    /**
     * @var bool
     */
    private $available;

    /**
     * @var float
     */
    private $originalAmount;

    /**
     * @var float
     */
    private $discountAmount;

    /**
     * @var float
     */
    private $payableAmount;

    /**
     * @var string
     */
    private $couponStatus;

    /**
     * @var bool
     */
    private $specChange;

    /**
     * @var string
     */
    private $message;

    public function __construct(
        bool $available,
        float $originalAmount = 0.0,
        float $discountAmount = 0.0,
        float $payableAmount = 0.0,
        string $couponStatus = '',
        bool $specChange = false,
        string $message = ''
    ) {
        $this->available = $available;
        $this->originalAmount = round($originalAmount, 2);
        $this->discountAmount = round($discountAmount, 2);
        $this->payableAmount = round($payableAmount, 2);
        $this->couponStatus = $couponStatus;
        $this->specChange = $specChange;
        $this->message = $message;
    }

    public static function unavailable(string $message): self
    {
        return new self(false, 0.0, 0.0, 0.0, '', false, $message);
    }

    public function isAvailable(): bool
    {
        return $this->available;
    }

    public function originalAmount(): float
    {
        return $this->originalAmount;
    }

    public function discountAmount(): float
    {
        return $this->discountAmount;
    }

    public function payableAmount(): float
    {
        return $this->payableAmount;
    }

    public function couponStatus(): string
    {
        return $this->couponStatus;
    }

    public function isCouponApplied(): bool
    {
        return strtoupper($this->couponStatus) === self::COUPON_APPLIED;
    }

    /**
     * Whether the quote covers a change of specification and not just a term.
     */
    public function isSpecChange(): bool
    {
        return $this->specChange;
    }

    public function message(): string
    {
        return $this->message;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'available' => $this->available,
            'original_amount' => $this->originalAmount,
            'discount_amount' => $this->discountAmount,
            'payable_amount' => $this->payableAmount,
            'coupon_status' => $this->couponStatus,
            'coupon_applied' => $this->isCouponApplied(),
            'is_spec_change' => $this->specChange,
            'message' => $this->message,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
