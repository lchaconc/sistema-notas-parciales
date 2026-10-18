<?php

namespace App\Services;

use App\Exceptions\ApiError;
use CodeIgniter\Database\BaseConnection;
use Throwable;

class HealthService
{
    public function check(?BaseConnection $connection = null): array
    {
        try {
            $connection ??= db_connect();
            if ($connection->query('SELECT 1') === false) {
                throw new \RuntimeException('La consulta de comprobación falló.');
            }
        } catch (Throwable $error) {
            log_message('error', 'Comprobación de MySQL fallida: ' . $error->getMessage());
            throw new ApiError('Base de datos no disponible.', 503);
        }

        return ['servidor' => 'ok', 'base_datos' => 'ok'];
    }
}
