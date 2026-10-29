<?php

namespace InventoryApp\Domain\Shared\ValueObjects;

use InvalidArgumentException;

/**
 * Immutable Money Value Object representing a monetary amount and currency.
 */
final class Money
{
    private const SUPPORTED_CURRENCIES = ['USD', 'EUR', 'GBP', 'CAD', 'AUD', 'JPY'];

    private int $amountInCents;
    private string $currency;

    public function __construct(int $amountInCents, string $currency = 'USD')
    {
        $currencyUpper = strtoupper(trim($currency));
        if (!in_array($currencyUpper, self::SUPPORTED_CURRENCIES, true)) {
            throw new InvalidArgumentException("Unsupported currency code: '{$currency}'.");
        }

        $this->amountInCents = $amountInCents;
        $this->currency = $currencyUpper;
    }

    public static function fromCents(int $amountInCents, string $currency = 'USD'): self
    {
        return new self($amountInCents, $currency);
    }

    public static function fromDollars(float $amountInDollars, string $currency = 'USD'): self
    {
        return new self((int) round($amountInDollars * 100), $currency);
    }

    public static function zero(string $currency = 'USD'): self
    {
        return new self(0, $currency);
    }

    public function getAmountInCents(): int
    {
        return $this->amountInCents;
    }

    public function getAmountInDollars(): float
    {
        return $this->amountInCents / 100.0;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function add(Money $other): self
    {
        $this->assertSameCurrency($other);
        return new self($this->amountInCents + $other->amountInCents, $this->currency);
    }

    public function subtract(Money $other): self
    {
        $this->assertSameCurrency($other);
        return new self($this->amountInCents - $other->amountInCents, $this->currency);
    }

    public function multiply(float|int $multiplier): self
    {
        return new self((int) round($this->amountInCents * $multiplier), $this->currency);
    }

    public function equals(Money $other): bool
    {
        return $this->amountInCents === $other->amountInCents && $this->currency === $other->currency;
    }

    public function isGreaterThan(Money $other): bool
    {
        $this->assertSameCurrency($other);
        return $this->amountInCents > $other->amountInCents;
    }

    public function isLessThan(Money $other): bool
    {
        $this->assertSameCurrency($other);
        return $this->amountInCents < $other->amountInCents;
    }

    public function isZero(): bool
    {
        return $this->amountInCents === 0;
    }

    public function isNegative(): bool
    {
        return $this->amountInCents < 0;
    }

    public function format(): string
    {
        $formatted = number_format(abs($this->amountInCents) / 100, 2);
        $prefix = $this->amountInCents < 0 ? '-' : '';
        return "{$prefix}{$this->currency} {$formatted}";
    }

    public function __toString(): string
    {
        return $this->format();
    }

    private function assertSameCurrency(Money $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException(
                "Currency mismatch: cannot operate on '{$this->currency}' and '{$other->currency}'."
            );
        }
    }
}
