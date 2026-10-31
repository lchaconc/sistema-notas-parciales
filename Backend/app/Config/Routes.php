<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->setAutoRoute(false);
$routes->post('api/login', 'AuthController::login');
$routes->get('api/healt', 'HealthController::index');

$routes->group('api/profesor', ['filter' => 'jwt:profesor'], static function ($routes) {
    $routes->post('estudiantes/(:num)/contrasena', 'AuthController::generatePassword/$1');
    $routes->get('cuatrimestres', 'CourseController::quarters');
    $routes->post('cuatrimestres', 'CourseController::createQuarter');
    $routes->get('cursos', 'CourseController::courses');
    $routes->post('cursos', 'CourseController::createCourse');
    $routes->delete('cursos/(:num)', 'CourseController::deleteCourse/$1');
    $routes->get('catalogo', 'CatalogController::index');
    $routes->post('catalogo', 'CatalogController::create');
    $routes->put('catalogo/(:num)', 'CatalogController::update/$1');
    $routes->delete('catalogo/(:num)', 'CatalogController::delete/$1');
    $routes->get('cursos/(:num)/componentes', 'EvaluationController::components/$1');
    $routes->post('cursos/(:num)/componentes', 'EvaluationController::createComponent/$1');
    $routes->delete('cursos/(:num)/componentes/(:num)', 'EvaluationController::deleteComponent/$1/$2');
    $routes->get('calificaciones/(:num)/(:num)', 'EvaluationController::grade/$1/$2');
    $routes->put('calificaciones/(:num)/(:num)', 'EvaluationController::saveGrade/$1/$2');
    $routes->get('estudiantes', 'EnrollmentController::students');
    $routes->get('cursos/(:num)/matriculas', 'EnrollmentController::enrollments/$1');
    $routes->post('cursos/(:num)/matriculas', 'EnrollmentController::enroll/$1');
    $routes->get('cursos/(:num)/sesiones', 'AttendanceController::sessions/$1');
    $routes->put('sesiones/(:num)/asistencias', 'AttendanceController::update/$1');
    $routes->get('cursos/(:num)/concentrado', 'ReportController::professorConcentrate/$1');
    $routes->get('cursos/(:num)/concentrado.xlsx', 'ReportController::professorXlsx/$1');
});

$routes->group('api/estudiante', ['filter' => 'jwt:estudiante'], static function ($routes) {
    $routes->put('perfil/contrasena', 'AuthController::changePassword');
    $routes->get('cursos', 'ReportController::studentCourses');
    $routes->get('cursos/(:num)/calificaciones', 'ReportController::studentGrades/$1');
    $routes->get('cursos/(:num)/asistencia', 'ReportController::studentAttendance/$1');
    $routes->get('cursos/(:num)/concentrado', 'ReportController::studentConcentrate/$1');
});
