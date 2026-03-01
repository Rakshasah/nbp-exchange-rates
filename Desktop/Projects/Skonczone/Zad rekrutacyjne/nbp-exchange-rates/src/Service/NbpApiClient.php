<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\ExchangeRateDay;
use App\Enum\Currency;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Klient API Narodowego Banku Polskiego (NBP).
 *
 * Pobiera kursy walut z tabeli C (kupno/sprzedaż) w formacie XML
 * i przetwarza je na obiekty domenowe {@see ExchangeRateDay}.
 *
 * Tabela C zawiera kursy kupna i sprzedaży walut obcych,
 * publikowane w każdy dzień roboczy przez NBP.
 *
 * @see https://api.nbp.pl/ Dokumentacja API NBP
 */
class NbpApiClient
{
    /** @var string Bazowy URL endpointu kursów walut (tabela C) */
    private const BASE_URL = 'https://api.nbp.pl/api/exchangerates/rates/C';

    /**
     * @param HttpClientInterface $httpClient Klient HTTP wstrzykiwany przez kontener DI Symfony
     */
    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    /**
     * Pobiera kursy walut z API NBP dla podanej waluty i zakresu dat.
     *
     * Metoda wykonuje zapytanie GET do API NBP w formacie XML,
     * parsuje odpowiedź i oblicza różnice kursowe pomiędzy
     * kolejnymi dniami roboczymi.
     *
     * Różnica kursowa dla pierwszego dnia w zakresie wynosi null,
     * ponieważ brak jest dnia poprzedniego do porównania.
     *
     * @param Currency           $currency  Waluta, dla której pobieramy kursy
     * @param \DateTimeInterface $startDate Data początkowa zakresu (YYYY-MM-DD)
     * @param \DateTimeInterface $endDate   Data końcowa zakresu (YYYY-MM-DD)
     *
     * @return ExchangeRateDay[] Tablica obiektów z kursami i różnicami kursowymi
     *
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface Gdy wystąpi błąd komunikacji z API
     * @throws \Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface      Gdy API zwróci kod błędu HTTP
     */
    public function fetchRates(Currency $currency, \DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        $url = sprintf(
            '%s/%s/%s/%s/',
            self::BASE_URL,
            $currency->value,
            $startDate->format('Y-m-d'),
            $endDate->format('Y-m-d')
        );

        $response = $this->httpClient->request('GET', $url, [
            'query' => ['format' => 'xml'],
        ]);

        $xmlContent = $response->getContent();

        return $this->parseXmlResponse($xmlContent);
    }

    /**
     * Parsuje odpowiedź XML z API NBP i oblicza różnice kursowe.
     *
     * Przetwarza element <Rates> z odpowiedzi XML, wyciągając daty,
     * kursy kupna (Bid) i sprzedaży (Ask), a następnie oblicza
     * różnice pomiędzy kolejnymi dniami.
     *
     * @param string $xmlContent Surowa odpowiedź XML z API NBP
     *
     * @return ExchangeRateDay[] Przetworzona tablica obiektów kursowych
     *
     * @throws \RuntimeException Gdy parsowanie XML się nie powiedzie
     */
    private function parseXmlResponse(string $xmlContent): array
    {
        $xml = simplexml_load_string($xmlContent);

        if ($xml === false) {
            throw new \RuntimeException('Nie udało się sparsować odpowiedzi XML z API NBP.');
        }

        $rates = [];
        $previousBid = null;
        $previousAsk = null;

        foreach ($xml->Rates->Rate as $rate) {
            $currentBid = (float) $rate->Bid;
            $currentAsk = (float) $rate->Ask;
            $date       = (string) $rate->EffectiveDate;

            $bidDiff = $previousBid !== null ? $currentBid - $previousBid : null;
            $askDiff = $previousAsk !== null ? $currentAsk - $previousAsk : null;

            $rates[] = new ExchangeRateDay(
                date: $date,
                bid: $currentBid,
                ask: $currentAsk,
                bidDiff: $bidDiff,
                askDiff: $askDiff,
            );

            $previousBid = $currentBid;
            $previousAsk = $currentAsk;
        }

        return $rates;
    }
}
