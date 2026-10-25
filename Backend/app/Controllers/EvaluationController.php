<?php

namespace App\Controllers;

use App\Exceptions\ApiError;
use App\Services\EvaluationService;

class EvaluationController extends ApiController
{
    public function components(int $courseId)
    {
        return $this->runAction(fn () => (new EvaluationService())->components($courseId));
    }

    public function createComponent(int $courseId)
    {
        return $this->runAction(fn () => (new EvaluationService())->createComponent($courseId, $this->body()), 201);
    }

    public function deleteComponent(int $courseId, int $componentId)
    {
        return $this->runAction(function () use ($courseId, $componentId) {
            $confirmation = $this->request->getGet('confirmar_con_notas');
            if ($confirmation !== null && $confirmation !== 'true' && $confirmation !== 'false') {
                throw new ApiError('confirmar_con_notas debe ser true o false.');
            }
            return (new EvaluationService())->deleteComponent($courseId, $componentId, $confirmation === 'true');
        });
    }

    public function saveGrade(int $enrollmentId, int $activityId)
    {
        return $this->runAction(fn () => (new EvaluationService())->saveGrade($enrollmentId, $activityId, $this->body()));
    }

    public function grade(int $enrollmentId, int $activityId)
    {
        return $this->runAction(fn () => (new EvaluationService())->grade($enrollmentId, $activityId));
    }
}
