<?php

namespace App\Services;

use App\Exceptions\ApiError;
use App\Models\EnrollmentModel;
use App\Models\StudentModel;
use App\Models\UserModel;
use App\Support\Input;

class EnrollmentService
{
    public function students(): array
    {
        return db_connect()->table('estudiantes s')
            ->select('s.*, u.correo')
            ->join('usuarios u', 'u.id = s.usuario_id')
            ->orderBy('s.primer_apellido')->orderBy('s.segundo_apellido')->orderBy('s.nombre')
            ->get()->getResultArray();
    }

    public function enrollments(int $courseId): array
    {
        (new CourseService())->course($courseId);
        return db_connect()->table('matriculas m')
            ->select('m.id, m.curso_id, s.usuario_id AS estudiante_id, s.nombre, s.primer_apellido, s.segundo_apellido, u.correo')
            ->join('estudiantes s', 's.usuario_id = m.estudiante_id')
            ->join('usuarios u', 'u.id = s.usuario_id')
            ->where('m.curso_id', $courseId)
            ->orderBy('s.primer_apellido')->orderBy('s.segundo_apellido')->orderBy('s.nombre')
            ->get()->getResultArray();
    }

    public function enroll(int $courseId, array $data): array
    {
        (new CourseService())->course($courseId);
        $email = Input::email($data);
        (new AuthService())->assertAllowedEmail($email);
        $name = Input::text($data, 'nombre', 255);
        $first = Input::text($data, 'primer_apellido', 255);
        $second = Input::text($data, 'segundo_apellido', 255);
        $db = db_connect();
        $db->transBegin();
        try {
            $users = new UserModel();
            $user = $users->where('correo', $email)->first();
            if ($user && $user['rol'] !== 'estudiante') {
                throw new ApiError('El correo pertenece al profesor.', 409);
            }
            if ($user) {
                $studentId = (int) $user['id'];
            } else {
                $studentId = (int) $users->insert(['correo' => $email, 'rol' => 'estudiante', 'contrasena_hash' => null], true);
                (new StudentModel())->insert([
                    'usuario_id' => $studentId,
                    'nombre' => $name,
                    'primer_apellido' => $first,
                    'segundo_apellido' => $second,
                ]);
            }
            $enrollments = new EnrollmentModel();
            if ($enrollments->where('estudiante_id', $studentId)->where('curso_id', $courseId)->first()) {
                throw new ApiError('El estudiante ya está matriculado en este curso.', 409);
            }
            $enrollmentId = (int) $enrollments->insert(['estudiante_id' => $studentId, 'curso_id' => $courseId], true);
            $sessions = $db->table('sesiones_asistencia')->select('id')->where('curso_id', $courseId)->get()->getResultArray();
            foreach ($sessions as $session) {
                $db->table('asistencias')->insert([
                    'sesion_id' => $session['id'],
                    'matricula_id' => $enrollmentId,
                    'condicion' => 'justificado',
                ]);
            }
            if ($db->transStatus() === false) {
                throw new \RuntimeException('No se pudo matricular al estudiante.');
            }
            $db->transCommit();
            return ['id' => $enrollmentId, 'estudiante_id' => $studentId, 'curso_id' => $courseId];
        } catch (\Throwable $error) {
            $db->transRollback();
            throw $error;
        }
    }
}
