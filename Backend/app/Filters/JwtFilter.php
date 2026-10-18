<?php

namespace App\Filters;

use App\Exceptions\ApiError;
use App\Services\TokenService;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class JwtFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        try {
            TokenService::principal($request, $arguments[0] ?? null);
        } catch (ApiError $error) {
            return service('response')->setStatusCode($error->status)->setJSON(['error' => $error->getMessage()]);
        }
        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
