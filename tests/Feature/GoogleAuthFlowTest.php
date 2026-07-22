<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Database;
use App\Core\Request;
use App\Core\Session;
use App\Core\View;
use App\Http\Controllers\AuthController;
use App\Services\GoogleAuthService;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

final class GoogleAuthFlowTest extends TestCase
{
    private array $getBackup;
    private array $sessionBackup;

    protected function setUp(): void
    {
        $this->getBackup = $_GET;
        $this->sessionBackup = $_SESSION ?? [];
        $_GET = [];
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_GET = $this->getBackup;
        $_SESSION = $this->sessionBackup;
    }

    public function testAuthorizationUrlCreatesStateNonceAndMinimalScopes(): void
    {
        [$service, $session] = $this->service();
        $url = $service->authorizationUrl();
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        self::assertStringStartsWith('https://accounts.google.com/', $url);
        self::assertSame($session->get('google_oauth_state'), $query['state'] ?? null);
        self::assertSame($session->get('google_oauth_nonce'), $query['nonce'] ?? null);
        self::assertStringContainsString('openid', urldecode((string) ($query['scope'] ?? '')));
        self::assertStringContainsString('email', urldecode((string) ($query['scope'] ?? '')));
        self::assertStringContainsString('profile', urldecode((string) ($query['scope'] ?? '')));
        self::assertSame('online', $query['access_type'] ?? null, 'El portal no debe solicitar acceso offline.');
    }

    public function testManipulatedStateIsRejectedBeforeTokenExchange(): void
    {
        [$service, $session] = $this->service();
        $service->authorizationUrl();

        try {
            $service->authenticate('codigo-falso', 'estado-manipulado');
            self::fail('Debía rechazar el estado OAuth manipulado.');
        } catch (\RuntimeException $exception) {
            self::assertStringContainsString('estado', $exception->getMessage());
        }
        self::assertNull($session->get('google_oauth_state'));
        self::assertNull($session->get('google_oauth_nonce'));
    }

    public function testCancelledAndIncompleteCallbacksReturnToLogin(): void
    {
        [$service, $session, $database] = $this->service();
        $controller = new AuthController(
            new View(dirname(__DIR__, 2) . '/resources/views', $session),
            $session,
            $service,
        );

        $_GET = ['error' => 'access_denied'];
        $cancelled = $controller->handleGoogleCallback(new Request());
        self::assertSame(302, $cancelled->status());
        self::assertSame('/login', $cancelled->header('Location'));

        $_GET = [];
        $incomplete = $controller->handleGoogleCallback(new Request());
        self::assertSame(302, $incomplete->status());
        self::assertSame('/login', $incomplete->header('Location'));
    }

    public function testInvitationIsPreservedWhenGoogleFlowIsCancelledOrIncomplete(): void
    {
        [$service, $session] = $this->service();
        $session->put('invitacion_token_pendiente', str_repeat('a', 43));
        $controller = new AuthController(
            new View(dirname(__DIR__, 2) . '/resources/views', $session),
            $session,
            $service,
        );

        $_GET = ['error' => 'access_denied'];
        $cancelled = $controller->handleGoogleCallback(new Request());
        self::assertSame('/invitaciones/aceptar', $cancelled->header('Location'));
        self::assertSame(str_repeat('a', 43), $session->get('invitacion_token_pendiente'));

        $_GET = [];
        $incomplete = $controller->handleGoogleCallback(new Request());
        self::assertSame('/invitaciones/aceptar', $incomplete->header('Location'));
        self::assertSame(str_repeat('a', 43), $session->get('invitacion_token_pendiente'));
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testLogoutInvalidatesTheSession(): void
    {
        require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
        \App\Core\Env::load(dirname(__DIR__, 2) . '/.env');
        ini_set('session.save_path', dirname(__DIR__, 2) . '/storage/cache');
        [$service, $session] = $this->service();
        $session->start();
        $session->put('user_id', 999);
        $controller = new AuthController(
            new View(dirname(__DIR__, 2) . '/resources/views', $session),
            $session,
            $service,
        );

        $response = $controller->destroy(new Request());
        self::assertSame(302, $response->status());
        self::assertSame('/login', $response->header('Location'));
        self::assertSame(PHP_SESSION_NONE, session_status());
    }

    private function service(): array
    {
        $database = Database::connect(require dirname(__DIR__, 2) . '/config/database.php');
        $session = new Session('portal_test_auth');
        return [new GoogleAuthService($database, $session), $session, $database];
    }
}
