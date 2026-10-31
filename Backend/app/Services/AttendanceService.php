<?php

namespace App\Services;

use App\Exceptions\ApiError;
use App\Support\Input;

class AttendanceService
{
    public function sessions(int $courseId): array
    {
        (new CourseService())->course($courseId);
        $db = db_connect();
        $sessions = $db->table('sesiones_asistencia s')
            ->select('s.id, s.curso_id, sc.numero, sc.fecha, s.asistencia_registrada')
            ->join('sesiones_cuatrimestre sc', 'sc.id = s.sesion_cuatrimestre_id')
            ->where('s.curso_id', $courseId)->orderBy('sc.numero')->get()->getResultArray();
        foreach ($sessions as &$session) {
            $session['asistencia_registrada'] = (bool) $session['asistencia_registrada'];
            $session['numero'] = (int) $session['numero'];
            $session['asistencias'] = $db->table('asistencias a')
                ->select('a.matricula_id, a.condicion, m.estudiante_id')
                ->join('matriculas m', 'm.id = a.matricula_id')
                ->where('a.sesion_id', $session['id'])->orderBy('a.matricula_id')->get()->getResultArray();
        }
        unset($session);
        return $sessions;
    }

    public function update(int $sessionId, array $data): array
    {
        $entries = $this->parseEntries($data);
        $db = db_connect();
        $session = $db->table('sesiones_asistencia')->where('id', $sessionId)->get()->getRowArray();
        if (!$session) {
            throw new ApiError('Sesión no encontrada.', 404);
        }
        $courseId = (int) $session['curso_id'];
        $db->transBegin();
        try {
            // Enrollment takes the same course lock, so its matrícula cannot race this full-set save.
            $db->query('SELECT id FROM cursos WHERE id = ? FOR UPDATE', [$courseId]);
            $locked = $db->query('SELECT s.id, s.asistencia_registrada, sc.cuatrimestre_id AS fecha_cuatrimestre_id, c.cuatrimestre_id AS curso_cuatrimestre_id FROM sesiones_asistencia s JOIN sesiones_cuatrimestre sc ON sc.id = s.sesion_cuatrimestre_id JOIN cursos c ON c.id = s.curso_id WHERE s.id = ? FOR UPDATE', [$sessionId])->getRowArray();
            if (!$locked) {
                throw new ApiError('Sesión no encontrada.', 404);
            }
            if ($locked['fecha_cuatrimestre_id'] !== $locked['curso_cuatrimestre_id']) {
                throw new ApiError('La sesión programada no pertenece al cuatrimestre del curso.', 409);
            }
            $enrollmentIds = array_map('intval', array_column(
                $db->table('matriculas')->select('id')->where('curso_id', $courseId)->get()->getResultArray(), 'id'
            ));
            sort($enrollmentIds);
            $givenIds = array_keys($entries);
            sort($givenIds);
            if (!$enrollmentIds || $enrollmentIds !== $givenIds) {
                throw new ApiError('Debe asignar una condición a todos y solo a los matriculados del curso.');
            }

            if ((bool) $locked['asistencia_registrada']) {
                $existingIds = array_map('intval', array_column(
                    $db->table('asistencias')->select('matricula_id')->where('sesion_id', $sessionId)->get()->getResultArray(), 'matricula_id'
                ));
                sort($existingIds);
                if ($existingIds !== $enrollmentIds) {
                    throw new ApiError('La asistencia registrada está incompleta.', 409);
                }
                foreach ($entries as $enrollmentId => $condition) {
                    $db->table('asistencias')->where('sesion_id', $sessionId)
                        ->where('matricula_id', $enrollmentId)->update(['condicion' => $condition]);
                }
            } else {
                foreach ($entries as $enrollmentId => $condition) {
                    $db->table('asistencias')->insert([
                        'sesion_id' => $sessionId, 'matricula_id' => $enrollmentId, 'condicion' => $condition,
                    ]);
                }
                $db->table('sesiones_asistencia')->where('id', $sessionId)->update(['asistencia_registrada' => 1]);
            }
            if ($db->transStatus() === false) {
                throw new \RuntimeException('No se pudo guardar la asistencia.');
            }
            $db->transCommit();
            foreach ($this->sessions($courseId) as $result) {
                if ((int) $result['id'] === $sessionId) {
                    return $result;
                }
            }
            throw new \RuntimeException('No se encontró la sesión guardada.');
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
