<?php

declare(strict_types=1);

namespace App\Tests\Unit\DTO;

use App\DTO\ExchangeRateDay;
use PHPUnit\Framework\TestCase;

/**
 * Testy jednostkowe dla DTO {@see ExchangeRateDay}.
 *
 * Weryfikują poprawność inicjalizacji obiektu oraz konwersji
 * do tablicy (metoda toArray), w tym zaokrąglanie różnic kursowych.
 */
class ExchangeRateDayTest extends TestCase
{
    /**
     * Sprawdza, czy właściwości readonly są poprawnie ustawiane przez konstruktor.
     */
    public function testConstructorSetsProperties(): void
    {
        $dto = new ExchangeRateDay(
            date: '2025-02-17',
            bid: 4.4520,
            ask: 4.5430,
            bidDiff: 0.0120,
            askDiff: -0.0050,
        );

        $this->assertSame('2025-02-17', $dto->date);
        $this->assertSame(4.4520, $dto->bid);
        $this->assertSame(4.5430, $dto->ask);
        $this->assertSame(0.0120, $dto->bidDiff);
        $this->assertSame(-0.0050, $dto->askDiff);
    }

    /**
     * Sprawdza, czy toArray() zwraca poprawną strukturę z zaokrąglonymi wartościami.
     */
    public function testToArrayReturnsCorrectStructure(): void
    {
        $dto = new ExchangeRateDay(
            date: '2025-02-18',
            bid: 4.4600,
            ask: 4.5500,
            bidDiff: 0.00801234,
            askDiff: -0.00706789,
        );

        $result = $dto->toArray();

        $this->assertSame('2025-02-18', $result['date']);
        $this->assertSame(4.4600, $result['bid']);
        $this->assertSame(4.5500, $result['ask']);
        $this->assertSame(0.008, $result['bidDiff']);
        $this->assertSame(-0.0071, $result['askDiff']);
    }

    /**
     * Weryfikuje, że null-owe różnice kursowe (pierwszy dzień) są prawidłowo obsługiwane.
     */
    public function testToArrayWithNullDiffs(): void
    {
        $dto = new ExchangeRateDay(
            date: '2025-02-17',
            bid: 4.4520,
            ask: 4.5430,
            bidDiff: null,
            askDiff: null,
        );

        $result = $dto->toArray();

        $this->assertNull($result['bidDiff']);
        $this->assertNull($result['askDiff']);
    }
}
