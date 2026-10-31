<?php

use App\Exceptions\ApiError;
use App\Services\AttendanceService;
use App\Services\AuthService;
use App\Services\CatalogService;
use App\Services\CourseService;
use App\Services\EnrollmentService;
use App\Services\EvaluationService;
use App\Services\ReportService;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use Firebase\JWT\JWT;

final class BackendFlowTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    private BaseConnection $testDb;

    protected function setUp(): void
    {
        parent::setUp();
        $this->testDb = db_connect('tests');
        foreach (['asistencias', 'sesiones_asistencia', 'calificaciones', 'matriculas', 'actividades_individuales', 'componentes_evaluacion', 'cursos', 'sesiones_cuatrimestre', 'cuatrimestres', 'estudiantes', 'usuarios', 'catalogo_actividades', 'dominios_permitidos'] as $table) {
            $this->testDb->table($table)->emptyTable();
        }
        $this->testDb->table('dominios_permitidos')->ignore(true)->insert(['dominio' => 'ucatolica.ac.cr']);
        $this->testDb->table('catalogo_actividades')->insert(['nombre' => 'prueba parcial']);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }

    public function testPublicHealthEndpointChecksMySql(): void
    {
        $response = $this->get('api/healt');
        $response->assertStatus(200);
        $this->assertSame(
            ['data' => ['servidor' => 'ok', 'base_datos' => 'ok']],
            json_decode($response->getJSON(), true)
        );
        $this->jsonRequest('GET', 'api/profesor/cursos')->assertStatus(401);
    }

    public function testAuthenticationAndRoleIsolation(): void
    {
        $this->assertSame('snp_test', $this->testDb->database);
        $this->assertSame('snp_test', db_connect()->database);
        $professorId = (new AuthService())->createProfessor('profesor@ucatolica.ac.cr', 'clave-prueba');
        try {
            (new AuthService())->createProfessor('otro@ucatolica.ac.cr', 'clave-prueba');
            $this->fail('No debe crearse una segunda cuenta de profesor.');
        } catch (ApiError $error) {
            $this->assertSame(409, $error->status);
        }
        $login = $this->jsonRequest('POST', 'api/login', [
            'correo' => 'profesor@ucatolica.ac.cr', 'contrasena' => 'clave-prueba',
        ]);
        $login->assertStatus(200);
        $token = json_decode($login->getJSON(), true)['data']['token'];

        $this->jsonRequest('GET', 'api/profesor/cursos')->assertStatus(401);
        $this->jsonRequest('GET', 'api/profesor/cursos', null, $token)->assertStatus(200);
        $this->jsonRequest('POST', 'api/login', [
            'correo' => 'Profesor@ucatolica.ac.cr', 'contrasena' => 'clave-prueba',
        ])->assertStatus(401);

        $quarter = (new CourseService())->createQuarter([
            'codigo' => '1-2025', 'fecha_inicio' => '2025-01-01', 'fecha_cierre' => '2025-04-09',
        ]);
        $course = (new CourseService())->createCourse([
            'cuatrimestre_id' => (int) $quarter['id'], 'nombre' => 'Curso', 'codigo' => 'C1', 'numero_grupo' => 1,
        ]);
        $student = (new EnrollmentService())->enroll((int) $course['id'], [
            'nombre' => 'Ana', 'primer_apellido' => 'Uno', 'segundo_apellido' => 'Dos',
            'correo' => 'ana@ucatolica.ac.cr',
        ]);
        $password = (new AuthService())->generateStudentPassword($student['estudiante_id'])['contrasena'];
        $studentLogin = $this->jsonRequest('POST', 'api/login', [
            'correo' => 'ana@ucatolica.ac.cr', 'contrasena' => $password,
        ]);
        $studentLogin->assertStatus(200);
        $studentToken = json_decode($studentLogin->getJSON(), true)['data']['token'];
        $this->jsonRequest('GET', 'api/profesor/cursos', null, $studentToken)->assertStatus(403);
        $this->jsonRequest('GET', 'api/estudiante/cursos', null, $studentToken)->assertStatus(200);
        $this->jsonRequest('GET', 'api/profesor/cursos/' . $course['id'] . '/concentrado.xlsx', null, $studentToken)->assertStatus(403);

        $other = (new EnrollmentService())->enroll((int) $course['id'], [
            'nombre' => 'Luis', 'primer_apellido' => 'Tres', 'segundo_apellido' => 'Cuatro',
            'correo' => 'luis@ucatolica.ac.cr',
        ]);
        $otherPassword = (new AuthService())->generateStudentPassword($other['estudiante_id'])['contrasena'];
        $otherToken = json_decode($this->jsonRequest('POST', 'api/login', [
            'correo' => 'luis@ucatolica.ac.cr', 'contrasena' => $otherPassword,
        ])->getJSON(), true)['data']['token'];
        $privateCourse = (new CourseService())->createCourse([
            'cuatrimestre_id' => (int) $quarter['id'], 'nombre' => 'Curso privado', 'codigo' => 'C2', 'numero_grupo' => 1,
        ]);
        (new EnrollmentService())->enroll((int) $privateCourse['id'], [
            'nombre' => 'Luis', 'primer_apellido' => 'Tres', 'segundo_apellido' => 'Cuatro',
            'correo' => 'luis@ucatolica.ac.cr',
        ]);
        $this->jsonRequest('GET', 'api/estudiante/cursos/' . $privateCourse['id'] . '/concentrado', null, $studentToken)->assertStatus(404);
        $ownTable = $this->jsonRequest('GET', 'api/estudiante/cursos/' . $course['id'] . '/concentrado', null, $studentToken);
        $ownTable->assertStatus(200);
        $this->assertCount(1, json_decode($ownTable->getJSON(), true)['data']['estudiantes']);
        $this->jsonRequest('PUT', 'api/estudiante/perfil/contrasena', ['contrasena_nueva' => 'otra-clave'], $studentToken)->assertStatus(200);
        $this->jsonRequest('POST', 'api/login', ['correo' => 'ana@ucatolica.ac.cr', 'contrasena' => $password])->assertStatus(401);
        $this->jsonRequest('POST', 'api/login', ['correo' => 'ana@ucatolica.ac.cr', 'contrasena' => 'otra-clave'])->assertStatus(200);
        $this->jsonRequest('GET', 'api/estudiante/cursos/' . $course['id'] . '/concentrado', null, $otherToken)->assertStatus(200);

        $expired = JWT::encode(['sub' => (string) $professorId, 'role' => 'profesor', 'iat' => time() - 7200, 'exp' => time() - 3600], getenv('JWT_SECRET'), 'HS256');
        $this->jsonRequest('GET', 'api/profesor/cursos', null, $expired)->assertStatus(401);
    }

    public function testAcademicFlowAndDataRetention(): void
    {
        $courses = new CourseService();
        $quarter = $courses->createQuarter([
            'codigo' => '2-2025', 'fecha_inicio' => '2025-05-01', 'fecha_cierre' => '2025-08-07',
        ]);
        $course = $courses->createCourse([
            'cuatrimestre_id' => (int) $quarter['id'], 'nombre' => 'Programación', 'codigo' => 'PR1', 'numero_grupo' => 1,
        ]);
        $otherGroup = $courses->createCourse([
            'cuatrimestre_id' => (int) $quarter['id'], 'nombre' => 'Programación', 'codigo' => 'PR1', 'numero_grupo' => 2,
        ]);
        $evaluation = new EvaluationService();
        $catalogId = (int) $this->testDb->table('catalogo_actividades')->get()->getRow('id');
        $component = $evaluation->createComponent((int) $course['id'], [
            'actividad_catalogo_id' => $catalogId, 'titulo' => 'Parcial', 'cantidad_actividades' => 1,
            'porcentaje_total' => '2.00', 'puntaje_por_actividad' => '10.00',
        ]);
        $enrollment = new EnrollmentService();
        $ana = $enrollment->enroll((int) $course['id'], [
            'nombre' => 'Ana', 'primer_apellido' => 'Uno', 'segundo_apellido' => 'Dos',
            'correo' => 'Ana@ucatolica.ac.cr',
        ]);
        $attendance = new AttendanceService();
        $sessionId = (int) $attendance->sessions((int) $course['id'])[0]['id'];
        $session = $attendance->update($sessionId, [
            'asistencias' => [['matricula_id' => $ana['id'], 'condicion' => 'presente']],
        ]);
        $bob = $enrollment->enroll((int) $course['id'], [
            'nombre' => 'Bob', 'primer_apellido' => 'Tres', 'segundo_apellido' => 'Cuatro',
            'correo' => 'bob@ucatolica.ac.cr',
        ]);
        $this->assertSame('justificado', $this->testDb->table('asistencias')
            ->where('sesion_id', $session['id'])->where('matricula_id', $bob['id'])->get()->getRow('condicion'));
        $attendance->update((int) $session['id'], [
            'asistencias' => [
                ['matricula_id' => $ana['id'], 'condicion' => 'presente'],
                ['matricula_id' => $bob['id'], 'condicion' => 'presente'],
            ],
        ]);
        $activityId = (int) $this->testDb->table('actividades_individuales')
            ->where('componente_id', $component['id'])->get()->getRow('id');
        $evaluation->saveGrade($ana['id'], $activityId, [
            'puntos_obtenidos' => '8.50', 'observacion' => 'Buen trabajo',
        ]);
        $report = new ReportService();
        $cards = $report->studentGrades((int) $course['id'], $ana['estudiante_id']);
        $this->assertSame('1.70', $cards['resultado']);
        $this->assertSame('Buen trabajo', $cards['tarjetas'][0]['observacion']);
        $bobRow = array_values(array_filter(
            $report->concentrate((int) $course['id'])['estudiantes'],
            static fn (array $row): bool => $row['estudiante_id'] === $bob['estudiante_id'],
        ))[0];
        $this->assertNull($bobRow['calificaciones'][0]['nota']);
        $path = $report->exportXlsx((int) $course['id']);
        $zip = new ZipArchive();
        $this->assertTrue($zip->open($path) === true);
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $this->assertIsString($sheet);
        $this->assertStringContainsString('8.50', $sheet);
        $this->assertStringContainsString('1.70', $sheet);
        $zip->close();
        unlink($path);

        $courses->deleteCourse((int) $course['id']);
        $this->assertSame(0, $this->testDb->table('matriculas')->where('curso_id', $course['id'])->countAllResults());
        $this->assertNotNull($this->testDb->table('estudiantes')->where('usuario_id', $ana['estudiante_id'])->get()->getRow());
        $this->assertNotNull($this->testDb->table('cursos')->where('id', $otherGroup['id'])->get()->getRow());
        $this->assertNotNull($this->testDb->table('cuatrimestres')->where('id', $quarter['id'])->get()->getRow());
        $this->assertNotNull($this->testDb->table('catalogo_actividades')->where('id', $catalogId)->get()->getRow());
    }

    public function testStudentCardsIncludeCatalogNameAndComponentActivityCount(): void
    {
        $quarter = (new CourseService())->createQuarter([
            'codigo' => '1-2026', 'fecha_inicio' => '2026-01-01', 'fecha_cierre' => '2026-04-09',
        ]);
        $course = (new CourseService())->createCourse([
            'cuatrimestre_id' => (int) $quarter['id'], 'nombre' => 'Curso', 'codigo' => 'C1', 'numero_grupo' => 1,
        ]);
        $this->testDb->table('catalogo_actividades')->insert(['nombre' => 'proyecto']);
        $projectId = (int) $this->testDb->insertID();
        $this->testDb->table('catalogo_actividades')->insert(['nombre' => 'tarea']);
        $taskId = (int) $this->testDb->insertID();
        $evaluation = new EvaluationService();
        foreach ([[$projectId, 'Proyecto en Word', 1], [$taskId, 'Tareas', 2]] as [$catalogId, $title, $count]) {
            $evaluation->createComponent((int) $course['id'], [
                'actividad_catalogo_id' => $catalogId, 'titulo' => $title,
                'cantidad_actividades' => $count, 'porcentaje_total' => '10.00',
                'puntaje_por_actividad' => '10.00',
            ]);
        }
        $student = (new EnrollmentService())->enroll((int) $course['id'], [
            'nombre' => 'Ana', 'primer_apellido' => 'Uno', 'segundo_apellido' => 'Dos',
            'correo' => 'ana@ucatolica.ac.cr',
        ]);
        $password = (new AuthService())->generateStudentPassword($student['estudiante_id'])['contrasena'];
        $token = json_decode($this->jsonRequest('POST', 'api/login', [
            'correo' => 'ana@ucatolica.ac.cr', 'contrasena' => $password,
        ])->getJSON(), true)['data']['token'];

        $response = $this->jsonRequest('GET', 'api/estudiante/cursos/' . $course['id'] . '/calificaciones', null, $token);
        $response->assertStatus(200);
        $cards = json_decode($response->getJSON(), true)['data']['tarjetas'];
        $this->assertCount(3, $cards);
        $this->assertSame(['proyecto', 1, 1, 'Proyecto en Word'], [
            $cards[0]['actividad_catalogo'], $cards[0]['cantidad_actividades'], $cards[0]['numero'], $cards[0]['titulo'],
        ]);
        $this->assertSame(['tarea', 2, 1, 'Tareas'], [
            $cards[1]['actividad_catalogo'], $cards[1]['cantidad_actividades'], $cards[1]['numero'], $cards[1]['titulo'],
        ]);
        $this->assertSame(['tarea', 2, 2, 'Tareas'], [
            $cards[2]['actividad_catalogo'], $cards[2]['cantidad_actividades'], $cards[2]['numero'], $cards[2]['titulo'],
        ]);
        $concentrate = (new ReportService())->concentrate((int) $course['id'], $student['estudiante_id']);
        $this->assertArrayNotHasKey('actividad_catalogo', $concentrate['actividades'][0]);
    }

    public function testCatalogAndComponentLimits(): void
    {
        $this->expectException(ApiError::class);
        (new CatalogService())->create(['nombre' => 'Prúeba parcial']);
    }

    public function testCatalogUsageAndProfessorCanReadPreviousObservation(): void
    {
        (new AuthService())->createProfessor('profesor@ucatolica.ac.cr', 'clave-prueba');
        $token = json_decode($this->jsonRequest('POST', 'api/login', [
            'correo' => 'profesor@ucatolica.ac.cr', 'contrasena' => 'clave-prueba',
        ])->getJSON(), true)['data']['token'];
        $catalogId = (int) $this->testDb->table('catalogo_actividades')->get()->getRow('id');
        $catalog = new CatalogService();
        $this->assertFalse($catalog->all()[0]['en_uso']);
        $newCatalog = $catalog->create(['nombre' => 'Proyecto']);
        $this->assertFalse($newCatalog['en_uso']);
        $this->assertFalse($catalog->update($catalogId, ['nombre' => 'Prueba parcial'])['en_uso']);

        $courses = new CourseService();
        $quarter = $courses->createQuarter(['codigo' => '1-2025', 'fecha_inicio' => '2025-01-01', 'fecha_cierre' => '2025-04-09']);
        $course = $courses->createCourse([
            'cuatrimestre_id' => (int) $quarter['id'], 'nombre' => 'Curso', 'codigo' => 'C1', 'numero_grupo' => 1,
        ]);
        $component = (new EvaluationService())->createComponent((int) $course['id'], [
            'actividad_catalogo_id' => $catalogId, 'titulo' => 'Parcial', 'cantidad_actividades' => 1,
            'porcentaje_total' => '2.00', 'puntaje_por_actividad' => '10.00',
        ]);
        $usage = array_column($catalog->all(), 'en_uso', 'id');
        $this->assertTrue($usage[$catalogId]);
        $this->assertFalse($usage[$newCatalog['id']]);
        $listed = json_decode($this->jsonRequest('GET', 'api/profesor/catalogo', null, $token)->getJSON(), true)['data'];
        $this->assertTrue(array_column($listed, 'en_uso', 'id')[$catalogId]);

        $student = (new EnrollmentService())->enroll((int) $course['id'], [
            'nombre' => 'Ana', 'primer_apellido' => 'Uno', 'segundo_apellido' => 'Dos',
            'correo' => 'ana@ucatolica.ac.cr',
        ]);
        $activityId = (int) $this->testDb->table('actividades_individuales')
            ->where('componente_id', $component['id'])->get()->getRow('id');
        $path = 'api/profesor/calificaciones/' . $student['id'] . '/' . $activityId;
        $this->jsonRequest('GET', $path)->assertStatus(401);
        $password = (new AuthService())->generateStudentPassword($student['estudiante_id'])['contrasena'];
        $studentToken = json_decode($this->jsonRequest('POST', 'api/login', [
            'correo' => 'ana@ucatolica.ac.cr', 'contrasena' => $password,
        ])->getJSON(), true)['data']['token'];
        $this->jsonRequest('GET', $path, null, $studentToken)->assertStatus(403);
        $empty = $this->jsonRequest('GET', $path, null, $token);
        $empty->assertStatus(200);
        $this->assertSame(['matricula_id' => $student['id'], 'actividad_individual_id' => $activityId,
            'puntos_obtenidos' => null, 'observacion' => null], json_decode($empty->getJSON(), true)['data']);

        $this->jsonRequest('PUT', $path, ['puntos_obtenidos' => '8.50', 'observacion' => 'Primera observación'], $token)->assertStatus(200);
        $saved = $this->jsonRequest('GET', $path, null, $token);
        $saved->assertStatus(200);
        $this->assertSame('8.50', json_decode($saved->getJSON(), true)['data']['puntos_obtenidos']);
        $this->assertSame('Primera observación', json_decode($saved->getJSON(), true)['data']['observacion']);
        $this->jsonRequest('PUT', $path, ['puntos_obtenidos' => '9.00', 'observacion' => 'Corregida'], $token)->assertStatus(200);
        $this->assertSame('Corregida', json_decode($this->jsonRequest('GET', $path, null, $token)->getJSON(), true)['data']['observacion']);
        $this->jsonRequest('GET', 'api/profesor/calificaciones/999999/' . $activityId, null, $token)->assertStatus(404);
        $otherCourse = $courses->createCourse([
            'cuatrimestre_id' => (int) $quarter['id'], 'nombre' => 'Otro grupo', 'codigo' => 'C1', 'numero_grupo' => 2,
        ]);
        $otherEnrollment = (new EnrollmentService())->enroll((int) $otherCourse['id'], [
            'nombre' => 'Ana', 'primer_apellido' => 'Uno', 'segundo_apellido' => 'Dos',
            'correo' => 'ana@ucatolica.ac.cr',
        ]);
        $this->jsonRequest('GET', 'api/profesor/calificaciones/' . $otherEnrollment['id'] . '/' . $activityId, null, $token)->assertStatus(409);
        $courses->deleteCourse((int) $course['id']);
        $usageAfterDelete = array_column($catalog->all(), 'en_uso', 'id');
        $this->assertFalse($usageAfterDelete[$catalogId]);
    }

    public function testPercentageDistributionKeepsPerActivityScoreAndGradeBoundaries(): void
    {
        $courses = new CourseService();
        $quarter = $courses->createQuarter(['codigo' => '3-2025', 'fecha_inicio' => '2025-09-01', 'fecha_cierre' => '2025-12-08']);
        $course = $courses->createCourse(['cuatrimestre_id' => (int) $quarter['id'], 'nombre' => 'Matemáticas', 'codigo' => 'M1', 'numero_grupo' => 1]);
        $catalogId = (int) $this->testDb->table('catalogo_actividades')->get()->getRow('id');
        $evaluation = new EvaluationService();
        $component = $evaluation->createComponent((int) $course['id'], [
            'actividad_catalogo_id' => $catalogId, 'titulo' => 'Tres pruebas', 'cantidad_actividades' => 3,
            'porcentaje_total' => '10.00', 'puntaje_por_actividad' => '10.00',
        ]);
        $this->assertCount(3, $component['actividades']);
        foreach ($component['actividades'] as $activity) {
            $this->assertSame('3.33', $activity['porcentaje_asignado']);
            $this->assertSame('10.00', $activity['puntaje_asignado']);
        }
        $this->assertSame('10.00', (new EvaluationService())->components((int) $course['id'])[0]['puntaje_por_actividad']);
        $five = $evaluation->createComponent((int) $course['id'], [
            'actividad_catalogo_id' => $catalogId, 'titulo' => 'Cinco pruebas', 'cantidad_actividades' => 5,
            'porcentaje_total' => '10.00', 'puntaje_por_actividad' => '10.00',
        ]);
        foreach ($five['actividades'] as $activity) {
            $this->assertSame('2.00', $activity['porcentaje_asignado']);
            $this->assertSame('10.00', $activity['puntaje_asignado']);
        }
        foreach (['1.00', '100.00'] as $limitScore) {
            $limit = $evaluation->createComponent((int) $course['id'], [
                'actividad_catalogo_id' => $catalogId, 'titulo' => 'Puntaje límite', 'cantidad_actividades' => 1,
                'porcentaje_total' => '1.00', 'puntaje_por_actividad' => $limitScore,
            ]);
            $this->assertSame($limitScore, $limit['actividades'][0]['puntaje_asignado']);
        }
        foreach (['0.99', '100.01'] as $invalidScore) {
            try {
                $evaluation->createComponent((int) $course['id'], [
                    'actividad_catalogo_id' => $catalogId, 'titulo' => 'Puntaje inválido', 'cantidad_actividades' => 1,
                    'porcentaje_total' => '1.00', 'puntaje_por_actividad' => $invalidScore,
                ]);
                $this->fail('El puntaje por actividad fuera de 1 a 100 debe rechazarse.');
            } catch (ApiError $error) {
                $this->assertSame(400, $error->status);
            }
        }
        foreach (['update', 'delete'] as $operation) {
            try {
                (new CatalogService())->$operation($catalogId, ['nombre' => 'Otro nombre']);
                $this->fail('La actividad vinculada debe permanecer bloqueada.');
            } catch (ApiError $error) {
                $this->assertSame(409, $error->status);
            }
        }
        $student = (new EnrollmentService())->enroll((int) $course['id'], [
            'nombre' => 'Eva', 'primer_apellido' => 'Uno', 'segundo_apellido' => 'Dos', 'correo' => 'eva@ucatolica.ac.cr',
        ]);
        $activityId = (int) $this->testDb->table('actividades_individuales')->where('componente_id', $component['id'])->get()->getRow('id');
        foreach (['-1.00', '10.01'] as $invalid) {
            try {
                $evaluation->saveGrade($student['id'], $activityId, ['puntos_obtenidos' => $invalid]);
                $this->fail('La nota fuera de rango debe rechazarse.');
            } catch (ApiError $error) {
                $this->assertSame(400, $error->status);
            }
        }
        $evaluation->saveGrade($student['id'], $activityId, ['puntos_obtenidos' => '10.00']);
        $this->assertSame('3.33', (new ReportService())->studentGrades((int) $course['id'], $student['estudiante_id'])['resultado']);
    }

    public function testHistoricalScoreBelowNewMinimumRemainsGradable(): void
    {
        $courses = new CourseService();
        $quarter = $courses->createQuarter(['codigo' => '2-2024', 'fecha_inicio' => '2024-05-01', 'fecha_cierre' => '2024-08-07']);
        $course = $courses->createCourse(['cuatrimestre_id' => (int) $quarter['id'], 'nombre' => 'Curso histórico', 'codigo' => 'H1', 'numero_grupo' => 1]);
        $catalogId = (int) $this->testDb->table('catalogo_actividades')->get()->getRow('id');
        $this->testDb->table('componentes_evaluacion')->insert([
            'curso_id' => $course['id'], 'actividad_catalogo_id' => $catalogId,
            'titulo' => 'Componente anterior', 'cantidad_actividades' => 1,
            'porcentaje_total' => '2.00', 'puntaje_por_actividad' => '0.33',
        ]);
        $componentId = (int) $this->testDb->insertID();
        $this->testDb->table('actividades_individuales')->insert([
            'componente_id' => $componentId, 'numero' => 1,
            'porcentaje_asignado' => '2.00', 'puntaje_asignado' => '0.33',
        ]);
        $activityId = (int) $this->testDb->insertID();
        $student = (new EnrollmentService())->enroll((int) $course['id'], [
            'nombre' => 'Eva', 'primer_apellido' => 'Uno', 'segundo_apellido' => 'Dos',
            'correo' => 'eva@ucatolica.ac.cr',
        ]);
        (new EvaluationService())->saveGrade($student['id'], $activityId, ['puntos_obtenidos' => '0.33']);
        $this->assertSame('0.33', (new EvaluationService())->components((int) $course['id'])[0]['puntaje_por_actividad']);
        $this->assertSame('2.00', (new ReportService())->studentGrades((int) $course['id'], $student['estudiante_id'])['resultado']);
        try {
            (new EvaluationService())->createComponent((int) $course['id'], [
                'actividad_catalogo_id' => $catalogId, 'titulo' => 'Nuevo componente',
                'cantidad_actividades' => 1, 'porcentaje_total' => '1.00',
                'puntaje_por_actividad' => '0.33',
            ]);
            $this->fail('Un componente nuevo no puede tener menos de un punto por actividad.');
        } catch (ApiError $error) {
            $this->assertSame(400, $error->status);
        }
    }

    public function testQuarterCalendarAndAttendanceOnExistingSessions(): void
    {
        $courses = new CourseService();
        $quarter = $courses->createQuarter(['codigo' => '1-2024', 'fecha_inicio' => '2024-01-01', 'fecha_cierre' => '2024-04-08']);
        $course = $courses->createCourse(['cuatrimestre_id' => (int) $quarter['id'], 'nombre' => 'Curso', 'codigo' => 'C1', 'numero_grupo' => 1]);
        $this->assertSame(15, $this->testDb->table('sesiones_cuatrimestre')->where('cuatrimestre_id', $quarter['id'])->countAllResults());
        $attendance = new AttendanceService();
        $sessions = $attendance->sessions((int) $course['id']);
        $this->assertCount(15, $sessions);
        $this->assertSame('2024-01-01', $sessions[0]['fecha']);
        $this->assertSame('2024-04-08', $sessions[14]['fecha']);
        $this->assertSame(15, $sessions[14]['numero']);
        $this->assertFalse($sessions[0]['asistencia_registrada']);
        $this->assertSame([], $sessions[0]['asistencias']);
        $student = (new EnrollmentService())->enroll((int) $course['id'], [
            'nombre' => 'Ana', 'primer_apellido' => 'Uno', 'segundo_apellido' => 'Dos', 'correo' => 'ana@ucatolica.ac.cr',
        ]);
        $this->assertSame(0, $this->testDb->table('asistencias')->countAllResults());
        try {
            $attendance->update((int) $sessions[0]['id'], ['asistencias' => []]);
            $this->fail('La captura incompleta debe rechazarse.');
        } catch (ApiError $error) {
            $this->assertSame(400, $error->status);
        }
        $entry = [['matricula_id' => $student['id'], 'condicion' => 'presente']];
        $saved = $attendance->update((int) $sessions[0]['id'], ['asistencias' => $entry]);
        $this->assertTrue($saved['asistencia_registrada']);
        $this->assertCount(1, $saved['asistencias']);
        $this->assertFalse($attendance->sessions((int) $course['id'])[1]['asistencia_registrada']);
        $this->assertCount(1, (new ReportService())->studentAttendance((int) $course['id'], (int) $student['estudiante_id']));
        $second = (new EnrollmentService())->enroll((int) $course['id'], [
            'nombre' => 'Luis', 'primer_apellido' => 'Tres', 'segundo_apellido' => 'Cuatro', 'correo' => 'luis@ucatolica.ac.cr',
        ]);
        $this->assertSame(1, $this->testDb->table('asistencias')->where('matricula_id', $second['id'])->countAllResults());
        $this->assertSame('justificado', $this->testDb->table('asistencias')->where('matricula_id', $second['id'])->get()->getRow('condicion'));
        try {
            $attendance->update((int) $sessions[0]['id'], ['asistencias' => $entry]);
            $this->fail('La corrección parcial debe rechazarse.');
        } catch (ApiError $error) {
            $this->assertSame(400, $error->status);
        }
        $attendance->update((int) $sessions[0]['id'], ['asistencias' => [
            $entry[0], ['matricula_id' => $second['id'], 'condicion' => 'ausente'],
        ]]);
        $this->assertSame('ausente', $this->testDb->table('asistencias')->where('matricula_id', $second['id'])->get()->getRow('condicion'));
    }

    public function testQuarterRequiresExactlyNinetyEightDaysAndManualSessionRouteIsGone(): void
    {
        $service = new CourseService();
        foreach (['2026-12-08', '2026-12-10'] as $end) {
            try {
                $service->createQuarter([
                    'codigo' => '3-2026', 'fecha_inicio' => '2026-09-02', 'fecha_cierre' => $end,
                ]);
                $this->fail('La duración distinta de 98 días debe rechazarse.');
            } catch (ApiError $error) {
                $this->assertSame(400, $error->status);
            }
        }
        $this->assertSame(0, $this->testDb->table('sesiones_cuatrimestre')->countAllResults());
        $quarter = $service->createQuarter([
            'codigo' => '3-2026', 'fecha_inicio' => '2026-09-02', 'fecha_cierre' => '2026-12-09',
        ]);
        $course = $service->createCourse([
            'cuatrimestre_id' => (int) $quarter['id'], 'nombre' => 'Informática', 'codigo' => 'PD008', 'numero_grupo' => 1,
        ]);
        $this->assertSame(15, $this->testDb->table('sesiones_cuatrimestre')->countAllResults());
        $this->assertSame(15, $this->testDb->table('sesiones_asistencia')->countAllResults());
        $this->assertSame(0, $this->testDb->table('asistencias')->countAllResults());
        $sessions = (new AttendanceService())->sessions((int) $course['id']);
        $this->assertSame('2026-09-02', $sessions[0]['fecha']);
        $this->assertSame('2026-12-09', $sessions[14]['fecha']);
        $this->assertFalse($sessions[0]['asistencia_registrada']);
        try {
            (new AttendanceService())->update((int) $sessions[0]['id'], ['asistencias' => []]);
            $this->fail('No se registra asistencia sin matrículas.');
        } catch (ApiError $error) {
            $this->assertSame(400, $error->status);
        }
        $this->expectException(\CodeIgniter\Exceptions\PageNotFoundException::class);
        $this->jsonRequest('POST', 'api/profesor/cursos/' . $course['id'] . '/sesiones', [
            'fecha' => '2026-09-02', 'asistencias' => [],
        ]);
    }

    public function testInitialCatalogCaseSensitiveEmailAndCrossCourseGrade(): void
    {
        \Config\Database::seeder('tests')->call('InitialDataSeeder');
        $this->assertSame(7, $this->testDb->table('catalogo_actividades')->countAllResults());
        $this->assertSame(1, $this->testDb->table('catalogo_actividades')->where('nombre', 'INDAGACION Y EXPOSICION')->countAllResults());
        $courses = new CourseService();
        $quarter = $courses->createQuarter(['codigo' => '2-2024', 'fecha_inicio' => '2024-05-01', 'fecha_cierre' => '2024-08-07']);
        $first = $courses->createCourse(['cuatrimestre_id' => (int) $quarter['id'], 'nombre' => 'Grupo uno', 'codigo' => 'C1', 'numero_grupo' => 1]);
        $second = $courses->createCourse(['cuatrimestre_id' => (int) $quarter['id'], 'nombre' => 'Grupo dos', 'codigo' => 'C1', 'numero_grupo' => 2]);
        $enrollments = new EnrollmentService();
        $upper = $enrollments->enroll((int) $first['id'], [
            'nombre' => 'Eva', 'primer_apellido' => 'Uno', 'segundo_apellido' => 'Dos', 'correo' => 'Eva@ucatolica.ac.cr',
        ]);
        $lower = $enrollments->enroll((int) $second['id'], [
            'nombre' => 'Eva', 'primer_apellido' => 'Uno', 'segundo_apellido' => 'Dos', 'correo' => 'eva@ucatolica.ac.cr',
        ]);
        $this->assertNotSame($upper['estudiante_id'], $lower['estudiante_id']);
        $catalogId = (int) $this->testDb->table('catalogo_actividades')->where('nombre', 'proyecto')->get()->getRow('id');
        $component = (new EvaluationService())->createComponent((int) $second['id'], [
            'actividad_catalogo_id' => $catalogId, 'titulo' => 'Proyecto', 'cantidad_actividades' => 1,
            'porcentaje_total' => '2.00', 'puntaje_por_actividad' => '10.00',
        ]);
        $activityId = (int) $this->testDb->table('actividades_individuales')->where('componente_id', $component['id'])->get()->getRow('id');
        try {
            (new EvaluationService())->saveGrade($upper['id'], $activityId, ['puntos_obtenidos' => '5.00']);
            $this->fail('La actividad de otro grupo debe rechazarse.');
        } catch (ApiError $error) {
            $this->assertSame(409, $error->status);
        }
    }

    public function testEachContributionIsTruncatedBeforeTheCourseSum(): void
    {
        $courses = new CourseService();
        $quarter = $courses->createQuarter(['codigo' => '3-2024', 'fecha_inicio' => '2024-09-01', 'fecha_cierre' => '2024-12-08']);
        $course = $courses->createCourse(['cuatrimestre_id' => (int) $quarter['id'], 'nombre' => 'Curso', 'codigo' => 'C1', 'numero_grupo' => 1]);
        $catalogId = (int) $this->testDb->table('catalogo_actividades')->get()->getRow('id');
        $component = (new EvaluationService())->createComponent((int) $course['id'], [
            'actividad_catalogo_id' => $catalogId, 'titulo' => 'Dos actividades', 'cantidad_actividades' => 2,
            'porcentaje_total' => '4.00', 'puntaje_por_actividad' => '10.00',
        ]);
        $student = (new EnrollmentService())->enroll((int) $course['id'], [
            'nombre' => 'Ana', 'primer_apellido' => 'Uno', 'segundo_apellido' => 'Dos', 'correo' => 'ana@ucatolica.ac.cr',
        ]);
        $activities = $this->testDb->table('actividades_individuales')->where('componente_id', $component['id'])->orderBy('numero')->get()->getResultArray();
        $report = new ReportService();
        $this->assertSame('0.00', $report->studentGrades((int) $course['id'], $student['estudiante_id'])['resultado']);
        $this->assertNull($report->concentrate((int) $course['id'])['estudiantes'][0]['calificaciones'][0]['nota']);
        foreach ($activities as $activity) {
            (new EvaluationService())->saveGrade($student['id'], (int) $activity['id'], ['puntos_obtenidos' => '8.49']);
        }
        $this->assertSame('3.38', $report->studentGrades((int) $course['id'], $student['estudiante_id'])['resultado']);
    }

    public function testSpecificTareasCorrectionPreservesWeightsAndOtherComponents(): void
    {
        $courses = new CourseService();
        $quarter = $courses->createQuarter(['codigo' => '3-2026', 'fecha_inicio' => '2026-09-01', 'fecha_cierre' => '2026-12-08']);
        $course = $courses->createCourse([
            'cuatrimestre_id' => (int) $quarter['id'], 'nombre' => 'Informática Aplicada 1',
            'codigo' => 'PD008', 'numero_grupo' => 1,
        ]);
        $otherCourse = $courses->createCourse([
            'cuatrimestre_id' => (int) $quarter['id'], 'nombre' => 'Otro curso',
            'codigo' => 'OTRO', 'numero_grupo' => 1,
        ]);
        $catalogId = (int) $this->testDb->table('catalogo_actividades')->get()->getRow('id');
        $evaluation = new EvaluationService();
        $input = [
            'actividad_catalogo_id' => $catalogId, 'titulo' => 'Tareas',
            'cantidad_actividades' => 7, 'porcentaje_total' => '15.00',
            'puntaje_por_actividad' => '10.00',
        ];
        $target = $evaluation->createComponent((int) $course['id'], $input);
        $other = $evaluation->createComponent((int) $otherCourse['id'], $input);
        foreach ([$target['id'], $other['id']] as $componentId) {
            $this->testDb->table('actividades_individuales')->where('componente_id', $componentId)
                ->update(['puntaje_asignado' => '1.42']);
        }

        require_once APPPATH . 'Database/Migrations/2026-10-24-000001_CorrectTareasActivityScore.php';
        $migration = new \App\Database\Migrations\CorrectTareasActivityScore();
        $migration->up();
        $migration->up();

        $targetRows = $this->testDb->table('actividades_individuales')
            ->where('componente_id', $target['id'])->orderBy('numero')->get()->getResultArray();
        $otherRows = $this->testDb->table('actividades_individuales')
            ->where('componente_id', $other['id'])->orderBy('numero')->get()->getResultArray();
        $this->assertCount(7, $targetRows);
        $this->assertCount(7, $otherRows);
        foreach ($targetRows as $row) {
            $this->assertSame('10.00', $row['puntaje_asignado']);
            $this->assertSame('2.14', $row['porcentaje_asignado']);
        }
        foreach ($otherRows as $row) {
            $this->assertSame('1.42', $row['puntaje_asignado']);
        }
    }

    public function testComponentDeletionRequiresFreshConfirmationAndRecalculatesViews(): void
    {
        $auth = new AuthService();
        $auth->createProfessor('profesor@ucatolica.ac.cr', 'clave-prueba');
        $token = json_decode($this->jsonRequest('POST', 'api/login', [
            'correo' => 'profesor@ucatolica.ac.cr', 'contrasena' => 'clave-prueba',
        ])->getJSON(), true)['data']['token'];
        $courses = new CourseService();
        $quarter = $courses->createQuarter(['codigo' => '1-2099', 'fecha_inicio' => '2099-01-01', 'fecha_cierre' => '2099-04-09']);
        $course = $courses->createCourse(['cuatrimestre_id' => (int) $quarter['id'], 'nombre' => 'Curso', 'codigo' => 'C1', 'numero_grupo' => 1]);
        $otherCourse = $courses->createCourse(['cuatrimestre_id' => (int) $quarter['id'], 'nombre' => 'Otro grupo', 'codigo' => 'C1', 'numero_grupo' => 2]);
        $catalogId = (int) $this->testDb->table('catalogo_actividades')->get()->getRow('id');
        $evaluation = new EvaluationService();
        $graded = $evaluation->createComponent((int) $course['id'], [
            'actividad_catalogo_id' => $catalogId, 'titulo' => 'Calificado', 'cantidad_actividades' => 2,
            'porcentaje_total' => '10.00', 'puntaje_por_actividad' => '10.00',
        ]);
        $remaining = $evaluation->createComponent((int) $course['id'], [
            'actividad_catalogo_id' => $catalogId, 'titulo' => 'Permanece', 'cantidad_actividades' => 1,
            'porcentaje_total' => '5.00', 'puntaje_por_actividad' => '10.00',
        ]);
        $other = $evaluation->createComponent((int) $otherCourse['id'], [
            'actividad_catalogo_id' => $catalogId, 'titulo' => 'Otro curso', 'cantidad_actividades' => 1,
            'porcentaje_total' => '5.00', 'puntaje_por_actividad' => '10.00',
        ]);
        $student = (new EnrollmentService())->enroll((int) $course['id'], [
            'nombre' => 'Ana', 'primer_apellido' => 'Uno', 'segundo_apellido' => 'Dos',
            'correo' => 'ana@ucatolica.ac.cr',
        ]);
        $firstSession = (new AttendanceService())->sessions((int) $course['id'])[0];
        (new AttendanceService())->update((int) $firstSession['id'], [
            'asistencias' => [['matricula_id' => $student['id'], 'condicion' => 'presente']],
        ]);
        $activityIds = array_column($this->testDb->table('actividades_individuales')
            ->where('componente_id', $graded['id'])->orderBy('numero')->get()->getResultArray(), 'id');
        $evaluation->saveGrade((int) $student['id'], (int) $activityIds[0], [
            'puntos_obtenidos' => '0.00', 'observacion' => 'Nota cero',
        ]);
        $evaluation->saveGrade((int) $student['id'], (int) $activityIds[1], [
            'puntos_obtenidos' => '8.50', 'observacion' => 'Avance',
        ]);
        $report = new ReportService();
        $this->assertSame('4.25', $report->studentGrades((int) $course['id'], (int) $student['estudiante_id'])['resultado']);
        $path = 'api/profesor/cursos/' . $course['id'] . '/componentes/' . $graded['id'];
        $listed = json_decode($this->jsonRequest('GET', 'api/profesor/cursos/' . $course['id'] . '/componentes', null, $token)->getJSON(), true)['data'];
        $this->assertTrue($listed[0]['tiene_calificaciones']);
        $this->assertFalse($listed[1]['tiene_calificaciones']);
        $this->jsonRequest('DELETE', $path)->assertStatus(401);
        $this->jsonRequest('DELETE', $path . '?confirmar_con_notas=invalido', null, $token)->assertStatus(400);
        $this->jsonRequest('DELETE', $path, null, $token)->assertStatus(409);
        $this->assertSame(2, $this->testDb->table('calificaciones')->countAllResults());
        $this->jsonRequest('DELETE', 'api/profesor/cursos/' . $otherCourse['id'] . '/componentes/' . $graded['id'], null, $token)->assertStatus(404);
        $deleted = $this->jsonRequest('DELETE', $path . '?confirmar_con_notas=true', null, $token);
        $deleted->assertStatus(200);
        $this->assertSame(['data' => ['eliminado' => true]], json_decode($deleted->getJSON(), true));
        $this->jsonRequest('DELETE', $path . '?confirmar_con_notas=true', null, $token)->assertStatus(404);
        $this->assertSame(0, $this->testDb->table('calificaciones')->countAllResults());
        $this->assertSame(0, $this->testDb->table('actividades_individuales')->whereIn('id', $activityIds)->countAllResults());
        $this->assertSame('0.00', $report->studentGrades((int) $course['id'], (int) $student['estudiante_id'])['resultado']);
        $this->assertCount(1, $report->concentrate((int) $course['id'])['actividades']);
        $this->assertCount(1, $evaluation->components((int) $course['id']));
        $this->assertSame((int) $remaining['id'], (int) $evaluation->components((int) $course['id'])[0]['id']);
        $this->assertSame(1, $this->testDb->table('matriculas')->countAllResults());
        $this->assertSame(30, $this->testDb->table('sesiones_asistencia')->countAllResults());
        $this->assertSame(1, $this->testDb->table('asistencias')->countAllResults());
        $this->assertSame(2, $this->testDb->table('cursos')->countAllResults());
        $this->assertSame(1, $this->testDb->table('componentes_evaluacion')->where('id', $other['id'])->countAllResults());
        $this->assertTrue(array_column((new CatalogService())->all(), 'en_uso', 'id')[$catalogId]);
        $evaluation->createComponent((int) $course['id'], [
            'actividad_catalogo_id' => $catalogId, 'titulo' => 'Porcentaje liberado', 'cantidad_actividades' => 1,
            'porcentaje_total' => '95.00', 'puntaje_por_actividad' => '10.00',
        ]);
    }

    public function testUngradedComponentCanBeDeletedWithoutFlagAndPreservesCatalog(): void
    {
        $auth = new AuthService();
        $auth->createProfessor('profesor@ucatolica.ac.cr', 'clave-prueba');
        $token = json_decode($this->jsonRequest('POST', 'api/login', [
            'correo' => 'profesor@ucatolica.ac.cr', 'contrasena' => 'clave-prueba',
        ])->getJSON(), true)['data']['token'];
        $courses = new CourseService();
        $quarter = $courses->createQuarter(['codigo' => '1-2099', 'fecha_inicio' => '2099-01-01', 'fecha_cierre' => '2099-04-09']);
        $course = $courses->createCourse(['cuatrimestre_id' => (int) $quarter['id'], 'nombre' => 'Curso', 'codigo' => 'C1', 'numero_grupo' => 1]);
        $catalogId = (int) $this->testDb->table('catalogo_actividades')->get()->getRow('id');
        $component = (new EvaluationService())->createComponent((int) $course['id'], [
            'actividad_catalogo_id' => $catalogId, 'titulo' => 'Sin notas', 'cantidad_actividades' => 1,
            'porcentaje_total' => '5.00', 'puntaje_por_actividad' => '10.00',
        ]);
        $path = 'api/profesor/cursos/' . $course['id'] . '/componentes/' . $component['id'];
        $student = (new EnrollmentService())->enroll((int) $course['id'], [
            'nombre' => 'Ana', 'primer_apellido' => 'Uno', 'segundo_apellido' => 'Dos',
            'correo' => 'ana@ucatolica.ac.cr',
        ]);
        $password = $auth->generateStudentPassword((int) $student['estudiante_id'])['contrasena'];
        $studentToken = json_decode($this->jsonRequest('POST', 'api/login', [
            'correo' => 'ana@ucatolica.ac.cr', 'contrasena' => $password,
        ])->getJSON(), true)['data']['token'];
        $this->jsonRequest('DELETE', $path, null, $studentToken)->assertStatus(403);
        $this->jsonRequest('DELETE', 'api/profesor/cursos/999999/componentes/' . $component['id'], null, $token)->assertStatus(404);
        $this->jsonRequest('DELETE', $path, null, $token)->assertStatus(200);
        $this->assertSame(0, $this->testDb->table('componentes_evaluacion')->countAllResults());
        $this->assertSame(1, $this->testDb->table('catalogo_actividades')->countAllResults());
        $this->assertFalse(array_column((new CatalogService())->all(), 'en_uso', 'id')[$catalogId]);
    }

    public function testCourseCannotBeDeletedBeforeQuarterCloses(): void
    {
        $courses = new CourseService();
        $quarter = $courses->createQuarter(['codigo' => '1-2099', 'fecha_inicio' => '2099-01-01', 'fecha_cierre' => '2099-04-09']);
        $course = $courses->createCourse(['cuatrimestre_id' => (int) $quarter['id'], 'nombre' => 'Curso', 'codigo' => 'C1', 'numero_grupo' => 1]);
        try {
            $courses->deleteCourse((int) $course['id']);
            $this->fail('El curso abierto no debe eliminarse.');
        } catch (ApiError $error) {
            $this->assertSame(409, $error->status);
        }
        $this->assertNotNull($this->testDb->table('cursos')->where('id', $course['id'])->get()->getRow());
    }

    private function jsonRequest(string $method, string $path, ?array $body = null, ?string $token = null)
    {
        $headers = ['Content-Type' => 'application/json'];
        if ($token !== null) {
            $headers['Authorization'] = 'Bearer ' . $token;
        }
        $this->withHeaders($headers)->withBodyFormat('json');
        if ($body !== null) {
            $this->withBody(json_encode($body, JSON_THROW_ON_ERROR));
        }
        return $this->call($method, $path);
    }
}
