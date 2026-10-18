<?php

namespace App\Controllers;

use App\Services\CourseService;

class CourseController extends ApiController
{
    public function quarters()
    {
        return $this->runAction(fn () => (new CourseService())->quarters());
    }

    public function createQuarter()
    {
        return $this->runAction(fn () => (new CourseService())->createQuarter($this->body()), 201);
    }

    public function courses()
    {
        return $this->runAction(fn () => (new CourseService())->courses());
    }

    public function createCourse()
    {
        return $this->runAction(fn () => (new CourseService())->createCourse($this->body()), 201);
    }

    public function deleteCourse(int $id)
    {
        return $this->runAction(fn () => (new CourseService())->deleteCourse($id));
    }
}
