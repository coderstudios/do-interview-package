<?php

namespace CoderStudios\DoInterview\Support;

/**
 * An amount in millionths of a currency unit. Provider costs are fractions of a cent per
 * second, so whole pence or cents would round most of them to zero.
 */
final readonly class Money
{
    public function __construct(
        public int $micros,
        public string $currency = 'USD',
    ) {}

    public static function fromDecimal(float $amount, string $currency = 'USD'): self
    {
        return new self((int) round($amount * 1_000_000), $currency);
    }

    public function plus(self $other): self
    {
        if ($other->currency !== $this->currency) {
            throw new \InvalidArgumentException("Cannot add {$other->currency} to {$this->currency}.");
        }

        return new self($this->micros + $other->micros, $this->currency);
    }

    public function toDecimal(): float
    {
        return $this->micros / 1_000_000;
    }

    public function format(int $decimals = 2): string
    {
        $symbol = ['USD' => '$', 'GBP' => '£', 'EUR' => '€'][$this->currency] ?? $this->currency.' ';

        return $symbol.number_format($this->toDecimal(), $decimals);
    }
}
