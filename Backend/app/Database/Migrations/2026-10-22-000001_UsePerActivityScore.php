<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Forge;
use CodeIgniter\Database\Migration;

class UsePerActivityScore extends Migration
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
        $invalid = $this->db->query(<<<'SQL'
SELECT c.id
FROM componentes_evaluacion c
LEFT JOIN actividades_individuales a ON a.componente_id = c.id
GROUP BY c.id, c.cantidad_actividades
HAVING COUNT(a.id) <> c.cantidad_actividades
    OR MIN(a.puntaje_asignado) <> MAX(a.puntaje_asignado)
LIMIT 1
SQL)->getRow();
        if ($invalid !== null) {
            throw new \RuntimeException('Hay componentes históricos con actividades o puntajes inconsistentes; no se modificó el esquema.');
        }

        // El límite antiguo impediría conservar puntajes individuales menores que 5.
        $this->db->query('ALTER TABLE componentes_evaluacion DROP CHECK chk_componentes_puntaje');
        $this->db->query(<<<'SQL'
UPDATE componentes_evaluacion c
JOIN actividades_individuales a ON a.componente_id = c.id AND a.numero = 1
SET c.puntaje_total = a.puntaje_asignado
SQL);
        $this->db->query(<<<'SQL'
ALTER TABLE componentes_evaluacion
CHANGE COLUMN puntaje_total puntaje_por_actividad DECIMAL(5,2) NOT NULL,
ADD CONSTRAINT chk_componentes_puntaje_actividad CHECK (puntaje_por_actividad > 0 AND puntaje_por_actividad <= 100)
SQL);
    }

    public function down()
    {
        throw new \RuntimeException('La regla anterior no puede reconstruirse sin alterar puntajes históricos; restaure un respaldo si necesita revertir esta migración.');
    }
}
