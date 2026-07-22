<?php

declare(strict_types=1);

namespace App\Core;

use App\Http\Middleware\Authenticate;
use App\Http\Middleware\AuthorizeFamily;
use App\Http\Middleware\VerifyCsrfToken;
use App\Http\Middleware\AuthorizeAdministrator;
use App\Services\PersonaContext;
use App\Services\PersonaService;

final class Application
{
    private Container $container;
    private Router $router;

    public function __construct(private readonly string $rootPath)
    {
        $this->container = new Container();
        $session = new Session(
            (string) env('SESSION_NAME', 'portal_salud_session'),
            (bool) env('SESSION_SECURE', false),
        );
        $session->start();

        $database = Database::connect(require $this->rootPath('config/database.php'));
        $personContext = new PersonaContext(new PersonaService($database), $session);
        $view = new View($this->rootPath('resources/views'), $session, $personContext);
        $this->container->instance(self::class, $this);
        $this->container->instance(Container::class, $this->container);
        $this->container->instance(Session::class, $session);
        $this->container->instance(View::class, $view);
        $this->container->instance(Validator::class, new Validator());
        $this->container->instance(Database::class, $database);
        $this->container->instance(\PDO::class, $database->connection());
        $this->container->instance(PersonaContext::class, $personContext);

        $this->router = new Router($this->container);
        $this->router->aliasMiddleware('auth', Authenticate::class);
        $this->router->aliasMiddleware('family', AuthorizeFamily::class);
        $this->router->aliasMiddleware('csrf', VerifyCsrfToken::class);
        $this->router->aliasMiddleware('admin', AuthorizeAdministrator::class);
        $this->loadRoutes();
    }

    public function run(): void
    {
        try {
            if ($this->mustRedirectToHttps()) {
                $baseUrl = rtrim((string) env('APP_URL', ''), '/');
                if (!str_starts_with($baseUrl, 'https://')) {
                    throw new \RuntimeException('APP_URL debe usar HTTPS cuando FORCE_HTTPS está activo.');
                }
                $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
                Response::redirect($baseUrl . '/' . ltrim($uri, '/'), 301)->send();
                return;
            }
            $this->router->dispatch(new Request())->send();
        } catch (\Throwable $exception) {
            $this->report($exception);
            if ((bool) env('APP_DEBUG', false)) {
                Response::html(
                    '<h1>Error interno</h1><pre>' . htmlspecialchars($exception->getMessage(), ENT_QUOTES, 'UTF-8') . '</pre>',
                    500,
                )->send();
                return;
            }
            Response::html('<h1>Error interno</h1><p>No fue posible completar la solicitud.</p>', 500)->send();
        }
    }

    private function mustRedirectToHttps(): bool
    {
        if (!filter_var(env('FORCE_HTTPS', false), FILTER_VALIDATE_BOOL)) {
            return false;
        }
        $directHttps = strtolower((string) ($_SERVER['HTTPS'] ?? ''));
        $forwardedProto = strtolower(trim(explode(',', (string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''))[0]));
        return !in_array($directHttps, ['on', '1'], true) && $forwardedProto !== 'https';
    }

    public function rootPath(string $path = ''): string
    {
        return $this->rootPath . ($path === '' ? '' : DIRECTORY_SEPARATOR . ltrim($path, '/\\'));
    }

    private function loadRoutes(): void
    {
        $router = $this->router;
        require $this->rootPath('routes/auth.php');
        require $this->rootPath('routes/web.php');
    }

    private function report(\Throwable $exception): void
    {
        (new LogManager(
            $this->rootPath('storage/logs'),
            (int) env('LOG_RETENTION_DAYS', 30),
        ))->report($exception);
    }
}
