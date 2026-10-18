<?php

namespace App\Services;

use App\Exceptions\ApiError;
use App\Models\SessionModel;
use App\Support\Input;

class AttendanceService
{
    public function sessions(int $courseId): array
    {
        (new CourseService())->course($courseId);
        $db = db_connect();
        $sessions = (new SessionModel())->where('curso_id', $courseId)->orderBy('fecha')->findAll();
        foreach ($sessions as &$session) {
            $session['asistencias'] = $db->table('asistencias a')
                ->select('a.matricula_id, a.condicion, m.estudiante_id')
                ->join('matriculas m', 'm.id = a.matricula_id')
                ->where('a.sesion_id', $session['id'])->orderBy('a.matricula_id')->get()->getResultArray();
        }
        return $sessions;
    }

    public function create(int $courseId, array $data): array
    {
        $course = (new CourseService())->course($courseId);
        $date = Input::date($data, 'fecha');
        if ($date < $course['fecha_inicio'] || $date > $course['fecha_cierre']) {
            throw new ApiError('La fecha está fuera del cuatrimestre.');
        }
        $entries = $this->parseEntries($data);
        $db = db_connect();
        $db->transBegin();
        try {
            $db->query('SELECT id FROM cursos WHERE id = ? FOR UPDATE', [$courseId]);
            $existing = $db->table('sesiones_asistencia')->where('curso_id', $courseId)->get()->getResultArray();
            if (count($existing) >= 15) {
                throw new ApiError('El curso ya tiene quince sesiones.', 409);
            }
            foreach ($existing as $session) {
                if ($session['fecha'] === $date) {
                    throw new ApiError('Ya existe una sesión para esa fecha.', 409);
                }
            }
            $enrollmentIds = array_map('intval', array_column(
                $db->table('matriculas')->select('id')->where('curso_id', $courseId)->get()->getResultArray(), 'id'
            ));
            sort($enrollmentIds);
            $givenIds = array_keys($entries);
            sort($givenIds);
            if ($enrollmentIds !== $givenIds) {
                throw new ApiError('Debe asignar una condición a todos y solo a los matriculados del curso.');
            }
            $sessionId = (int) (new SessionModel())->insert(['curso_id' => $courseId, 'fecha' => $date], true);
            foreach ($entries as $enrollmentId => $condition) {
                $db->table('asistencias')->insert([
                    'sesion_id' => $sessionId,
                    'matricula_id' => $enrollmentId,
                    'condicion' => $condition,
                ]);
            }
            if ($db->transStatus() === false) {
                throw new \RuntimeException('No se pudo guardar la sesión.');
            }
            $db->transCommit();
            return ['id' => $sessionId, 'curso_id' => $courseId, 'fecha' => $date];
        } catch (\Throwable $error) {
            $db->transRollback();
            throw $error;
        }
    }

    public function update(int $sessionId, array $data): array
    {
        $session = (new SessionModel())->find($sessionId);
        if (!$session) {
            throw new ApiError('Sesión no encontrada.', 404);
        }
        $entries = $this->parseEntries($data);
        $db = db_connect();
        $db->transBegin();
        try {
            foreach ($entries as $enrollmentId => $condition) {
                $enrollment = $db->table('matriculas')->where('id', $enrollmentId)
                    ->where('curso_id', $session['curso_id'])->get()->getRowArray();
                if (!$enrollment) {
                    throw new ApiError('Matrícula ajena al curso de la sesión.');
                }
                $attendance = $db->table('asistencias')->where('sesion_id', $sessionId)
                    ->where('matricula_id', $enrollmentId)->get()->getRowArray();
                if (!$attendance) {
                    throw new ApiError('Asistencia no encontrada.', 404);
                }
                $db->table('asistencias')->where('id', $attendance['id'])->update(['condicion' => $condition]);
            }
            if ($db->transStatus() === false) {
                throw new \RuntimeException('No se pudo actualizar la asistencia.');
            }
            $db->transCommit();
            return ['actualizada' => true];
        } catch (\Throwable $error) {
            $db->transRollback();
            throw $error;
        }
    }

    private function parseEntries(array $data): array
    {
        $items = $data['asistencias'] ?? null;
        if (!is_array($items) || !array_is_list($items)) {
            throw new ApiError('El campo asistencias debe ser una lista.');
        }
        $entries = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                throw new ApiError('Asistencia inválida.');
            }
            $id = Input::integer($item, 'matricula_id', 1);
            $condition = Input::text($item, 'condicion');
            if (!in_array($condition, ['presente', 'ausente', 'justificado'], true)) {
                throw new ApiError('Condición de asistencia inválida.');
            }
            if (array_key_exists($id, $entries)) {
                throw new ApiError('La matrícula aparece dos veces en la sesión.');
            }
            $entries[$id] = $condition;
        }
        return $entries;
    }
}
