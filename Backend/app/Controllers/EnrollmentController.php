<?php

namespace App\Controllers;

use App\Services\EnrollmentService;

class EnrollmentController extends ApiController
{
    public function students()
    {
        return $this->runAction(fn () => (new EnrollmentService())->students());
    }

    public function enrollments(int $courseId)
    {
        return $this->runAction(fn () => (new EnrollmentService())->enrollments($courseId));
    }

    public function enroll(int $courseId)
    {
        return $this->runAction(fn () => (new EnrollmentService())->enroll($courseId, $this->body()), 201);
    }
}
