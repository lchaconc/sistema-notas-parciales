<?php

namespace App\Controllers;

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

    public function saveGrade(int $enrollmentId, int $activityId)
    {
        return $this->runAction(fn () => (new EvaluationService())->saveGrade($enrollmentId, $activityId, $this->body()));
    }

    public function grade(int $enrollmentId, int $activityId)
    {
        return $this->runAction(fn () => (new EvaluationService())->grade($enrollmentId, $activityId));
    }
}
