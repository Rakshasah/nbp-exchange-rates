# NBP Exchange Rates API (Zadanie Rekrutacyjne)

Prosta implementacja REST API w Symfony 6.4, która pobiera kursy walut (tabela C) z API NBP i wylicza różnice kursowe dzień do dnia.

## Funkcjonalności
- Obsługa EUR, USD, CHF.
- Walidacja zakresu dat (max 7 dni).
- Wyliczanie różnicy bid/ask względem poprzedniego dnia roboczego.
- Dokumentacja Swagger UI (`/api/doc`).
- Autoryzacja nagłówkiem `X-TOKEN-SYSTEM`.

## Instalacja i uruchomienie

Wymagany PHP 8.2+ oraz Composer.

1. Pobierz paczki:
   ```bash
   composer install
   ```

2. Konfiguracja tokena:
   Domyślny token znajduje się w pliku `.env`. Jeśli chcesz go zmienić, zrób to w `.env.local`:
   `X_TOKEN_SYSTEM=twoj_token`

3. Odpal serwer:
   ```bash
   php -S localhost:8000 -t public/
   ```

## Użycie

### GET `/api/exchange-rates`

Przykład:
```bash
curl -H "X-TOKEN-SYSTEM: s3cur3T0k3nV4lu3" \
  "http://localhost:8000/api/exchange-rates?currency=EUR&startDate=2024-02-19&endDate=2024-02-23"
```

Struktura odpowiedzi:
- `date`: Data publikacji kursu.
- `bid` / `ask`: Kurs kupna / sprzedaży.
- `bidDiff` / `askDiff`: Różnica względem poprzedniego notowania (null dla pierwszego rekordu w zapytaniu).

## Testy

Projekt posiada testy jednostkowe (serwisy, enumy, DTO) oraz funkcjonalne (kontroler).

```bash
php bin/phpunit
```

## Dokumentacja
Pełna specyfikacja dostępna pod `/api/doc` (NelmioApiDocBundle).

---
Autor: Bartosz Olech
Licencja: MIT
