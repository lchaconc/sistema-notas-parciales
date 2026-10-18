<?php

namespace App\Controllers;

use App\Exceptions\ApiError;
use CodeIgniter\HTTP\ResponseInterface;
use Throwable;

abstract class ApiController extends BaseController
{
    protected function body(): array
    {
        $value = $this->request->getJSON(true);
        if (!is_array($value) || array_is_list($value)) {
            throw new ApiError('Se requiere un objeto JSON válido.');
        }
        return $value;
    }

    protected function runAction(callable $action, int $status = 200): ResponseInterface
    {
        try {
            $result = $action();
            return $this->response->setStatusCode($status)->setJSON(['data' => $result]);
        } catch (ApiError $error) {
            return $this->response->setStatusCode($error->status)->setJSON(['error' => $error->getMessage()]);
        } catch (Throwable $error) {
            log_message('error', (string) $error);
            return $this->response->setStatusCode(500)->setJSON(['error' => 'Error interno del servidor.']);
        }
    }
}
