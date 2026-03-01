<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * Obiekt transferu danych (DTO) reprezentujący kurs wymiany waluty dla pojedynczego dnia.
 *
 * Zawiera datę, kurs kupna i sprzedaży oraz opcjonalne różnice kursowe
 * w stosunku do dnia poprzedniego. Obiekt jest niemutowalny (readonly).
 */
final readonly class ExchangeRateDay
{
    /**
     * @param string     $date    Data kursu w formacie YYYY-MM-DD
     * @param float      $bid     Kurs kupna waluty (cena, po której NBP kupuje walutę)
     * @param float      $ask     Kurs sprzedaży waluty (cena, po której NBP sprzedaje walutę)
     * @param float|null $bidDiff Różnica kursu kupna w stosunku do dnia poprzedniego (null dla pierwszego dnia)
     * @param float|null $askDiff Różnica kursu sprzedaży w stosunku do dnia poprzedniego (null dla pierwszego dnia)
     */
    public function __construct(
        public string $date,
        public float $bid,
        public float $ask,
        public ?float $bidDiff,
        public ?float $askDiff,
    ) {
    }

    /**
     * Konwertuje obiekt do tablicy asocjacyjnej gotowej do serializacji JSON.
     *
     * Różnice kursowe są zaokrąglane do 4 miejsc po przecinku,
     * co odpowiada precyzji kursów NBP.
     *
     * @return array{
     *     date: string,
     *     bid: float,
     *     ask: float,
     *     bidDiff: float|null,
     *     askDiff: float|null
     * }
     */
    public function toArray(): array
    {
        return [
            'date'    => $this->date,
            'bid'     => $this->bid,
            'ask'     => $this->ask,
            'bidDiff' => $this->bidDiff !== null ? round($this->bidDiff, 4) : null,
            'askDiff' => $this->askDiff !== null ? round($this->askDiff, 4) : null,
        ];
    }
}
