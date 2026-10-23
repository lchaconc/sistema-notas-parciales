<?php

namespace App\Database\Seeds;

use App\Services\AuthService;
use CodeIgniter\Database\Seeder;
use RuntimeException;

class E2EProfessorSeeder extends Seeder
{
    public function run()
    {
        if (getenv('DB_TARGET') !== 'test' || getenv('DB_TEST_HOST') !== 'db_e2e') {
            throw new RuntimeException('Este seeder solo puede usarse con la base temporal e2e.');
        }
        if ($this->db->table('usuarios')->where('rol', 'profesor')->countAllResults() === 0) {
            (new AuthService())->createProfessor('profesor@ucatolica.ac.cr', getenv('E2E_PROFESSOR_PASSWORD') ?: 'e2e-fixture-only');
        }
    }
}
