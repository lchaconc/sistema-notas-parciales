<?php

namespace App\Services;

use App\Exceptions\ApiError;
use App\Models\CourseModel;
use App\Models\QuarterModel;
use App\Support\Input;
use DateTimeImmutable;
use DateTimeZone;

class CourseService
{
    private QuarterModel $quarters;
    private CourseModel $courses;

    public function __construct()
    {
        $this->quarters = new QuarterModel();
        $this->courses = new CourseModel();
    }

    public function quarters(): array
    {
        return $this->quarters->orderBy('codigo', 'DESC')->findAll();
    }

    public function createQuarter(array $data): array
    {
        $code = Input::text($data, 'codigo', 6);
        if (!preg_match('/^[1-3]-\d{4}$/D', $code)) {
            throw new ApiError('El código del cuatrimestre debe tener formato N-AAAA.');
        }
        $start = Input::date($data, 'fecha_inicio');
        $end = Input::date($data, 'fecha_cierre');
        if ($start > $end) {
            throw new ApiError('La fecha de inicio no puede ser posterior al cierre.');
        }
        if ($this->quarters->where('codigo', $code)->first()) {
            throw new ApiError('El cuatrimestre ya existe.', 409);
        }
        $id = $this->quarters->insert(['codigo' => $code, 'fecha_inicio' => $start, 'fecha_cierre' => $end], true);
        return $this->quarters->find($id);
    }

    public function courses(): array
    {
        return db_connect()->table('cursos c')
            ->select('c.*, q.codigo AS cuatrimestre_codigo, q.fecha_inicio, q.fecha_cierre')
            ->join('cuatrimestres q', 'q.id = c.cuatrimestre_id')
            ->orderBy('q.codigo', 'DESC')->orderBy('c.codigo')->orderBy('c.numero_grupo')
            ->get()->getResultArray();
    }

    public function course(int $id): array
    {
        $course = db_connect()->table('cursos c')
            ->select('c.*, q.codigo AS cuatrimestre_codigo, q.fecha_inicio, q.fecha_cierre')
            ->join('cuatrimestres q', 'q.id = c.cuatrimestre_id')
            ->where('c.id', $id)->get()->getRowArray();
        if (!$course) {
            throw new ApiError('Curso no encontrado.', 404);
        }
        return $course;
    }

    public function createCourse(array $data): array
    {
        $quarterId = Input::integer($data, 'cuatrimestre_id', 1);
        if (!$this->quarters->find($quarterId)) {
            throw new ApiError('Cuatrimestre no encontrado.', 404);
        }
        $name = Input::text($data, 'nombre', 255);
        $code = Input::text($data, 'codigo', 64);
        $group = Input::integer($data, 'numero_grupo');
        if ($this->courses->where('cuatrimestre_id', $quarterId)->where('codigo', $code)->where('numero_grupo', $group)->first()) {
            throw new ApiError('El grupo ya existe para este código y cuatrimestre.', 409);
        }
        $id = $this->courses->insert([
            'cuatrimestre_id' => $quarterId,
            'nombre' => $name,
            'codigo' => $code,
            'numero_grupo' => $group,
        ], true);
        return $this->course((int) $id);
    }

    public function deleteCourse(int $id): array
    {
        $course = $this->course($id);
        $today = (new DateTimeImmutable('now', new DateTimeZone('America/Costa_Rica')))->format('Y-m-d');
        if ($today <= $course['fecha_cierre']) {
            throw new ApiError('El curso solo puede eliminarse después del cierre del cuatrimestre.', 409);
        }
        $db = db_connect();
        $db->transBegin();
        try {
            $db->table('cursos')->where('id', $id)->delete();
            if ($db->transStatus() === false) {
                throw new \RuntimeException('No se pudo eliminar el curso.');
            }
            $db->transCommit();
        } catch (\Throwable $error) {
            $db->transRollback();
            throw $error;
        }
        return ['eliminado' => true];
    }
}
