<?php

namespace App\Controllers;

use App\Services\AttendanceService;

class AttendanceController extends ApiController
{
    public function sessions(int $courseId)
    {
        return $this->runAction(fn () => (new AttendanceService())->sessions($courseId));
    }

    public function create(int $courseId)
    {
        return $this->runAction(fn () => (new AttendanceService())->create($courseId, $this->body()), 201);
    }

    public function update(int $sessionId)
    {
        return $this->runAction(fn () => (new AttendanceService())->update($sessionId, $this->body()));
    }
}
