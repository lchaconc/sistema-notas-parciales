<?php

use App\Exceptions\ApiError;
use App\Services\HealthService;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Test\CIUnitTestCase;

final class HealthServiceTest extends CIUnitTestCase
{
    public function testDatabaseConnectionExceptionReturnsServiceUnavailable(): void
    {
        $connection = $this->createMock(BaseConnection::class);
        $connection->method('query')->willThrowException(new RuntimeException('detalle interno de conexión'));

        try {
            (new HealthService())->check($connection);
            $this->fail('La comprobación debe fallar cuando MySQL no responde.');
        } catch (ApiError $error) {
            $this->assertSame(503, $error->status);
            $this->assertSame('Base de datos no disponible.', $error->getMessage());
        }
    }

    public function testFailedDatabaseQueryReturnsServiceUnavailableWithoutDetails(): void
    {
        $connection = $this->createMock(BaseConnection::class);
        $connection->expects($this->once())->method('query')->with('SELECT 1')->willReturn(false);

        try {
            (new HealthService())->check($connection);
            $this->fail('La comprobación debe fallar cuando MySQL no responde.');
        } catch (ApiError $error) {
            $this->assertSame(503, $error->status);
            $this->assertSame('Base de datos no disponible.', $error->getMessage());
        }
    }
}
