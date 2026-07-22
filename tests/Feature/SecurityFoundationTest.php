<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Http\Middleware\VerifyCsrfToken;
use PHPUnit\Framework\TestCase;

final class SecurityFoundationTest extends TestCase
{
    private array $serverBackup;
    private array $postBackup;
    private array $sessionBackup;

    protected function setUp(): void
    {
        $this->serverBackup = $_SERVER;
        $this->postBackup = $_POST;
        $this->sessionBackup = $_SESSION ?? [];
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->serverBackup;
        $_POST = $this->postBackup;
        $_SESSION = $this->sessionBackup;
    }

    public function testStoredAndReflectedHtmlIsEscapedByTheView(): void
    {
        $view = new View(dirname(__DIR__, 2) . '/resources/views', new Session());
        $payload = '<script>alert("xss")</script><img src=x onerror=alert(1)>';
        $escaped = $view->escape($payload);

        self::assertStringNotContainsString('<script>', $escaped);
        self::assertStringNotContainsString('<img', $escaped);
        self::assertStringContainsString('&lt;script&gt;', $escaped);
        self::assertStringContainsString('&quot;xss&quot;', $escaped);
    }

    public function testCsrfMiddlewareRejectsInvalidTokenAndAcceptsValidToken(): void
    {
        $session = new Session();
        $middleware = new VerifyCsrfToken($session);
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['_token' => 'manipulado'];

        $rejected = $middleware->handle(new Request(), static fn (): Response => Response::html('aceptado'));
        self::assertSame(419, $rejected->status());

        $_POST['_token'] = $session->token();
        $accepted = $middleware->handle(new Request(), static fn (): Response => Response::html('aceptado'));
        self::assertSame(200, $accepted->status());
        self::assertSame('aceptado', $accepted->content());
    }

    public function testSessionTokensAreRandomAndStrictlyCompared(): void
    {
        $firstSession = new Session();
        $first = $firstSession->token();
        $_SESSION = [];
        $second = (new Session())->token();

        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $first);
        self::assertNotSame($first, $second);
        self::assertFalse($firstSession->tokenIsValid($first));
        self::assertTrue($firstSession->tokenIsValid($second));
    }
}
