<?php

namespace App\Services;

use App\Exceptions\ApiError;
use App\Support\Input;
use CodeIgniter\Database\BaseConnection;

class AuthService
{
    private BaseConnection $db;

    public function __construct()
    {
        $this->db = db_connect();
    }

    public function assertAllowedEmail(string $email): void
    {
        $domain = substr(strrchr($email, '@') ?: '', 1);
        if ($domain === '' || $this->db->table('dominios_permitidos')->where('dominio', $domain)->countAllResults() === 0) {
            throw new ApiError('El dominio del correo no está permitido.');
        }
    }

    public function createProfessor(string $email, string $password): int
    {
        $email = Input::email(['correo' => $email]);
        $this->assertAllowedEmail($email);
        if ($password === '') {
            throw new ApiError('La contraseña es obligatoria.');
        }
        if ($this->db->table('usuarios')->where('rol', 'profesor')->countAllResults() > 0) {
            throw new ApiError('Ya existe una cuenta de profesor.', 409);
        }
        if ($this->db->table('usuarios')->where('correo', $email)->countAllResults() > 0) {
            throw new ApiError('El correo ya está en uso.', 409);
        }
        $this->db->table('usuarios')->insert([
            'correo' => $email,
            'contrasena_hash' => password_hash($password, PASSWORD_DEFAULT),
            'rol' => 'profesor',
        ]);
        return (int) $this->db->insertID();
    }

    public function login(array $data): array
    {
        $email = Input::email($data);
        $password = $data['contrasena'] ?? null;
        if (!is_string($password) || $password === '') {
            throw new ApiError('Correo o contraseña incorrectos.', 401);
        }
        $user = $this->db->table('usuarios')->where('correo', $email)->get()->getRowArray();
        if (!$user || !is_string($user['contrasena_hash']) || !password_verify($password, $user['contrasena_hash'])) {
            throw new ApiError('Correo o contraseña incorrectos.', 401);
        }
        return [
            'token' => TokenService::issue((int) $user['id'], $user['rol']),
            'expires_in' => 3600,
            'rol' => $user['rol'],
        ];
    }

    public function generateStudentPassword(int $studentId): array
    {
        $user = $this->db->table('usuarios')->where('id', $studentId)->where('rol', 'estudiante')->get()->getRowArray();
        if (!$user) {
            throw new ApiError('Estudiante no encontrado.', 404);
        }
        $password = rtrim(strtr(base64_encode(random_bytes(18)), '+/', '-_'), '=');
        $this->db->table('usuarios')->where('id', $studentId)->update([
            'contrasena_hash' => password_hash($password, PASSWORD_DEFAULT),
        ]);
        return ['contrasena' => $password];
    }

    public function changeStudentPassword(int $studentId, array $data): array
    {
        $password = $data['contrasena_nueva'] ?? null;
        if (!is_string($password) || $password === '') {
            throw new ApiError('La nueva contraseña es obligatoria.');
        }
        $this->db->table('usuarios')->where('id', $studentId)->where('rol', 'estudiante')->update([
            'contrasena_hash' => password_hash($password, PASSWORD_DEFAULT),
        ]);
        return ['actualizada' => true];
    }
}
