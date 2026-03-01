<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Subscriber weryfikujący obecność i poprawność tokena uwierzytelniającego.
 *
 * Każde zapytanie kierowane do endpointów API (ścieżki zaczynające się od /api,
 * z wyłączeniem /api/doc) musi zawierać nagłówek HTTP {@code X-TOKEN-SYSTEM}
 * o wartości zgodnej z parametrem środowiskowym.
 *
 * W przypadku braku nagłówka lub nieprawidłowej wartości tokena,
 * subscriber zwraca odpowiedź HTTP 401 Unauthorized w formacie JSON.
 */
class TokenSubscriber implements EventSubscriberInterface
{
    /**
     * @param string $tokenSystem Oczekiwana wartość tokena (wstrzykiwana z parametru app.token_system)
     */
    public function __construct(
        private readonly string $tokenSystem,
    ) {
    }

    /**
     * Zwraca mapę subskrybowanych zdarzeń.
     *
     * Subscriber nasłuchuje na zdarzenie kernel.request z priorytetem 256,
     * aby weryfikacja tokena następowała przed resztą logiki routingu
     * i kontrolerów.
     *
     * @return array<string, array{string, int}> Mapa zdarzeń
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 256],
        ];
    }

    /**
     * Obsługuje zdarzenie kernel.request — weryfikuje token uwierzytelniający.
     *
     * Metoda sprawdza, czy zapytanie dotyczy endpointu API (z pominięciem
     * dokumentacji Swagger) i czy nagłówek X-TOKEN-SYSTEM jest obecny
     * oraz zawiera prawidłową wartość.
     *
     * @param RequestEvent $event Zdarzenie żądania HTTP
     */
    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        $path    = $request->getPathInfo();

        // Pomijamy ścieżki niezwiązane z API oraz dokumentację Swagger
        if (!str_starts_with($path, '/api') || str_starts_with($path, '/api/doc')) {
            return;
        }

        $token = $request->headers->get('X-TOKEN-SYSTEM');

        if ($token === null || $token === '') {
            $event->setResponse(new JsonResponse(
                ['error' => 'Brak wymaganego nagłówka X-TOKEN-SYSTEM.'],
                Response::HTTP_UNAUTHORIZED
            ));

            return;
        }

        if ($token !== $this->tokenSystem) {
            $event->setResponse(new JsonResponse(
                ['error' => 'Nieprawidłowa wartość tokena X-TOKEN-SYSTEM.'],
                Response::HTTP_UNAUTHORIZED
            ));
        }
    }
}
