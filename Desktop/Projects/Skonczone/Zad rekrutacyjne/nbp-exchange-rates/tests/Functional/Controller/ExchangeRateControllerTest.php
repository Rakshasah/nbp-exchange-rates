<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Testy funkcjonalne dla {@see \App\Controller\ExchangeRateController}.
 *
 * Testy weryfikują pełny cykl obsługi żądania HTTP:
 * od walidacji nagłówka tokena, przez walidację parametrów wejściowych,
 * aż po integrację z serwisem NBP.
 *
 * Środowisko testowe korzysta z tokena zdefiniowanego w .env.test.
 */
class ExchangeRateControllerTest extends WebTestCase
{
    /** @var string Token używany w środowisku testowym (.env.test) */
    private const TEST_TOKEN = 'test-token-value';

    /** @var string Bazowy URL endpointu */
    private const ENDPOINT = '/api/exchange-rates';

    /**
     * Sprawdza, że żądanie bez nagłówka X-TOKEN-SYSTEM zwraca 401.
     */
    public function testMissingTokenReturns401(): void
    {
        $client = static::createClient();

        $client->request('GET', self::ENDPOINT, [
            'currency'  => 'EUR',
            'startDate' => '2025-02-17',
            'endDate'   => '2025-02-21',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);

        $json = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('error', $json);
    }

    /**
     * Weryfikuje, że nieprawidłowy token zwraca 401.
     */
    public function testInvalidTokenReturns401(): void
    {
        $client = static::createClient();

        $client->request('GET', self::ENDPOINT, [
            'currency'  => 'EUR',
            'startDate' => '2025-02-17',
            'endDate'   => '2025-02-21',
        ], [], [
            'HTTP_X_TOKEN_SYSTEM' => 'wrong-token',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    /**
     * Sprawdza, że brak parametru currency zwraca 400.
     */
    public function testMissingCurrencyReturns400(): void
    {
        $client = static::createClient();

        $client->request('GET', self::ENDPOINT, [
            'startDate' => '2025-02-17',
            'endDate'   => '2025-02-21',
        ], [], [
            'HTTP_X_TOKEN_SYSTEM' => self::TEST_TOKEN,
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);

        $json = json_decode($client->getResponse()->getContent(), true);
        $this->assertStringContainsString('currency', $json['error']);
    }

    /**
     * Weryfikuje, że nieobsługiwana waluta (np. GBP) zwraca 400.
     */
    public function testInvalidCurrencyReturns400(): void
    {
        $client = static::createClient();

        $client->request('GET', self::ENDPOINT, [
            'currency'  => 'GBP',
            'startDate' => '2025-02-17',
            'endDate'   => '2025-02-21',
        ], [], [
            'HTTP_X_TOKEN_SYSTEM' => self::TEST_TOKEN,
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);

        $json = json_decode($client->getResponse()->getContent(), true);
        $this->assertStringContainsString('EUR, USD, CHF', $json['error']);
    }

    /**
     * Sprawdza, że brak parametru startDate zwraca 400.
     */
    public function testMissingStartDateReturns400(): void
    {
        $client = static::createClient();

        $client->request('GET', self::ENDPOINT, [
            'currency' => 'EUR',
            'endDate'  => '2025-02-21',
        ], [], [
            'HTTP_X_TOKEN_SYSTEM' => self::TEST_TOKEN,
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);

        $json = json_decode($client->getResponse()->getContent(), true);
        $this->assertStringContainsString('startDate', $json['error']);
    }

    /**
     * Sprawdza, że brak parametru endDate zwraca 400.
     */
    public function testMissingEndDateReturns400(): void
    {
        $client = static::createClient();

        $client->request('GET', self::ENDPOINT, [
            'currency'  => 'EUR',
            'startDate' => '2025-02-17',
        ], [], [
            'HTTP_X_TOKEN_SYSTEM' => self::TEST_TOKEN,
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);

        $json = json_decode($client->getResponse()->getContent(), true);
        $this->assertStringContainsString('endDate', $json['error']);
    }

    /**
     * Weryfikuje, że nieprawidłowy format daty (DD-MM-YYYY zamiast YYYY-MM-DD) zwraca 400.
     */
    public function testInvalidDateFormatReturns400(): void
    {
        $client = static::createClient();

        $client->request('GET', self::ENDPOINT, [
            'currency'  => 'EUR',
            'startDate' => '17-02-2025',
            'endDate'   => '21-02-2025',
        ], [], [
            'HTTP_X_TOKEN_SYSTEM' => self::TEST_TOKEN,
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);

        $json = json_decode($client->getResponse()->getContent(), true);
        $this->assertStringContainsString('YYYY-MM-DD', $json['error']);
    }

    /**
     * Sprawdza, że zakres dat dłuższy niż 7 dni zwraca 400.
     */
    public function testDateRangeExceedingSevenDaysReturns400(): void
    {
        $client = static::createClient();

        $client->request('GET', self::ENDPOINT, [
            'currency'  => 'EUR',
            'startDate' => '2025-02-01',
            'endDate'   => '2025-02-15',
        ], [], [
            'HTTP_X_TOKEN_SYSTEM' => self::TEST_TOKEN,
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);

        $json = json_decode($client->getResponse()->getContent(), true);
        $this->assertStringContainsString('7', $json['error']);
    }

    /**
     * Weryfikuje, że startDate późniejszy niż endDate zwraca 400.
     */
    public function testStartDateAfterEndDateReturns400(): void
    {
        $client = static::createClient();

        $client->request('GET', self::ENDPOINT, [
            'currency'  => 'EUR',
            'startDate' => '2025-02-25',
            'endDate'   => '2025-02-20',
        ], [], [
            'HTTP_X_TOKEN_SYSTEM' => self::TEST_TOKEN,
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);

        $json = json_decode($client->getResponse()->getContent(), true);
        $this->assertStringContainsString('startDate', $json['error']);
    }

    /**
     * Sprawdza, że parametr currency jest case-insensitive (eur → EUR).
     *
     * Test wykonuje prawdziwe zapytanie do API NBP — wymaga połączenia z internetem.
     * Używamy odległych dat, aby zapewnić dostępność danych.
     */
    public function testCurrencyIsCaseInsensitive(): void
    {
        $client = static::createClient();

        $client->request('GET', self::ENDPOINT, [
            'currency'  => 'eur',
            'startDate' => '2025-01-06',
            'endDate'   => '2025-01-07',
        ], [], [
            'HTTP_X_TOKEN_SYSTEM' => self::TEST_TOKEN,
        ]);

        // Akceptujemy zarówno 200 (sukces) jak i 500 (problem z połączeniem NBP w CI)
        $statusCode = $client->getResponse()->getStatusCode();
        $this->assertTrue(
            in_array($statusCode, [200, 500], true),
            sprintf('Oczekiwano 200 lub 500, otrzymano %d', $statusCode)
        );
    }
}
