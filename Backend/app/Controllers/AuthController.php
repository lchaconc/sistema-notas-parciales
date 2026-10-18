<?php

namespace App\Controllers;

use App\Services\AuthService;
use App\Services\TokenService;

class AuthController extends ApiController
{
    public function login()
    {
        return $this->runAction(fn () => (new AuthService())->login($this->body()));
    }

    public function generatePassword(int $studentId)
    {
        return $this->runAction(fn () => (new AuthService())->generateStudentPassword($studentId));
    }

    public function changePassword()
    {
        return $this->runAction(function () {
            $user = TokenService::principal($this->request, 'estudiante');
            return (new AuthService())->changeStudentPassword((int) $user['id'], $this->body());
        });
    }
}
