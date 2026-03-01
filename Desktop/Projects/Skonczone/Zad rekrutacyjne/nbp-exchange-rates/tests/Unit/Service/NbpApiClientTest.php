<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\DTO\ExchangeRateDay;
use App\Enum\Currency;
use App\Service\NbpApiClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Testy jednostkowe dla serwisu {@see NbpApiClient}.
 *
 * Wykorzystują MockHttpClient do symulowania odpowiedzi API NBP
 * bez faktycznego wykonywania zapytań sieciowych.
 */
class NbpApiClientTest extends TestCase
{
    /**
     * Przykładowa odpowiedź XML zawierająca 3 dni kursowe.
     *
     * Dane odzwierciedlają prawdziwą strukturę tabeli C NBP
     * z kursami kupna (Bid) i sprzedaży (Ask) dla EUR.
     */
    private const SAMPLE_XML = <<<'XML'
        <?xml version="1.0" encoding="utf-8"?>
        <ExchangeRatesSeries>
            <Table>C</Table>
            <Currency>euro</Currency>
            <Code>EUR</Code>
            <Rates>
                <Rate>
                    <No>033/C/NBP/2025</No>
                    <EffectiveDate>2025-02-17</EffectiveDate>
                    <Bid>4.4520</Bid>
                    <Ask>4.5430</Ask>
                </Rate>
                <Rate>
                    <No>034/C/NBP/2025</No>
                    <EffectiveDate>2025-02-18</EffectiveDate>
                    <Bid>4.4600</Bid>
                    <Ask>4.5400</Ask>
                </Rate>
                <Rate>
                    <No>035/C/NBP/2025</No>
                    <EffectiveDate>2025-02-19</EffectiveDate>
                    <Bid>4.4700</Bid>
                    <Ask>4.5500</Ask>
                </Rate>
            </Rates>
        </ExchangeRatesSeries>
        XML;

    /**
     * Sprawdza, czy klient prawidłowo parsuje XML i zwraca poprawną liczbę rekordów.
     */
    public function testFetchRatesReturnsCorrectNumberOfRates(): void
    {
        $client = $this->createClientWithXmlResponse(self::SAMPLE_XML);

        $rates = $client->fetchRates(
            Currency::EUR,
            new \DateTimeImmutable('2025-02-17'),
            new \DateTimeImmutable('2025-02-19')
        );

        $this->assertCount(3, $rates);
    }

    /**
     * Weryfikuje, że każdy zwrócony element jest instancją ExchangeRateDay.
     */
    public function testFetchRatesReturnsExchangeRateDayInstances(): void
    {
        $client = $this->createClientWithXmlResponse(self::SAMPLE_XML);

        $rates = $client->fetchRates(
            Currency::EUR,
            new \DateTimeImmutable('2025-02-17'),
            new \DateTimeImmutable('2025-02-19')
        );

        foreach ($rates as $rate) {
            $this->assertInstanceOf(ExchangeRateDay::class, $rate);
        }
    }

    /**
     * Sprawdza poprawność kursów kupna i sprzedaży parsowanych z XML.
     */
    public function testFetchRatesParsesBidAndAskCorrectly(): void
    {
        $client = $this->createClientWithXmlResponse(self::SAMPLE_XML);

        $rates = $client->fetchRates(
            Currency::EUR,
            new \DateTimeImmutable('2025-02-17'),
            new \DateTimeImmutable('2025-02-19')
        );

        // Pierwszy dzień
        $this->assertSame(4.4520, $rates[0]->bid);
        $this->assertSame(4.5430, $rates[0]->ask);

        // Drugi dzień
        $this->assertSame(4.4600, $rates[1]->bid);
        $this->assertSame(4.5400, $rates[1]->ask);

        // Trzeci dzień
        $this->assertSame(4.4700, $rates[2]->bid);
        $this->assertSame(4.5500, $rates[2]->ask);
    }

    /**
     * Weryfikuje poprawność dat parsowanych z elementów EffectiveDate.
     */
    public function testFetchRatesParseDatesCorrectly(): void
    {
        $client = $this->createClientWithXmlResponse(self::SAMPLE_XML);

        $rates = $client->fetchRates(
            Currency::EUR,
            new \DateTimeImmutable('2025-02-17'),
            new \DateTimeImmutable('2025-02-19')
        );

        $this->assertSame('2025-02-17', $rates[0]->date);
        $this->assertSame('2025-02-18', $rates[1]->date);
        $this->assertSame('2025-02-19', $rates[2]->date);
    }

    /**
     * Sprawdza, czy różnice kursowe dla pierwszego dnia wynoszą null.
     *
     * Pierwszy dzień nie ma dnia poprzedniego do porównania,
     * więc bidDiff i askDiff powinny być null.
     */
    public function testFirstDayDiffIsNull(): void
    {
        $client = $this->createClientWithXmlResponse(self::SAMPLE_XML);

        $rates = $client->fetchRates(
            Currency::EUR,
            new \DateTimeImmutable('2025-02-17'),
            new \DateTimeImmutable('2025-02-19')
        );

        $this->assertNull($rates[0]->bidDiff);
        $this->assertNull($rates[0]->askDiff);
    }

    /**
     * Weryfikuje obliczanie różnic kursowych pomiędzy kolejnymi dniami.
     *
     * Oczekiwane różnice:
     * - Dzień 2: bidDiff = 4.4600 - 4.4520 = 0.008, askDiff = 4.5400 - 4.5430 = -0.003
     * - Dzień 3: bidDiff = 4.4700 - 4.4600 = 0.01, askDiff = 4.5500 - 4.5400 = 0.01
     */
    public function testSubsequentDaysDiffIsCalculatedCorrectly(): void
    {
        $client = $this->createClientWithXmlResponse(self::SAMPLE_XML);

        $rates = $client->fetchRates(
            Currency::EUR,
            new \DateTimeImmutable('2025-02-17'),
            new \DateTimeImmutable('2025-02-19')
        );

        // Dzień 2: 4.4600 - 4.4520 = 0.008
        $this->assertEqualsWithDelta(0.008, $rates[1]->bidDiff, 0.00001);
        // Dzień 2: 4.5400 - 4.5430 = -0.003
        $this->assertEqualsWithDelta(-0.003, $rates[1]->askDiff, 0.00001);

        // Dzień 3: 4.4700 - 4.4600 = 0.01
        $this->assertEqualsWithDelta(0.01, $rates[2]->bidDiff, 0.00001);
        // Dzień 3: 4.5500 - 4.5400 = 0.01
        $this->assertEqualsWithDelta(0.01, $rates[2]->askDiff, 0.00001);
    }

    /**
     * Sprawdza zachowanie klienta dla odpowiedzi zawierającej tylko jeden dzień.
     * Jedyny dzień powinien mieć null-owe różnice kursowe.
     */
    public function testSingleDayResponseReturnsNullDiffs(): void
    {
        $xml = <<<'XML'
            <?xml version="1.0" encoding="utf-8"?>
            <ExchangeRatesSeries>
                <Table>C</Table>
                <Currency>euro</Currency>
                <Code>EUR</Code>
                <Rates>
                    <Rate>
                        <No>033/C/NBP/2025</No>
                        <EffectiveDate>2025-02-17</EffectiveDate>
                        <Bid>4.4520</Bid>
                        <Ask>4.5430</Ask>
                    </Rate>
                </Rates>
            </ExchangeRatesSeries>
            XML;

        $client = $this->createClientWithXmlResponse($xml);

        $rates = $client->fetchRates(
            Currency::EUR,
            new \DateTimeImmutable('2025-02-17'),
            new \DateTimeImmutable('2025-02-17')
        );

        $this->assertCount(1, $rates);
        $this->assertNull($rates[0]->bidDiff);
        $this->assertNull($rates[0]->askDiff);
    }

    /**
     * Weryfikuje, że nieprawidłowa odpowiedź XML powoduje wyjątek RuntimeException.
     */
    public function testInvalidXmlThrowsRuntimeException(): void
    {
        $this->expectException(\RuntimeException::class);

        $client = $this->createClientWithXmlResponse('not valid xml <><>');

        $client->fetchRates(
            Currency::EUR,
            new \DateTimeImmutable('2025-02-17'),
            new \DateTimeImmutable('2025-02-17')
        );
    }

    /**
     * Tworzy instancję NbpApiClient z mockiem HTTP zwracającym podaną odpowiedź XML.
     *
     * @param string $xmlResponse Treść XML symulowanej odpowiedzi API NBP
     *
     * @return NbpApiClient Klient z zamockowanym transportem HTTP
     */
    private function createClientWithXmlResponse(string $xmlResponse): NbpApiClient
    {
        $mockResponse = new MockResponse($xmlResponse, [
            'http_code'        => 200,
            'response_headers' => ['Content-Type' => 'application/xml'],
        ]);

        $httpClient = new MockHttpClient($mockResponse);

        return new NbpApiClient($httpClient);
    }
}
