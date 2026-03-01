<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventSubscriber;

use App\EventSubscriber\TokenSubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Testy jednostkowe dla subscribera {@see TokenSubscriber}.
 *
 * Weryfikują poprawność walidacji nagłówka X-TOKEN-SYSTEM
 * dla różnych scenariuszy: brak nagłówka, nieprawidłowy token,
 * prawidłowy token, oraz pomijanie ścieżek non-API (np. Swagger).
 */
class TokenSubscriberTest extends TestCase
{
    /** @var string Wartość tokena używana w testach */
    private const TEST_TOKEN = 'test-token-value';

    /**
     * Sprawdza, czy subscriber jest zarejestrowany na zdarzenie kernel.request.
     */
    public function testSubscribedEvents(): void
    {
        $events = TokenSubscriber::getSubscribedEvents();

        $this->assertArrayHasKey(KernelEvents::REQUEST, $events);
    }

    /**
     * Weryfikuje, że brak nagłówka X-TOKEN-SYSTEM zwraca 401.
     */
    public function testMissingTokenReturns401(): void
    {
        $subscriber = new TokenSubscriber(self::TEST_TOKEN);
        $event      = $this->createRequestEvent('/api/exchange-rates');

        $subscriber->onKernelRequest($event);

        $this->assertNotNull($event->getResponse());
        $this->assertSame(401, $event->getResponse()->getStatusCode());
    }

    /**
     * Sprawdza, że pusty nagłówek X-TOKEN-SYSTEM również zwraca 401.
     */
    public function testEmptyTokenReturns401(): void
    {
        $subscriber = new TokenSubscriber(self::TEST_TOKEN);
        $event      = $this->createRequestEvent('/api/exchange-rates', '');

        $subscriber->onKernelRequest($event);

        $this->assertNotNull($event->getResponse());
        $this->assertSame(401, $event->getResponse()->getStatusCode());
    }

    /**
     * Weryfikuje, że nieprawidłowa wartość tokena zwraca 401.
     */
    public function testInvalidTokenReturns401(): void
    {
        $subscriber = new TokenSubscriber(self::TEST_TOKEN);
        $event      = $this->createRequestEvent('/api/exchange-rates', 'wrong-token');

        $subscriber->onKernelRequest($event);

        $this->assertNotNull($event->getResponse());
        $this->assertSame(401, $event->getResponse()->getStatusCode());
    }

    /**
     * Sprawdza, że prawidłowy token nie ustawia odpowiedzi (przepuszcza żądanie dalej).
     */
    public function testValidTokenDoesNotSetResponse(): void
    {
        $subscriber = new TokenSubscriber(self::TEST_TOKEN);
        $event      = $this->createRequestEvent('/api/exchange-rates', self::TEST_TOKEN);

        $subscriber->onKernelRequest($event);

        $this->assertNull($event->getResponse());
    }

    /**
     * Weryfikuje, że ścieżki niezaczynające się od /api są pomijane.
     */
    public function testNonApiPathIsSkipped(): void
    {
        $subscriber = new TokenSubscriber(self::TEST_TOKEN);
        $event      = $this->createRequestEvent('/some-other-path');

        $subscriber->onKernelRequest($event);

        $this->assertNull($event->getResponse());
    }

    /**
     * Sprawdza, że ścieżka dokumentacji Swagger (/api/doc) jest pomijana.
     */
    public function testSwaggerDocPathIsSkipped(): void
    {
        $subscriber = new TokenSubscriber(self::TEST_TOKEN);
        $event      = $this->createRequestEvent('/api/doc');

        $subscriber->onKernelRequest($event);

        $this->assertNull($event->getResponse());
    }

    /**
     * Tworzy obiekt RequestEvent z symulowanym żądaniem HTTP.
     *
     * @param string      $path  Ścieżka URL żądania
     * @param string|null $token Wartość nagłówka X-TOKEN-SYSTEM (null = brak nagłówka)
     *
     * @return RequestEvent Zdarzenie gotowe do przekazania subscriberowi
     */
    private function createRequestEvent(string $path, ?string $token = null): RequestEvent
    {
        $request = Request::create($path);

        if ($token !== null) {
            $request->headers->set('X-TOKEN-SYSTEM', $token);
        }

        $kernel = $this->createMock(HttpKernelInterface::class);

        return new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);
    }
}
