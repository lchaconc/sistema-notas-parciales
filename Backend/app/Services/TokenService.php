<?php

namespace App\Services;

use App\Exceptions\ApiError;
use CodeIgniter\HTTP\IncomingRequest;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Throwable;

final class TokenService
{
    private static function secret(): string
    {
        $secret = getenv('JWT_SECRET');
        if (!is_string($secret) || strlen($secret) < 32) {
            throw new \RuntimeException('JWT_SECRET no está configurado.');
        }
        return $secret;
    }

    public static function issue(int $userId, string $role): string
    {
        $now = time();
        return JWT::encode([
            'sub' => (string) $userId,
            'role' => $role,
            'iat' => $now,
            'exp' => $now + 3600,
        ], self::secret(), 'HS256');
    }

    public static function principal(IncomingRequest $request, ?string $requiredRole = null): array
    {
        $authorization = $request->getHeaderLine('Authorization');
        if (!preg_match('/^Bearer\s+(\S+)$/i', $authorization, $matches)) {
            throw new ApiError('Se requiere un JWT válido.', 401);
        }
        try {
            $token = JWT::decode($matches[1], new Key(self::secret(), 'HS256'));
        } catch (Throwable) {
            throw new ApiError('JWT inválido o vencido.', 401);
        }
        $id = filter_var($token->sub ?? null, FILTER_VALIDATE_INT);
        $role = $token->role ?? null;
        if (!$id || !in_array($role, ['profesor', 'estudiante'], true)) {
            throw new ApiError('JWT inválido.', 401);
        }
        $user = db_connect()->table('usuarios')->where('id', $id)->get()->getRowArray();
        if (!$user || $user['rol'] !== $role) {
            throw new ApiError('La cuenta no está disponible.', 401);
        }
        if ($requiredRole !== null && $role !== $requiredRole) {
            throw new ApiError('No tiene permiso para esta operación.', 403);
        }
        return $user;
    }
}
