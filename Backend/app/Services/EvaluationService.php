<?php

namespace App\Services;

use App\Exceptions\ApiError;
use App\Models\ComponentModel;
use App\Models\GradeModel;
use App\Support\Input;

class EvaluationService
{
    public function components(int $courseId): array
    {
        (new CourseService())->course($courseId);
        $db = db_connect();
        $rows = $db->table('componentes_evaluacion c')
            ->select('c.*, ca.nombre AS actividad_catalogo')
            ->join('catalogo_actividades ca', 'ca.id = c.actividad_catalogo_id')
            ->where('c.curso_id', $courseId)->orderBy('c.id')->get()->getResultArray();
        foreach ($rows as &$row) {
            $row['actividades'] = $db->table('actividades_individuales')
                ->where('componente_id', $row['id'])->orderBy('numero')->get()->getResultArray();
        }
        return $rows;
    }

    public function createComponent(int $courseId, array $data): array
    {
        (new CourseService())->course($courseId);
        $catalogId = Input::integer($data, 'actividad_catalogo_id', 1);
        $db = db_connect();
        if (!$db->table('catalogo_actividades')->where('id', $catalogId)->get()->getRow()) {
            throw new ApiError('Actividad del catálogo no encontrada.', 404);
        }
        $title = Input::text($data, 'titulo');
        if (count(preg_split('/\s+/u', $title) ?: []) > 128) {
            throw new ApiError('El título supera 128 palabras.');
        }
        $quantity = Input::integer($data, 'cantidad_actividades', 1, 15);
        $description = Input::optionalText($data, 'descripcion', 256);
        $percentCents = Input::cents($data, 'porcentaje_total', 0, 10000);
        $pointsCents = Input::cents($data, 'puntaje_por_actividad', 100, 10000);

        $db->transBegin();
        try {
            $db->query('SELECT id FROM cursos WHERE id = ? FOR UPDATE', [$courseId]);
            $total = $db->table('componentes_evaluacion')->selectSum('porcentaje_total', 'total')
                ->where('curso_id', $courseId)->get()->getRow('total') ?? '0';
            if (Input::cents(['total' => (string) $total], 'total') + $percentCents > 10000) {
                throw new ApiError('Los porcentajes del curso superan el 100 %.');
            }
            $id = (new ComponentModel())->insert([
                'curso_id' => $courseId,
                'actividad_catalogo_id' => $catalogId,
                'titulo' => $title,
                'cantidad_actividades' => $quantity,
                'descripcion' => $description,
                'porcentaje_total' => Input::decimal($percentCents),
                'puntaje_por_actividad' => Input::decimal($pointsCents),
            ], true);
            $individuals = [];
            for ($number = 1; $number <= $quantity; $number++) {
                $individuals[] = [
                    'componente_id' => $id,
                    'numero' => $number,
                    'porcentaje_asignado' => Input::decimal(intdiv($percentCents, $quantity)),
                    'puntaje_asignado' => Input::decimal($pointsCents),
                ];
            }
            $db->table('actividades_individuales')->insertBatch($individuals);
            if ($db->transStatus() === false) {
                throw new \RuntimeException('No se pudo crear el componente.');
            }
            $db->transCommit();
            return ['id' => (int) $id, 'actividades' => $individuals];
        } catch (\Throwable $error) {
            $db->transRollback();
            throw $error;
        }
    }

    public function saveGrade(int $enrollmentId, int $activityId, array $data): array
    {
        $row = $this->gradeContext($enrollmentId, $activityId);
        $maxCents = Input::cents(['max' => $row['puntaje_asignado']], 'max');
        $pointsCents = Input::cents($data, 'puntos_obtenidos', 0, $maxCents);
        $observation = Input::optionalText($data, 'observacion', 125);
        $grades = new GradeModel();
        $existing = $grades->where('matricula_id', $enrollmentId)->where('actividad_individual_id', $activityId)->first();
        $record = [
            'matricula_id' => $enrollmentId,
            'actividad_individual_id' => $activityId,
            'puntos_obtenidos' => Input::decimal($pointsCents),
            'observacion' => $observation,
        ];
        if ($existing) {
            $grades->update($existing['id'], $record);
            $record['id'] = (int) $existing['id'];
        } else {
            $record['id'] = (int) $grades->insert($record, true);
        }
        return $record;
    }

    public function grade(int $enrollmentId, int $activityId): array
    {
        $this->gradeContext($enrollmentId, $activityId);
        $existing = (new GradeModel())->where('matricula_id', $enrollmentId)
            ->where('actividad_individual_id', $activityId)->first();
        return [
            'matricula_id' => $enrollmentId,
            'actividad_individual_id' => $activityId,
            'puntos_obtenidos' => $existing['puntos_obtenidos'] ?? null,
            'observacion' => $existing['observacion'] ?? null,
        ];
    }

    private function gradeContext(int $enrollmentId, int $activityId): array
    {
        $row = db_connect()->table('matriculas m')
            ->select('m.curso_id, ai.puntaje_asignado, c.curso_id AS actividad_curso_id')
            ->join('actividades_individuales ai', 'ai.id = ' . (int) $activityId)
            ->join('componentes_evaluacion c', 'c.id = ai.componente_id')
            ->where('m.id', $enrollmentId)->get()->getRowArray();
        if (!$row) {
            throw new ApiError('Matrícula o actividad no encontrada.', 404);
        }
        if ($row['curso_id'] !== $row['actividad_curso_id']) {
            throw new ApiError('La actividad no pertenece al curso de la matrícula.', 409);
        }
        return $row;
    }
}
