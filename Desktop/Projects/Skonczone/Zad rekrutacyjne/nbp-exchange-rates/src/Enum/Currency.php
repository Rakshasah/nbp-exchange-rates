<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Enum reprezentujący obsługiwane waluty z tabeli C NBP.
 *
 * Każdy case odpowiada kodowi ISO 4217 waluty
 * dostępnej w tabeli kursów kupna i sprzedaży NBP.
 */
enum Currency: string
{
    /** Euro */
    case EUR = 'EUR';

    /** Dolar amerykański */
    case USD = 'USD';

    /** Frank szwajcarski */
    case CHF = 'CHF';

    /**
     * Zwraca pełną, polską nazwę waluty.
     *
     * @return string Nazwa waluty w języku polskim
     */
    public function label(): string
    {
        return match ($this) {
            self::EUR => 'Euro',
            self::USD => 'Dolar amerykański',
            self::CHF => 'Frank szwajcarski',
        };
    }

    /**
     * Zwraca listę wszystkich obsługiwanych kodów walut.
     *
     * @return string[] Tablica kodów walut (np. ['EUR', 'USD', 'CHF'])
     */
    public static function allowedCodes(): array
    {
        return array_map(
            static fn (self $currency): string => $currency->value,
            self::cases()
        );
    }
}
