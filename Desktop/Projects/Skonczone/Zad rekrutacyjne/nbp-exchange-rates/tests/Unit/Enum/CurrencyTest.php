<?php

declare(strict_types=1);

namespace App\Tests\Unit\Enum;

use App\Enum\Currency;
use PHPUnit\Framework\TestCase;

/**
 * Testy jednostkowe dla enuma {@see Currency}.
 *
 * Weryfikują poprawność definicji case'ów, wartości backingowych,
 * metod pomocniczych (label, allowedCodes) oraz odporność na
 * nieprawidłowe wartości wejściowe.
 */
class CurrencyTest extends TestCase
{
    /**
     * Sprawdza, czy enum posiada dokładnie trzy oczekiwane case'y.
     */
    public function testEnumHasThreeCases(): void
    {
        $cases = Currency::cases();

        $this->assertCount(3, $cases);
    }

    /**
     * Weryfikuje, że wartości backingowe odpowiadają kodom ISO 4217.
     *
     * @dataProvider currencyCodeProvider
     */
    public function testEnumBackingValues(string $expectedValue, Currency $case): void
    {
        $this->assertSame($expectedValue, $case->value);
    }

    /**
     * Sprawdza, czy metoda label() zwraca polskie nazwy walut.
     *
     * @dataProvider currencyLabelProvider
     */
    public function testLabelReturnsPolishName(string $expectedLabel, Currency $case): void
    {
        $this->assertSame($expectedLabel, $case->label());
    }

    /**
     * Weryfikuje, że allowedCodes() zwraca wszystkie trzy kody walut.
     */
    public function testAllowedCodesReturnsAllCurrencyCodes(): void
    {
        $codes = Currency::allowedCodes();

        $this->assertSame(['EUR', 'USD', 'CHF'], $codes);
    }

    /**
     * Sprawdza, czy tryFrom() poprawnie mapuje stringi na case'y enuma.
     */
    public function testTryFromWithValidCode(): void
    {
        $this->assertSame(Currency::EUR, Currency::tryFrom('EUR'));
        $this->assertSame(Currency::USD, Currency::tryFrom('USD'));
        $this->assertSame(Currency::CHF, Currency::tryFrom('CHF'));
    }

    /**
     * Weryfikuje, że tryFrom() zwraca null dla nieobsługiwanych walut.
     */
    public function testTryFromWithInvalidCodeReturnsNull(): void
    {
        $this->assertNull(Currency::tryFrom('GBP'));
        $this->assertNull(Currency::tryFrom(''));
        $this->assertNull(Currency::tryFrom('eur'));
    }

    /**
     * Dostarcza pary: (oczekiwana wartość, case enuma).
     *
     * @return iterable<string, array{string, Currency}>
     */
    public static function currencyCodeProvider(): iterable
    {
        yield 'EUR' => ['EUR', Currency::EUR];
        yield 'USD' => ['USD', Currency::USD];
        yield 'CHF' => ['CHF', Currency::CHF];
    }

    /**
     * Dostarcza pary: (oczekiwana etykieta, case enuma).
     *
     * @return iterable<string, array{string, Currency}>
     */
    public static function currencyLabelProvider(): iterable
    {
        yield 'Euro' => ['Euro', Currency::EUR];
        yield 'Dolar amerykański' => ['Dolar amerykański', Currency::USD];
        yield 'Frank szwajcarski' => ['Frank szwajcarski', Currency::CHF];
    }
}
