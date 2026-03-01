# NBP Exchange Rates API

REST API do pobierania kursów walut z **tabeli C** Narodowego Banku Polskiego (NBP).
Obsługiwane waluty: **EUR**, **USD**, **CHF**.

Stworzony w PHP 8.2+ z użyciem frameworka Symfony 6.4.

---

## Wymagania

- PHP ≥ 8.2
- Composer 2.x
- Rozszerzenia PHP: `ext-simplexml`, `ext-ctype`, `ext-iconv`

## Instalacja

```bash
# 1. Klonowanie repozytorium
git clone git@github.com:<twoj-login>/nbp-exchange-rates.git
cd nbp-exchange-rates

# 2. Instalacja zależności
composer install

# 3. Konfiguracja tokena (opcjonalnie zmień wartość)
# Edytuj plik .env lub utwórz .env.local:
# X_TOKEN_SYSTEM=twoja-wartość-tokena
```

## Uruchamianie

```bash
# Serwer deweloperski Symfony
symfony serve

# Alternatywnie — wbudowany serwer PHP
php -S localhost:8000 -t public/
```

## Endpoint

### `GET /api/exchange-rates`

Pobiera kursy kupna i sprzedaży waluty z tabeli C NBP.

#### Nagłówki

| Nagłówek        | Wymagany | Opis                              |
|-----------------|----------|-----------------------------------|
| X-TOKEN-SYSTEM  | ✅        | Token uwierzytelniający z `.env`  |

#### Parametry query string

| Parametr   | Typ    | Wymagany | Opis                                        |
|------------|--------|----------|---------------------------------------------|
| currency   | string | ✅        | Kod waluty: `EUR`, `USD` lub `CHF`           |
| startDate  | string | ✅        | Data początkowa (format `YYYY-MM-DD`)        |
| endDate    | string | ✅        | Data końcowa (format `YYYY-MM-DD`)           |

> **Uwaga:** Maksymalny zakres dat to **7 dni**.

#### Przykłady

```bash
# Poprawne zapytanie — kursy EUR z 5 dni
curl -H "X-TOKEN-SYSTEM: s3cur3T0k3nV4lu3" \
  "http://localhost:8000/api/exchange-rates?currency=EUR&startDate=2025-02-17&endDate=2025-02-21"
```

**Odpowiedź 200 OK:**

```json
[
  {
    "date": "2025-02-17",
    "bid": 4.4520,
    "ask": 4.5430,
    "bidDiff": null,
    "askDiff": null
  },
  {
    "date": "2025-02-18",
    "bid": 4.4600,
    "ask": 4.5400,
    "bidDiff": 0.0080,
    "askDiff": -0.0030
  }
]
```

```bash
# Brak tokena → 401
curl "http://localhost:8000/api/exchange-rates?currency=EUR&startDate=2025-02-17&endDate=2025-02-21"
# → {"error":"Brak wymaganego nagłówka X-TOKEN-SYSTEM."}

# Nieprawidłowa waluta → 400
curl -H "X-TOKEN-SYSTEM: s3cur3T0k3nV4lu3" \
  "http://localhost:8000/api/exchange-rates?currency=GBP&startDate=2025-02-17&endDate=2025-02-21"
# → {"error":"Nieprawidłowy parametr \"currency\". Dozwolone wartości: EUR, USD, CHF."}

# Zakres > 7 dni → 400
curl -H "X-TOKEN-SYSTEM: s3cur3T0k3nV4lu3" \
  "http://localhost:8000/api/exchange-rates?currency=EUR&startDate=2025-02-01&endDate=2025-02-15"
# → {"error":"Maksymalny zakres dat to 7 dni."}
```

#### Odpowiedź — opis pól

| Pole     | Typ          | Opis                                                       |
|----------|-------------|-------------------------------------------------------------|
| date     | string      | Data kursu (`YYYY-MM-DD`)                                   |
| bid      | float       | Kurs kupna                                                  |
| ask      | float       | Kurs sprzedaży                                              |
| bidDiff  | float\|null | Różnica kursu kupna vs poprzedni dzień (null dla 1. dnia)   |
| askDiff  | float\|null | Różnica kursu sprzedaży vs poprzedni dzień (null dla 1. dnia)|

## Dokumentacja OpenAPI (Swagger)

Po uruchomieniu serwera dokumentacja jest dostępna pod adresem:

- **Swagger UI:** [http://localhost:8000/api/doc](http://localhost:8000/api/doc)
- **JSON spec:** [http://localhost:8000/api/doc.json](http://localhost:8000/api/doc.json)

## Testy

```bash
# Uruchomienie pełnego zestawu testów
php bin/phpunit

# Tylko testy jednostkowe
php bin/phpunit tests/Unit/

# Tylko testy funkcjonalne
php bin/phpunit tests/Functional/
```

### Pokrycie testami

| Komponent                   | Typ testu    | Liczba testów |
|-----------------------------|-------------|---------------|
| `Currency` enum             | Unit        | 6             |
| `ExchangeRateDay` DTO      | Unit        | 3             |
| `NbpApiClient` service     | Unit        | 7             |
| `TokenSubscriber`           | Unit        | 7             |
| `ExchangeRateController`   | Functional  | 10            |

## Struktura projektu

```
nbp-exchange-rates/
├── config/
│   ├── bundles.php
│   ├── packages/
│   │   ├── framework.yaml
│   │   ├── nelmio_api_doc.yaml
│   │   └── twig.yaml
│   ├── routes/
│   │   └── nelmio_api_doc.yaml
│   ├── routes.yaml
│   └── services.yaml
├── public/
│   └── index.php
├── src/
│   ├── Controller/
│   │   └── ExchangeRateController.php
│   ├── DTO/
│   │   └── ExchangeRateDay.php
│   ├── Enum/
│   │   └── Currency.php
│   ├── EventSubscriber/
│   │   └── TokenSubscriber.php
│   ├── Service/
│   │   └── NbpApiClient.php
│   └── Kernel.php
├── tests/
│   ├── Functional/
│   │   └── Controller/
│   │       └── ExchangeRateControllerTest.php
│   ├── Unit/
│   │   ├── DTO/
│   │   │   └── ExchangeRateDayTest.php
│   │   ├── Enum/
│   │   │   └── CurrencyTest.php
│   │   ├── EventSubscriber/
│   │   │   └── TokenSubscriberTest.php
│   │   └── Service/
│   │       └── NbpApiClientTest.php
│   └── bootstrap.php
├── .env
├── .env.test
├── .gitignore
├── composer.json
├── phpunit.xml.dist
└── README.md
```

## Token uwierzytelniający

Wartość tokena jest konfigurowana przez zmienną środowiskową `X_TOKEN_SYSTEM` w pliku `.env`:

```dotenv
X_TOKEN_SYSTEM=s3cur3T0k3nV4lu3
```

Dla środowiska testowego osobna wartość jest w `.env.test`:

```dotenv
X_TOKEN_SYSTEM=test-token-value
```

## Licencja

Projekt udostępniony na licencji [MIT](LICENSE).
