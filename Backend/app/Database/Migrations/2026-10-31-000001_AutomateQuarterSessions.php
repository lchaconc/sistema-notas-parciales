<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Forge;
use CodeIgniter\Database\Migration;
use DateTimeImmutable;
use RuntimeException;

class AutomateQuarterSessions extends Migration
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
        $quarters = $this->db->table('cuatrimestres')->get()->getResultArray();
        $quarterDates = [];
        foreach ($quarters as $quarter) {
            $start = new DateTimeImmutable($quarter['fecha_inicio']);
            if ($start->modify('+98 days')->format('Y-m-d') !== $quarter['fecha_cierre']) {
                throw new RuntimeException('El cuatrimestre ' . $quarter['codigo'] . ' no dura 98 días; no se migraron sesiones.');
            }
            $quarterDates[$quarter['id']] = $quarter['fecha_inicio'];
        }

        $oldSessions = $this->db->table('sesiones_asistencia s')
            ->select('s.id, s.curso_id, s.fecha, c.cuatrimestre_id')
            ->join('cursos c', 'c.id = s.curso_id')->get()->getResultArray();
        foreach ($oldSessions as $session) {
            $start = new DateTimeImmutable($quarterDates[$session['cuatrimestre_id']]);
            $days = (int) $start->diff(new DateTimeImmutable($session['fecha']))->format('%r%a');
            if ($days < 0 || $days > 98 || $days % 7 !== 0) {
                throw new RuntimeException('La sesión histórica ' . $session['id'] . ' no coincide con el calendario semanal; no se migró.');
            }
            $total = (int) $this->db->table('asistencias')->where('sesion_id', $session['id'])->countAllResults();
            $enrolled = (int) $this->db->table('matriculas')->where('curso_id', $session['curso_id'])->countAllResults();
            $foreign = (int) $this->db->table('asistencias a')->join('matriculas m', 'm.id = a.matricula_id')
                ->where('a.sesion_id', $session['id'])->where('m.curso_id !=', $session['curso_id'])->countAllResults();
            if ($foreign || ($total !== 0 && $total !== $enrolled)) {
                throw new RuntimeException('La sesión histórica ' . $session['id'] . ' tiene asistencias incompletas o ajenas; no se migró.');
            }
        }

        $this->db->query(<<<'SQL'
CREATE TABLE sesiones_cuatrimestre (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cuatrimestre_id BIGINT UNSIGNED NOT NULL,
    numero TINYINT UNSIGNED NOT NULL,
    fecha DATE NOT NULL,
    UNIQUE KEY uq_sesiones_cuatrimestre_numero (cuatrimestre_id, numero),
    UNIQUE KEY uq_sesiones_cuatrimestre_fecha (cuatrimestre_id, fecha),
    CONSTRAINT fk_sesiones_cuatrimestre_cuatrimestre FOREIGN KEY (cuatrimestre_id) REFERENCES cuatrimestres(id) ON DELETE CASCADE,
    CONSTRAINT chk_sesiones_cuatrimestre_numero CHECK (numero BETWEEN 1 AND 15)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL);
        $this->db->query('ALTER TABLE sesiones_asistencia ADD sesion_cuatrimestre_id BIGINT UNSIGNED NULL, ADD asistencia_registrada BOOLEAN NOT NULL DEFAULT FALSE');

        $schedule = [];
        foreach ($quarters as $quarter) {
            $start = new DateTimeImmutable($quarter['fecha_inicio']);
            for ($number = 1; $number <= 15; $number++) {
                $date = $start->modify('+' . (($number - 1) * 7) . ' days')->format('Y-m-d');
                $this->db->table('sesiones_cuatrimestre')->insert([
                    'cuatrimestre_id' => $quarter['id'], 'numero' => $number, 'fecha' => $date,
                ]);
                $schedule[$quarter['id']][$date] = (int) $this->db->insertID();
            }
        }
        foreach ($oldSessions as $session) {
            $recorded = $this->db->table('asistencias')->where('sesion_id', $session['id'])->countAllResults() > 0;
            $this->db->table('sesiones_asistencia')->where('id', $session['id'])->update([
                'sesion_cuatrimestre_id' => $schedule[$session['cuatrimestre_id']][$session['fecha']],
                'asistencia_registrada' => $recorded ? 1 : 0,
            ]);
        }
        $courses = $this->db->table('cursos')->get()->getResultArray();
        foreach ($courses as $course) {
            foreach ($schedule[$course['cuatrimestre_id']] as $date => $scheduleId) {
                $exists = $this->db->table('sesiones_asistencia')->where('curso_id', $course['id'])
                    ->where('sesion_cuatrimestre_id', $scheduleId)->countAllResults();
                if (!$exists) {
                    $this->db->table('sesiones_asistencia')->insert([
                        'curso_id' => $course['id'], 'fecha' => $date,
                        'sesion_cuatrimestre_id' => $scheduleId, 'asistencia_registrada' => 0,
                    ]);
                }
            }
        }
        $this->db->query('ALTER TABLE sesiones_asistencia MODIFY sesion_cuatrimestre_id BIGINT UNSIGNED NOT NULL, ADD UNIQUE KEY uq_sesiones_curso_programada (curso_id, sesion_cuatrimestre_id), ADD CONSTRAINT fk_sesiones_programada FOREIGN KEY (sesion_cuatrimestre_id) REFERENCES sesiones_cuatrimestre(id) ON DELETE RESTRICT');
        $this->db->query('ALTER TABLE sesiones_asistencia DROP INDEX uq_sesiones_curso_fecha, DROP COLUMN fecha');
        $this->db->query('ALTER TABLE cuatrimestres DROP CHECK chk_cuatrimestres_fechas, ADD CONSTRAINT chk_cuatrimestres_98_dias CHECK (DATEDIFF(fecha_cierre, fecha_inicio) = 98)');
    }

    public function down()
    {
        $this->db->query('ALTER TABLE cuatrimestres DROP CHECK chk_cuatrimestres_98_dias, ADD CONSTRAINT chk_cuatrimestres_fechas CHECK (fecha_inicio <= fecha_cierre)');
        $this->db->query('ALTER TABLE sesiones_asistencia ADD fecha DATE NULL');
        $this->db->query('UPDATE sesiones_asistencia s JOIN sesiones_cuatrimestre sc ON sc.id = s.sesion_cuatrimestre_id SET s.fecha = sc.fecha');
        $this->db->query('ALTER TABLE sesiones_asistencia DROP FOREIGN KEY fk_sesiones_programada, DROP INDEX uq_sesiones_curso_programada, DROP COLUMN sesion_cuatrimestre_id, DROP COLUMN asistencia_registrada, MODIFY fecha DATE NOT NULL, ADD UNIQUE KEY uq_sesiones_curso_fecha (curso_id, fecha)');
        $this->db->query('DROP TABLE sesiones_cuatrimestre');
    }
}
