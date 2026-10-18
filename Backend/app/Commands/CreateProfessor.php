<?php

namespace App\Commands;

use App\Services\AuthService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Throwable;

class CreateProfessor extends BaseCommand
{
    protected $group = 'Usuarios';
    protected $name = 'profesor:crear';
    protected $description = 'Crea la única cuenta del profesor.';
    protected $usage = 'profesor:crear correo@ucatolica.ac.cr';

    public function run(array $params)
    {
        $email = $params[0] ?? '';
        if ($email === '') {
            CLI::error('Indique el correo del profesor.');
            return EXIT_ERROR;
        }

        $password = getenv('PROFESSOR_PASSWORD');
        if (!is_string($password) || $password === '') {
            if (!stream_isatty(STDIN)) {
                CLI::error('Se requiere una terminal para ingresar la contraseña.');
                return EXIT_ERROR;
            }
            CLI::write('Contraseña: ', 'yellow');
            shell_exec('stty -echo');
            try {
                $password = rtrim((string) fgets(STDIN), "\r\n");
            } finally {
                shell_exec('stty echo');
                CLI::newLine();
            }
        }

        try {
            (new AuthService())->createProfessor($email, $password);
            CLI::write('Cuenta del profesor creada.', 'green');
            return EXIT_SUCCESS;
        } catch (Throwable $error) {
            CLI::error($error->getMessage());
            return EXIT_ERROR;
        }
    }
}
