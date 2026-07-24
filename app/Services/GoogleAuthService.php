<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Session;
use Firebase\JWT\JWT;
use Google\Client;
use PDO;

final class GoogleAuthService
{
    private const GOOGLE_ID_TOKEN_LEEWAY_SECONDS = 300;

    private readonly PDO $database;

    public function __construct(
        Database $database,
        private readonly Session $session,
    ) {
        $this->database = $database->connection();
    }

    public function authorizationUrl(): string
    {
        $client = $this->client();
        $state = bin2hex(random_bytes(32));
        $nonce = bin2hex(random_bytes(32));
        $this->session->put('google_oauth_state', $state);
        $this->session->put('google_oauth_nonce', $nonce);
        $client->setState($state);

        return $client->createAuthUrl(null, [
            'nonce' => $nonce,
        ]);
    }

    public function authenticate(string $code, string $state): int
    {
        $expectedState = $this->session->get('google_oauth_state');
        $expectedNonce = $this->session->get('google_oauth_nonce');
        $this->session->forget('google_oauth_state');
        $this->session->forget('google_oauth_nonce');

        if (!is_string($expectedState) || !hash_equals($expectedState, $state)) {
            throw new \RuntimeException('La respuesta de Google no contiene un estado válido.');
        }

        $client = $this->client();
        $token = $client->fetchAccessTokenWithAuthCode($code);
        if (isset($token['error'])) {
            throw new \RuntimeException('Google rechazó el código de autorización.');
        }

        $idToken = $token['id_token'] ?? null;
        if (!is_string($idToken)) {
            throw new \RuntimeException('Google no entregó un token de identidad.');
        }

        $claims = $this->verifyIdToken($client, $idToken);
        if (!is_array($claims)) {
            throw new \RuntimeException('El token de identidad de Google no es válido.');
        }

        $issuer = (string) ($claims['iss'] ?? '');
        if (!in_array($issuer, ['https://accounts.google.com', 'accounts.google.com'], true)) {
            throw new \RuntimeException('El emisor del token de Google no es válido.');
        }
        if (!is_string($expectedNonce) || !hash_equals($expectedNonce, (string) ($claims['nonce'] ?? ''))) {
            throw new \RuntimeException('El nonce del token de Google no es válido.');
        }
        if (($claims['email_verified'] ?? false) !== true) {
            throw new \RuntimeException('Google no confirmó el correo de la cuenta.');
        }

        $googleSub = trim((string) ($claims['sub'] ?? ''));
        $email = strtolower(trim((string) ($claims['email'] ?? '')));
        $name = trim((string) ($claims['name'] ?? ''));
        $avatar = isset($claims['picture']) ? (string) $claims['picture'] : null;
        if ($googleSub === '' || $email === '' || $name === '') {
            throw new \RuntimeException('El perfil de Google está incompleto.');
        }

        return $this->upsertUser($googleSub, $email, $name, $avatar);
    }

    public function establishSession(int $userId): void
    {
        $this->session->regenerate();
        $this->session->put('user_id', $userId);

        $statement = $this->database->prepare(
            'SELECT familia_id FROM familia_usuarios WHERE usuario_id = :usuario_id ORDER BY created_at LIMIT 1'
        );
        $statement->execute(['usuario_id' => $userId]);
        $familyId = $statement->fetchColumn();
        if ($familyId !== false) {
            $this->session->put('family_id', (int) $familyId);
        }
    }

    private function client(): Client
    {
        $clientId = trim((string) env('GOOGLE_CLIENT_ID', ''));
        $clientSecret = trim((string) env('GOOGLE_CLIENT_SECRET', ''));
        $redirectUri = trim((string) env('GOOGLE_REDIRECT_URI', ''));
        if ($clientId === '' || $clientSecret === '' || $redirectUri === '') {
            throw new \RuntimeException('Las credenciales de Google OAuth no están configuradas.');
        }

        $client = new Client();
        $client->setClientId($clientId);
        $client->setClientSecret($clientSecret);
        $client->setRedirectUri($redirectUri);
        $client->setScopes(['openid', 'email', 'profile']);
        $client->setAccessType('online');
        return $client;
    }

    private function verifyIdToken(Client $client, string $idToken): array|false
    {
        $previousLeeway = JWT::$leeway;
        JWT::$leeway = max($previousLeeway, self::GOOGLE_ID_TOKEN_LEEWAY_SECONDS);

        try {
            return $client->verifyIdToken($idToken);
        } finally {
            JWT::$leeway = $previousLeeway;
        }
    }

    private function upsertUser(string $googleSub, string $email, string $name, ?string $avatar): int
    {
        $statement = $this->database->prepare('SELECT id, activo FROM usuarios WHERE google_sub = :google_sub');
        $statement->execute(['google_sub' => $googleSub]);
        $user = $statement->fetch();

        if (is_array($user)) {
            if (!(bool) $user['activo']) {
                throw new \RuntimeException('La cuenta está desactivada.');
            }
            $update = $this->database->prepare(
                'UPDATE usuarios
                 SET nombre = :nombre, email = :email, avatar_url = :avatar_url,
                     email_verificado_at = UTC_TIMESTAMP(), ultimo_acceso_at = UTC_TIMESTAMP()
                 WHERE id = :id'
            );
            $update->execute([
                'nombre' => $name,
                'email' => $email,
                'avatar_url' => $avatar,
                'id' => $user['id'],
            ]);
            return (int) $user['id'];
        }

        $emailOwner = $this->database->prepare('SELECT id FROM usuarios WHERE email = :email');
        $emailOwner->execute(['email' => $email]);
        if ($emailOwner->fetchColumn() !== false) {
            throw new \RuntimeException('El correo ya está asociado a otra identidad de Google.');
        }

        $insert = $this->database->prepare(
            'INSERT INTO usuarios
             (nombre, email, google_sub, avatar_url, email_verificado_at, ultimo_acceso_at, activo)
             VALUES (:nombre, :email, :google_sub, :avatar_url, UTC_TIMESTAMP(), UTC_TIMESTAMP(), 1)'
        );
        $insert->execute([
            'nombre' => $name,
            'email' => $email,
            'google_sub' => $googleSub,
            'avatar_url' => $avatar,
        ]);
        return (int) $this->database->lastInsertId();
    }
}
