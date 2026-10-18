<?php

namespace App\Controllers;

use App\Exceptions\ApiError;
use App\Services\ReportService;
use App\Services\TokenService;
use Throwable;

class ReportController extends ApiController
{
    public function professorConcentrate(int $courseId)
    {
        return $this->runAction(fn () => (new ReportService())->concentrate($courseId));
    }

    public function professorXlsx(int $courseId)
    {
        try {
            $path = (new ReportService())->exportXlsx($courseId);
            $contents = file_get_contents($path);
            unlink($path);
            if ($contents === false) {
                throw new \RuntimeException('No se pudo leer el archivo.');
            }
            return $this->response
                ->setHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
                ->setHeader('Content-Disposition', 'attachment; filename="concentrado-curso-' . $courseId . '.xlsx"')
                ->setBody($contents);
        } catch (ApiError $error) {
            return $this->response->setStatusCode($error->status)->setJSON(['error' => $error->getMessage()]);
        } catch (Throwable $error) {
            log_message('error', (string) $error);
            return $this->response->setStatusCode(500)->setJSON(['error' => 'Error interno del servidor.']);
        }
    }

    public function studentCourses()
    {
        return $this->runAction(fn () => (new ReportService())->studentCourses($this->studentId()));
    }

    public function studentGrades(int $courseId)
    {
        return $this->runAction(fn () => (new ReportService())->studentGrades($courseId, $this->studentId()));
    }

    public function studentAttendance(int $courseId)
    {
        return $this->runAction(fn () => (new ReportService())->studentAttendance($courseId, $this->studentId()));
    }

    public function studentConcentrate(int $courseId)
    {
        return $this->runAction(fn () => (new ReportService())->concentrate($courseId, $this->studentId()));
    }

    private function studentId(): int
    {
        return (int) TokenService::principal($this->request, 'estudiante')['id'];
    }
}
