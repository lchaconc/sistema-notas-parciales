# Contrato HTTP del backend

La versión ejecutable de este contrato en OpenAPI 3.0.4 está en [API-DOCS/openapi.yaml](../API-DOCS/openapi.yaml) y se visualiza con Swagger UI en Docker.

Todas las rutas comienzan con `/api`. Salvo `POST /api/login` y `GET /api/healt`, requieren `Authorization: Bearer <JWT>`. Las solicitudes con cuerpo usan `Content-Type: application/json` y un objeto JSON. Las respuestas normales son `{ "data": ... }`; los rechazos controlados son `{ "error": "motivo" }`. Se usan `400` para datos inválidos, `401` para autenticación ausente o inválida, `403` para rol incorrecto, `404` para recurso o matrícula inexistente, `409` para conflictos, `500` para fallos internos y `503` cuando la comprobación de MySQL falla. Las creaciones devuelven `201`; las demás operaciones correctas, `200`. La descarga `.xlsx` devuelve contenido binario.

## Sesión e identidad

| Método y ruta | Rol | Entrada o salida principal |
| --- | --- | --- |
| `POST /login` | Público | `{correo, contrasena}` → `{token, expires_in: 3600, rol}`. |
| `GET /healt` | Público | Sin entrada ni JWT. `200`: `{data: {servidor: "ok", base_datos: "ok"}}` tras `SELECT 1` en MySQL; `503`: `{error: "Base de datos no disponible."}` si falla la comprobación. |
| `POST /profesor/estudiantes/{estudianteId}/contrasena` | Profesor | Genera otra clave y devuelve `{contrasena}` una sola vez. |
| `PUT /estudiante/perfil/contrasena` | Estudiante | `{contrasena_nueva}` → `{actualizada: true}`. |

## Gestión del profesor

| Método y ruta | Entrada o salida principal |
| --- | --- |
| `GET /profesor/cuatrimestres` | Lista de cuatrimestres. |
| `POST /profesor/cuatrimestres` | `{codigo, fecha_inicio, fecha_cierre}`. |
| `GET /profesor/cursos` | Lista de cursos con su cuatrimestre y fechas. |
| `POST /profesor/cursos` | `{cuatrimestre_id, nombre, codigo, numero_grupo}`. |
| `DELETE /profesor/cursos/{cursoId}` | Elimina tras el cierre; requiere confirmación previa en la interfaz. |
| `GET /profesor/catalogo` | Lista de actividades del catálogo, cada una con `en_uso` booleano. |
| `POST /profesor/catalogo` | `{nombre}` → actividad con `en_uso: false`. |
| `PUT /profesor/catalogo/{id}` | `{nombre}` → actividad con `en_uso: false`; rechaza si está en uso. |
| `DELETE /profesor/catalogo/{id}` | Elimina una opción libre. |
| `GET /profesor/cursos/{cursoId}/componentes` | Componentes y sus actividades individuales. |
| `POST /profesor/cursos/{cursoId}/componentes` | `{actividad_catalogo_id, titulo, cantidad_actividades, descripcion?, porcentaje_total, puntaje_por_actividad}`. El porcentaje se reparte; el puntaje de `1,00` a `100,00` se asigna íntegro a cada actividad. |
| `GET /profesor/calificaciones/{matriculaId}/{actividadId}` | Nota y observación vigentes de esa actividad y matrícula; ambos `null` si aún no hay calificación. Rechaza asociaciones entre cursos distintos. |
| `GET /profesor/estudiantes` | Lista de estudiantes. |
| `GET /profesor/cursos/{cursoId}/matriculas` | Matrículas del curso. |
| `POST /profesor/cursos/{cursoId}/matriculas` | `{nombre, primer_apellido, segundo_apellido, correo}`. |
| `PUT /profesor/calificaciones/{matriculaId}/{actividadId}` | `{puntos_obtenidos, observacion?}`. Los puntos se ingresan sobre el puntaje de esa actividad. |
| `GET /profesor/cursos/{cursoId}/sesiones` | Sesiones y condiciones de todos los matriculados. |
| `POST /profesor/cursos/{cursoId}/sesiones` | `{fecha, asistencias: [{matricula_id, condicion}, ...]}`; exige todos los matriculados. |
| `PUT /profesor/sesiones/{sesionId}/asistencias` | `{asistencias: [{matricula_id, condicion}, ...]}`; permite editar condiciones existentes. |
| `GET /profesor/cursos/{cursoId}/concentrado` | Tabla vigente de estudiantes por actividad. |
| `GET /profesor/cursos/{cursoId}/concentrado.xlsx` | Descarga la misma tabla en `.xlsx`. |

## Consultas del estudiante

El identificador de estudiante procede exclusivamente del JWT. Cada `{cursoId}` se verifica contra sus matrículas.

| Método y ruta | Salida principal |
| --- | --- |
| `GET /estudiante/cursos` | Cursos propios. |
| `GET /estudiante/cursos/{cursoId}/calificaciones` | Tarjetas con nota, porcentaje obtenido, observación y resultado del curso. |
| `GET /estudiante/cursos/{cursoId}/asistencia` | Fecha y condición de cada sesión. |
| `GET /estudiante/cursos/{cursoId}/concentrado` | Tabla propia por actividad, con nota y porcentaje obtenido. |

El concentrado devuelve `curso`, `actividades` y `estudiantes`. Cada celda contiene `nota` (o `null` si no se ha registrado) y `porcentaje_obtenido` como decimal de dos cifras. El resultado del curso suma los aportes truncados por actividad; la tabla no incluye observaciones ni el resultado total.
