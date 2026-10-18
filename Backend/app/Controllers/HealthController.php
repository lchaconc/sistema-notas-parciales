<?php

namespace App\Controllers;

use App\Services\HealthService;

class HealthController extends ApiController
{
    public function index()
    {
        return $this->runAction(fn () => (new HealthService())->check());
    }
}
