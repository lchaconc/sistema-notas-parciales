<?php

namespace App\Services;

use App\Exceptions\ApiError;
use App\Support\Input;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

class ReportService
{
    public function studentCourses(int $studentId): array
    {
        return db_connect()->table('matriculas m')
            ->select('c.id, c.nombre, c.codigo, c.numero_grupo, q.codigo AS cuatrimestre_codigo, q.fecha_inicio, q.fecha_cierre')
            ->join('cursos c', 'c.id = m.curso_id')
            ->join('cuatrimestres q', 'q.id = c.cuatrimestre_id')
            ->where('m.estudiante_id', $studentId)
            ->orderBy('q.codigo', 'DESC')->orderBy('c.codigo')->orderBy('c.numero_grupo')
            ->get()->getResultArray();
    }

    public function concentrate(int $courseId, ?int $studentId = null): array
    {
        $course = (new CourseService())->course($courseId);
        $db = db_connect();
        $peopleQuery = $db->table('matriculas m')
            ->select('m.id AS matricula_id, s.usuario_id AS estudiante_id, s.nombre, s.primer_apellido, s.segundo_apellido, u.correo')
            ->join('estudiantes s', 's.usuario_id = m.estudiante_id')
            ->join('usuarios u', 'u.id = s.usuario_id')
            ->where('m.curso_id', $courseId);
        if ($studentId !== null) {
            $peopleQuery->where('m.estudiante_id', $studentId);
        }
        $people = $peopleQuery->orderBy('s.primer_apellido')->orderBy('s.segundo_apellido')->orderBy('s.nombre')
            ->get()->getResultArray();
        if ($studentId !== null && $people === []) {
            throw new ApiError('No está matriculado en este curso.', 404);
        }
        $activities = $db->table('actividades_individuales ai')
            ->select('ai.id, ai.numero, ai.porcentaje_asignado, ai.puntaje_asignado, c.titulo')
            ->join('componentes_evaluacion c', 'c.id = ai.componente_id')
            ->where('c.curso_id', $courseId)->orderBy('c.id')->orderBy('ai.numero')->get()->getResultArray();
        $grades = $db->table('calificaciones g')
            ->select('g.matricula_id, g.actividad_individual_id, g.puntos_obtenidos, g.observacion')
            ->join('matriculas m', 'm.id = g.matricula_id')
            ->where('m.curso_id', $courseId)->get()->getResultArray();
        $gradeMap = [];
        foreach ($grades as $grade) {
            $gradeMap[$grade['matricula_id'] . ':' . $grade['actividad_individual_id']] = $grade;
        }
        $table = [];
        foreach ($people as $person) {
            $cells = [];
            foreach ($activities as $activity) {
                $grade = $gradeMap[$person['matricula_id'] . ':' . $activity['id']] ?? null;
                $points = $grade['puntos_obtenidos'] ?? null;
                $scoreCents = Input::cents(['value' => $activity['puntaje_asignado']], 'value');
                $weightCents = Input::cents(['value' => $activity['porcentaje_asignado']], 'value');
                $earnedCents = $points === null ? 0 : Input::cents(['value' => $points], 'value');
                $percentCents = $scoreCents === 0 ? 0 : intdiv($earnedCents * $weightCents, $scoreCents);
                $cells[] = [
                    'actividad_id' => (int) $activity['id'],
                    'nota' => $points,
                    'porcentaje_obtenido' => Input::decimal($percentCents),
                ];
            }
            $table[] = [
                'matricula_id' => (int) $person['matricula_id'],
                'estudiante_id' => (int) $person['estudiante_id'],
                'nombre' => $person['nombre'],
                'primer_apellido' => $person['primer_apellido'],
                'segundo_apellido' => $person['segundo_apellido'],
                'correo' => $person['correo'],
                'calificaciones' => $cells,
            ];
        }
        return [
            'curso' => $course,
            'actividades' => array_map(static fn ($item) => [
                'id' => (int) $item['id'],
                'titulo' => $item['titulo'],
                'numero' => (int) $item['numero'],
            ], $activities),
            'estudiantes' => $table,
        ];
    }

    public function studentGrades(int $courseId, int $studentId): array
    {
        $concentrate = $this->concentrate($courseId, $studentId);
        $person = $concentrate['estudiantes'][0];
        $db = db_connect();
        $observations = $db->table('calificaciones')
            ->select('actividad_individual_id, observacion')
            ->where('matricula_id', $person['matricula_id'])->get()->getResultArray();
        $observationMap = array_column($observations, 'observacion', 'actividad_individual_id');
        $activityLabels = $db->table('actividades_individuales ai')
            ->select('ai.id, ca.nombre AS actividad_catalogo, c.cantidad_actividades')
            ->join('componentes_evaluacion c', 'c.id = ai.componente_id')
            ->join('catalogo_actividades ca', 'ca.id = c.actividad_catalogo_id')
            ->where('c.curso_id', $courseId)->get()->getResultArray();
        $labelMap = array_column($activityLabels, null, 'id');
        $cards = [];
        $totalCents = 0;
        foreach ($person['calificaciones'] as $index => $cell) {
            $totalCents += Input::cents(['value' => $cell['porcentaje_obtenido']], 'value');
            $cards[] = [
                ...$concentrate['actividades'][$index],
                'actividad_catalogo' => $labelMap[$cell['actividad_id']]['actividad_catalogo'],
                'cantidad_actividades' => (int) $labelMap[$cell['actividad_id']]['cantidad_actividades'],
                'nota' => $cell['nota'],
                'porcentaje_obtenido' => $cell['porcentaje_obtenido'],
                'observacion' => $observationMap[$cell['actividad_id']] ?? null,
            ];
        }
        return ['curso' => $concentrate['curso'], 'resultado' => Input::decimal($totalCents), 'tarjetas' => $cards];
    }

    public function studentAttendance(int $courseId, int $studentId): array
    {
        $this->concentrate($courseId, $studentId);
        return db_connect()->table('asistencias a')
            ->select('s.id AS sesion_id, s.fecha, a.condicion')
            ->join('sesiones_asistencia s', 's.id = a.sesion_id')
            ->join('matriculas m', 'm.id = a.matricula_id')
            ->where('m.curso_id', $courseId)->where('m.estudiante_id', $studentId)
            ->orderBy('s.fecha')->get()->getResultArray();
    }

    public function exportXlsx(int $courseId): string
    {
        $concentrate = $this->concentrate($courseId);
        $path = tempnam(sys_get_temp_dir(), 'snp_xlsx_');
        if ($path === false) {
            throw new \RuntimeException('No se pudo preparar el archivo.');
        }
        $writer = new Writer();
        try {
            $writer->openToFile($path);
            $header = ['Estudiante', 'Correo'];
            foreach ($concentrate['actividades'] as $activity) {
                $name = $activity['titulo'] . ' ' . $activity['numero'];
                $header[] = $name . ' - nota';
                $header[] = $name . ' - porcentaje obtenido';
            }
            $writer->addRow(Row::fromValues($header));
            foreach ($concentrate['estudiantes'] as $student) {
                $values = [
                    $student['nombre'] . ' ' . $student['primer_apellido'] . ' ' . $student['segundo_apellido'],
                    $student['correo'],
                ];
                foreach ($student['calificaciones'] as $cell) {
                    $values[] = $cell['nota'] ?? '';
                    $values[] = $cell['porcentaje_obtenido'];
                }
                $writer->addRow(Row::fromValues($values));
            }
            $writer->close();
            return $path;
        } catch (\Throwable $error) {
            @unlink($path);
            throw $error;
        }
    }
}
