<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Forge;
use CodeIgniter\Database\Migration;

class CorrectTareasActivityScore extends Migration
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
        $components = $this->db->table('componentes_evaluacion c')
            ->select('c.id')
            ->join('cursos cu', 'cu.id = c.curso_id')
            ->join('cuatrimestres q', 'q.id = cu.cuatrimestre_id')
            ->where('cu.nombre', 'Informática Aplicada 1')
            ->where('cu.codigo', 'PD008')
            ->where('cu.numero_grupo', 1)
            ->where('q.codigo', '3-2026')
            ->where('c.titulo', 'Tareas')
            ->where('c.cantidad_actividades', 7)
            ->where('c.porcentaje_total', '15.00')
            ->where('c.puntaje_por_actividad', '10.00')
            ->get()->getResultArray();

        if ($components === []) {
            return;
        }
        if (count($components) !== 1) {
            throw new \RuntimeException('La corrección de Tareas requiere un único componente coincidente.');
        }

        $componentId = (int) $components[0]['id'];
        $activities = $this->db->table('actividades_individuales')
            ->select('id, numero, porcentaje_asignado, puntaje_asignado')
            ->where('componente_id', $componentId)
            ->orderBy('numero')->get()->getResultArray();

        if (count($activities) !== 7) {
            throw new \RuntimeException('La corrección de Tareas requiere exactamente siete actividades.');
        }

        $oldScores = 0;
        foreach ($activities as $index => $activity) {
            if ((int) $activity['numero'] !== $index + 1 || (string) $activity['porcentaje_asignado'] !== '2.14') {
                throw new \RuntimeException('Las actividades de Tareas no coinciden con los datos esperados.');
            }
            if ((string) $activity['puntaje_asignado'] === '1.42') {
                $oldScores++;
            } elseif ((string) $activity['puntaje_asignado'] !== '10.00') {
                throw new \RuntimeException('Las actividades de Tareas tienen puntajes inesperados.');
            }
        }

        if ($oldScores === 0) {
            return;
        }
        if ($oldScores !== 7) {
            throw new \RuntimeException('La corrección de Tareas no admite puntajes parcialmente modificados.');
        }

        $activityIds = array_map(static fn (array $activity): int => (int) $activity['id'], $activities);
        $grades = $this->db->table('calificaciones')->whereIn('actividad_individual_id', $activityIds)->countAllResults();
        if ($grades !== 0) {
            throw new \RuntimeException('La corrección de Tareas requiere que sus actividades no tengan calificaciones.');
        }

        $this->db->transBegin();
        try {
            $this->db->table('actividades_individuales')
                ->where('componente_id', $componentId)
                ->where('puntaje_asignado', '1.42')
                ->update(['puntaje_asignado' => '10.00']);
            if ($this->db->affectedRows() !== 7 || $this->db->transStatus() === false) {
                throw new \RuntimeException('No se actualizaron exactamente siete actividades de Tareas.');
            }
            $this->db->transCommit();
        } catch (\Throwable $error) {
            $this->db->transRollback();
            throw $error;
        }
    }

    public function down()
    {
        throw new \RuntimeException('La corrección puntual de Tareas no se revierte automáticamente; restaure un respaldo si es necesario.');
    }
}
