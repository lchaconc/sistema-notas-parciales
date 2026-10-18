<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Forge;
use CodeIgniter\Database\Migration;

class EnforceSingleProfessor extends Migration
{
    public function __construct(?Forge $forge = null)
    {
        if (getenv('DB_TARGET') === 'test' || ENVIRONMENT === 'testing') {
            $this->DBGroup = 'tests';
        }
        parent::__construct($forge);
    }

    public function up()
    {
        $this->db->query(<<<'SQL'
ALTER TABLE usuarios
ADD COLUMN profesor_unico TINYINT GENERATED ALWAYS AS (CASE WHEN rol = 'profesor' THEN 1 ELSE NULL END) STORED,
ADD UNIQUE KEY uq_usuarios_profesor_unico (profesor_unico)
SQL);
    }

    public function down()
    {
        $this->db->query('ALTER TABLE usuarios DROP INDEX uq_usuarios_profesor_unico, DROP COLUMN profesor_unico');
    }
}
