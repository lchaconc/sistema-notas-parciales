<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\Forge;

class CreateAcademicSchema extends Migration
{
    public function __construct(?Forge $forge = null)
    {
        if (getenv('DB_TARGET') === 'test' || ENVIRONMENT === 'testing') {
            $this->DBGroup = 'tests';
        }
        parent::__construct($forge);
    }

    public function up()
    {
        $statements = [
            <<<'SQL'
CREATE TABLE dominios_permitidos (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    dominio VARCHAR(253) NOT NULL,
    UNIQUE KEY uq_dominios_dominio (dominio)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL,
            <<<'SQL'
CREATE TABLE usuarios (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    correo VARCHAR(254) COLLATE utf8mb4_0900_as_cs NOT NULL,
    contrasena_hash VARCHAR(255) NULL,
    rol ENUM('profesor', 'estudiante') NOT NULL,
    UNIQUE KEY uq_usuarios_correo (correo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL,
            <<<'SQL'
CREATE TABLE estudiantes (
    usuario_id BIGINT UNSIGNED PRIMARY KEY,
    nombre VARCHAR(255) NOT NULL,
    primer_apellido VARCHAR(255) NOT NULL,
    segundo_apellido VARCHAR(255) NOT NULL,
    CONSTRAINT fk_estudiantes_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL,
            <<<'SQL'
CREATE TABLE cuatrimestres (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(6) NOT NULL,
    fecha_inicio DATE NOT NULL,
    fecha_cierre DATE NOT NULL,
    UNIQUE KEY uq_cuatrimestres_codigo (codigo),
    CONSTRAINT chk_cuatrimestres_fechas CHECK (fecha_inicio <= fecha_cierre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL,
            <<<'SQL'
CREATE TABLE cursos (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cuatrimestre_id BIGINT UNSIGNED NOT NULL,
    nombre VARCHAR(255) NOT NULL,
    codigo VARCHAR(64) NOT NULL,
    numero_grupo INT NOT NULL,
    UNIQUE KEY uq_cursos_grupo (cuatrimestre_id, codigo, numero_grupo),
    CONSTRAINT fk_cursos_cuatrimestre FOREIGN KEY (cuatrimestre_id) REFERENCES cuatrimestres(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL,
            <<<'SQL'
CREATE TABLE catalogo_actividades (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(255) COLLATE utf8mb4_0900_ai_ci NOT NULL,
    UNIQUE KEY uq_catalogo_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL,
            <<<'SQL'
CREATE TABLE componentes_evaluacion (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    curso_id BIGINT UNSIGNED NOT NULL,
    actividad_catalogo_id BIGINT UNSIGNED NOT NULL,
    titulo TEXT NOT NULL,
    cantidad_actividades TINYINT UNSIGNED NOT NULL,
    descripcion VARCHAR(256) NULL,
    porcentaje_total DECIMAL(5,2) NOT NULL,
    puntaje_total DECIMAL(5,2) NOT NULL,
    KEY idx_componentes_catalogo (actividad_catalogo_id),
    CONSTRAINT fk_componentes_curso FOREIGN KEY (curso_id) REFERENCES cursos(id) ON DELETE CASCADE,
    CONSTRAINT fk_componentes_catalogo FOREIGN KEY (actividad_catalogo_id) REFERENCES catalogo_actividades(id) ON DELETE RESTRICT,
    CONSTRAINT chk_componentes_cantidad CHECK (cantidad_actividades BETWEEN 1 AND 15),
    CONSTRAINT chk_componentes_porcentaje CHECK (porcentaje_total BETWEEN 0 AND 100),
    CONSTRAINT chk_componentes_puntaje CHECK (puntaje_total BETWEEN 5 AND 100)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL,
            <<<'SQL'
CREATE TABLE actividades_individuales (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    componente_id BIGINT UNSIGNED NOT NULL,
    numero TINYINT UNSIGNED NOT NULL,
    porcentaje_asignado DECIMAL(5,2) NOT NULL,
    puntaje_asignado DECIMAL(5,2) NOT NULL,
    UNIQUE KEY uq_actividades_numero (componente_id, numero),
    CONSTRAINT fk_actividades_componente FOREIGN KEY (componente_id) REFERENCES componentes_evaluacion(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL,
            <<<'SQL'
CREATE TABLE matriculas (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    estudiante_id BIGINT UNSIGNED NOT NULL,
    curso_id BIGINT UNSIGNED NOT NULL,
    UNIQUE KEY uq_matriculas_estudiante_curso (estudiante_id, curso_id),
    KEY idx_matriculas_curso (curso_id),
    CONSTRAINT fk_matriculas_estudiante FOREIGN KEY (estudiante_id) REFERENCES estudiantes(usuario_id) ON DELETE RESTRICT,
    CONSTRAINT fk_matriculas_curso FOREIGN KEY (curso_id) REFERENCES cursos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL,
            <<<'SQL'
CREATE TABLE calificaciones (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    matricula_id BIGINT UNSIGNED NOT NULL,
    actividad_individual_id BIGINT UNSIGNED NOT NULL,
    puntos_obtenidos DECIMAL(5,2) NULL,
    observacion VARCHAR(125) NULL,
    UNIQUE KEY uq_calificaciones_matricula_actividad (matricula_id, actividad_individual_id),
    KEY idx_calificaciones_actividad (actividad_individual_id),
    CONSTRAINT fk_calificaciones_matricula FOREIGN KEY (matricula_id) REFERENCES matriculas(id) ON DELETE CASCADE,
    CONSTRAINT fk_calificaciones_actividad FOREIGN KEY (actividad_individual_id) REFERENCES actividades_individuales(id) ON DELETE CASCADE,
    CONSTRAINT chk_calificaciones_no_negativas CHECK (puntos_obtenidos IS NULL OR puntos_obtenidos >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL,
            <<<'SQL'
CREATE TABLE sesiones_asistencia (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    curso_id BIGINT UNSIGNED NOT NULL,
    fecha DATE NOT NULL,
    UNIQUE KEY uq_sesiones_curso_fecha (curso_id, fecha),
    CONSTRAINT fk_sesiones_curso FOREIGN KEY (curso_id) REFERENCES cursos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL,
            <<<'SQL'
CREATE TABLE asistencias (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sesion_id BIGINT UNSIGNED NOT NULL,
    matricula_id BIGINT UNSIGNED NOT NULL,
    condicion ENUM('presente', 'ausente', 'justificado') NOT NULL,
    UNIQUE KEY uq_asistencias_sesion_matricula (sesion_id, matricula_id),
    KEY idx_asistencias_matricula (matricula_id),
    CONSTRAINT fk_asistencias_sesion FOREIGN KEY (sesion_id) REFERENCES sesiones_asistencia(id) ON DELETE CASCADE,
    CONSTRAINT fk_asistencias_matricula FOREIGN KEY (matricula_id) REFERENCES matriculas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL,
        ];

        foreach ($statements as $statement) {
            $this->db->query($statement);
        }
    }

    public function down()
    {
        foreach ([
            'asistencias', 'sesiones_asistencia', 'calificaciones', 'matriculas',
            'actividades_individuales', 'componentes_evaluacion', 'catalogo_actividades',
            'cursos', 'cuatrimestres', 'estudiantes', 'usuarios', 'dominios_permitidos',
        ] as $table) {
            $this->db->query('DROP TABLE IF EXISTS ' . $table);
        }
    }
}
