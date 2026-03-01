<?php

declare(strict_types=1);

namespace App\Controller;

use App\Enum\Currency;
use App\Service\NbpApiClient;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Kontroler REST API obsługujący zapytania o kursy walut z tabeli C NBP.
 *
 * Udostępnia endpoint GET /api/exchange-rates, który przyjmuje parametry:
 * - currency  — kod waluty (EUR, USD lub CHF),
 * - startDate — data początkowa zakresu (format YYYY-MM-DD),
 * - endDate   — data końcowa zakresu (format YYYY-MM-DD).
 *
 * Maksymalny dopuszczalny zakres dat wynosi 7 dni.
 * Każde zapytanie wymaga nagłówka X-TOKEN-SYSTEM (walidowanego przez {@see \App\EventSubscriber\TokenSubscriber}).
 */
class ExchangeRateController extends AbstractController
{
    /** @var int Maksymalny dozwolony zakres dat w dniach */
    private const MAX_DATE_RANGE_DAYS = 7;

    /**
     * @param NbpApiClient $nbpApiClient Serwis komunikujący się z API NBP
     */
    public function __construct(
        private readonly NbpApiClient $nbpApiClient,
    ) {
    }

    /**
     * Pobiera kursy wymiany waluty z tabeli C NBP dla zadanego zakresu dat.
     *
     * Endpoint waliduje parametry wejściowe (walutę, format dat, zakres)
     * i zwraca tablicę obiektów JSON zawierających:
     * - date    — data kursu,
     * - bid     — kurs kupna,
     * - ask     — kurs sprzedaży,
     * - bidDiff — różnica kursu kupna względem dnia poprzedniego (null dla pierwszego dnia),
     * - askDiff — różnica kursu sprzedaży względem dnia poprzedniego (null dla pierwszego dnia).
     *
     * @param Request $request Obiekt żądania HTTP z parametrami query string
     *
     * @return JsonResponse Odpowiedź JSON z kursami lub komunikatem o błędzie
     */
    #[Route(
        path: '/api/exchange-rates',
        name: 'api_exchange_rates',
        methods: ['GET'],
    )]
    #[OA\Get(
        summary: 'Pobierz kursy walut z tabeli C NBP',
        description: 'Zwraca kursy kupna i sprzedaży wybranej waluty z tabeli C NBP dla podanego zakresu dat (max 7 dni). Zawiera różnice kursowe względem dnia poprzedniego.',
    )]
    #[OA\Parameter(
        name: 'currency',
        description: 'Kod waluty (EUR, USD lub CHF)',
        in: 'query',
        required: true,
        schema: new OA\Schema(type: 'string', enum: ['EUR', 'USD', 'CHF']),
        example: 'EUR',
    )]
    #[OA\Parameter(
        name: 'startDate',
        description: 'Data początkowa zakresu (format YYYY-MM-DD)',
        in: 'query',
        required: true,
        schema: new OA\Schema(type: 'string', format: 'date'),
        example: '2025-02-17',
    )]
    #[OA\Parameter(
        name: 'endDate',
        description: 'Data końcowa zakresu (format YYYY-MM-DD)',
        in: 'query',
        required: true,
        schema: new OA\Schema(type: 'string', format: 'date'),
        example: '2025-02-21',
    )]
    #[OA\Response(
        response: 200,
        description: 'Lista kursów walut z różnicami kursowymi',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(
                properties: [
                    new OA\Property(property: 'date', type: 'string', format: 'date', example: '2025-02-17'),
                    new OA\Property(property: 'bid', type: 'number', format: 'float', example: 4.4520),
                    new OA\Property(property: 'ask', type: 'number', format: 'float', example: 4.5430),
                    new OA\Property(property: 'bidDiff', type: 'number', format: 'float', example: 0.0120, nullable: true),
                    new OA\Property(property: 'askDiff', type: 'number', format: 'float', example: -0.0050, nullable: true),
                ],
                type: 'object'
            ),
        ),
    )]
    #[OA\Response(
        response: 400,
        description: 'Błąd walidacji parametrów wejściowych',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'error', type: 'string', example: 'Nieprawidłowy parametr "currency". Dozwolone wartości: EUR, USD, CHF.'),
            ],
            type: 'object'
        ),
    )]
    #[OA\Response(
        response: 401,
        description: 'Brak lub nieprawidłowy token X-TOKEN-SYSTEM',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'error', type: 'string', example: 'Brak wymaganego nagłówka X-TOKEN-SYSTEM.'),
            ],
            type: 'object'
        ),
    )]
    #[OA\Response(
        response: 500,
        description: 'Błąd wewnętrzny — problem z komunikacją z API NBP',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'error', type: 'string', example: 'Wystąpił błąd podczas pobierania danych z API NBP.'),
            ],
            type: 'object'
        ),
    )]
    public function index(Request $request): JsonResponse
    {
        // --- Walidacja parametru: currency ---
        $currencyCode = $request->query->get('currency');

        if ($currencyCode === null || $currencyCode === '') {
            return $this->errorResponse('Parametr "currency" jest wymagany.', Response::HTTP_BAD_REQUEST);
        }

        $currency = Currency::tryFrom(strtoupper($currencyCode));

        if ($currency === null) {
            return $this->errorResponse(
                sprintf(
                    'Nieprawidłowy parametr "currency". Dozwolone wartości: %s.',
                    implode(', ', Currency::allowedCodes())
                ),
                Response::HTTP_BAD_REQUEST
            );
        }

        // --- Walidacja parametrów: startDate i endDate ---
        $startDateRaw = $request->query->get('startDate');
        $endDateRaw   = $request->query->get('endDate');

        if ($startDateRaw === null || $startDateRaw === '') {
            return $this->errorResponse('Parametr "startDate" jest wymagany.', Response::HTTP_BAD_REQUEST);
        }

        if ($endDateRaw === null || $endDateRaw === '') {
            return $this->errorResponse('Parametr "endDate" jest wymagany.', Response::HTTP_BAD_REQUEST);
        }

        $startDate = \DateTimeImmutable::createFromFormat('Y-m-d', $startDateRaw);
        $endDate   = \DateTimeImmutable::createFromFormat('Y-m-d', $endDateRaw);

        if ($startDate === false) {
            return $this->errorResponse(
                'Nieprawidłowy format daty "startDate". Wymagany format: YYYY-MM-DD.',
                Response::HTTP_BAD_REQUEST
            );
        }

        if ($endDate === false) {
            return $this->errorResponse(
                'Nieprawidłowy format daty "endDate". Wymagany format: YYYY-MM-DD.',
                Response::HTTP_BAD_REQUEST
            );
        }

        // Normalizujemy czas do północy, aby porównanie dat działało poprawnie
        $startDate = $startDate->setTime(0, 0);
        $endDate   = $endDate->setTime(0, 0);

        if ($startDate > $endDate) {
            return $this->errorResponse(
                'Data "startDate" nie może być późniejsza niż "endDate".',
                Response::HTTP_BAD_REQUEST
            );
        }

        // --- Walidacja zakresu dat: max 7 dni ---
        $diffDays = (int) $startDate->diff($endDate)->days;

        if ($diffDays > self::MAX_DATE_RANGE_DAYS) {
            return $this->errorResponse(
                sprintf('Maksymalny zakres dat to %d dni.', self::MAX_DATE_RANGE_DAYS),
                Response::HTTP_BAD_REQUEST
            );
        }

        // --- Pobranie danych z API NBP ---
        try {
            $rates = $this->nbpApiClient->fetchRates($currency, $startDate, $endDate);
        } catch (\Throwable $e) {
            return $this->errorResponse(
                'Wystąpił błąd podczas pobierania danych z API NBP.',
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }

        // Konwertujemy obiekty DTO na tablice gotowe do serializacji JSON
        $data = array_map(
            static fn ($rate) => $rate->toArray(),
            $rates
        );

        return new JsonResponse($data, Response::HTTP_OK);
    }

    /**
     * Tworzy ustandaryzowaną odpowiedź błędu w formacie JSON.
     *
     * @param string $message    Treść komunikatu błędu
     * @param int    $statusCode Kod statusu HTTP
     *
     * @return JsonResponse Odpowiedź JSON z polem "error"
     */
    private function errorResponse(string $message, int $statusCode): JsonResponse
    {
        return new JsonResponse(['error' => $message], $statusCode);
    }
}
