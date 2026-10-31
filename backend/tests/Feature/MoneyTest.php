<?php

namespace Tests\Feature;

use App\Support\Currency;
use App\Support\Money;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Money is exact integer minor units in one currency; display comes from config/money.php.
 */
class MoneyTest extends TestCase
{
    public function test_decimal_input_is_parsed_exactly_without_floating_point(): void
    {
        $this->assertSame(1999, Money::parse('19.99', 'USD')->minor());
        $this->assertSame(30, Money::parse('0.1', 'USD')->plus(Money::parse('0.2', 'USD'))->minor());
        $this->assertSame(123450, Money::parse('1,234.5', 'USD')->minor());
        $this->assertSame(1850, Money::parse(18.5, 'USD')->minor());
        $this->assertSame(29, Money::parse(0.29, 'USD')->minor());
        $this->assertSame(1200, Money::parse(12, 'USD')->minor());
        $this->assertSame(13, Money::parse('0.125', 'USD')->minor(), 'extra decimals round half-up');
        $this->assertSame(-300, Money::parse('-3.004', 'USD')->minor());
        $this->assertSame('1234.50', Money::ofMinor(123450, 'USD')->toDecimalString());
        $this->assertSame('-0.05', Money::ofMinor(-5, 'USD')->toDecimalString());
    }

    public function test_percentages_round_half_up_to_the_cent(): void
    {
        $this->assertSame(83, Money::parse('10.00', 'USD')->percentage(825)->minor()); // 82.5 → 83
        $this->assertSame(82, Money::parse('9.99', 'USD')->percentage(825)->minor()); // 82.4175 → 82
        $this->assertSame(303, Money::ofMinor(6050, 'USD')->percentage(500)->minor()); // 302.5 → 303
        $this->assertSame(0, Money::ofMinor(0, 'USD')->percentage(10000)->minor());
    }

    public function test_currencies_never_mix_and_bad_input_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Money::parse('1', 'USD')->plus(Money::parse('1', 'JMD'));
    }

    public function test_bad_amounts_and_unknown_currencies_are_refused(): void
    {
        foreach (['abc', '1.2.3', '--1', '.'] as $bad) {
            try {
                Money::parse($bad, 'USD');
                $this->fail("'{$bad}' was accepted");
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
        $this->expectException(InvalidArgumentException::class);
        Currency::of('XYZ');
    }

    public function test_display_comes_from_configuration_not_code(): void
    {
        $this->assertSame('USD', Currency::defaultCode());
        $this->assertSame('$1,234.50', Money::ofMinor(123450, 'USD')->display());
        $this->assertSame('-$3.00', Money::ofMinor(-300, 'USD')->display());
        $this->assertSame('J$2,800.00', Money::formatMinor(280000, 'JMD'));

        config(['money.currencies.USD.symbol' => 'US$']);
        $this->assertSame('US$12.00', Money::formatMinor(1200, 'USD'));

        config(['money.currencies.EUR' => ['minor_unit' => 2, 'symbol' => '€', 'symbol_position' => 'after', 'decimal' => ',', 'thousands' => '.']]);
        $this->assertSame('1.234,50 €', Money::ofMinor(123450, 'EUR')->display());
        $this->assertContains('EUR', Currency::supportedCodes());
    }
}
