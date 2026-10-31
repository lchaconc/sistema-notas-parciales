# Contrato HTTP del backend

La versión ejecutable de este contrato en OpenAPI 3.0.4 está en [API-DOCS/openapi.yaml](../API-DOCS/openapi.yaml) y se visualiza con Swagger UI en Docker.

**Estado del contrato:** la tabla de rutas siguiente describe la API vigente y coincide con OpenAPI y `Routes.php`. La eliminación individual de componentes, los metadatos de las tarjetas del estudiante y el calendario automático están implementados.

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
| `GET /profesor/cursos/{cursoId}/componentes` | Componentes, actividades individuales e indicador `tiene_calificaciones`. |
| `POST /profesor/cursos/{cursoId}/componentes` | `{actividad_catalogo_id, titulo, cantidad_actividades, descripcion?, porcentaje_total, puntaje_por_actividad}`. El porcentaje se reparte; el puntaje de `1,00` a `100,00` se asigna íntegro a cada actividad. |
| `DELETE /profesor/cursos/{cursoId}/componentes/{componenteId}` | Elimina el componente y sus datos dependientes; exige `?confirmar_con_notas=true` si tiene notas. |
| `GET /profesor/calificaciones/{matriculaId}/{actividadId}` | Nota y observación vigentes de esa actividad y matrícula; ambos `null` si aún no hay calificación. Rechaza asociaciones entre cursos distintos. |
| `GET /profesor/estudiantes` | Lista de estudiantes. |
| `GET /profesor/cursos/{cursoId}/matriculas` | Matrículas del curso. |
| `POST /profesor/cursos/{cursoId}/matriculas` | `{nombre, primer_apellido, segundo_apellido, correo}`. |
| `PUT /profesor/calificaciones/{matriculaId}/{actividadId}` | `{puntos_obtenidos, observacion?}`. Los puntos se ingresan sobre el puntaje de esa actividad. |
| `GET /profesor/cursos/{cursoId}/sesiones` | Quince sesiones programadas con número, fecha, estado y condiciones guardadas. |
| `PUT /profesor/sesiones/{sesionId}/asistencias` | `{asistencias: [{matricula_id, condicion}, ...]}`; registra por primera vez o corrige el conjunto completo de condiciones. |
| `GET /profesor/cursos/{cursoId}/concentrado` | Tabla vigente de estudiantes por actividad. |
| `GET /profesor/cursos/{cursoId}/concentrado.xlsx` | Descarga la misma tabla en `.xlsx`. |

## Consultas del estudiante

El identificador de estudiante procede exclusivamente del JWT. Cada `{cursoId}` se verifica contra sus matrículas.

| Método y ruta | Salida principal |
| --- | --- |
| `GET /estudiante/cursos` | Cursos propios. |
| `GET /estudiante/cursos/{cursoId}/calificaciones` | Tarjetas con título del componente, `numero`, `actividad_catalogo`, `cantidad_actividades`, nota, porcentaje obtenido y observación; incluye el resultado del curso. |
| `GET /estudiante/cursos/{cursoId}/asistencia` | Fecha y condición de cada sesión. |
| `GET /estudiante/cursos/{cursoId}/concentrado` | Tabla propia por actividad, con nota y porcentaje obtenido. |

El concentrado devuelve `curso`, `actividades` y `estudiantes`. Cada celda contiene `nota` (o `null` si no se ha registrado) y `porcentaje_obtenido` como decimal de dos cifras. El resultado del curso suma los aportes truncados por actividad; la tabla no incluye observaciones ni el resultado total.

Cada elemento de `tarjetas` en la consulta de calificaciones del estudiante incluye `id`, `titulo`, `numero`, `actividad_catalogo`, `cantidad_actividades`, `nota`, `porcentaje_obtenido` y `observacion`. `actividad_catalogo` es el nombre almacenado en el catálogo; `cantidad_actividades` y `numero` corresponden al componente y a la actividad individual de esa tarjeta. El frontend usa solo el nombre del catálogo si la cantidad es 1, o el nombre seguido de un espacio y el número si es mayor. El título del componente se muestra aparte. La consulta de concentrado conserva su esquema previo.

La propuesta de tabla editable en **Calificaciones** usa `matricula_id` de cada estudiante, `id` de cada actividad y `nota` del concentrado para presentar sus celdas. El puntaje máximo procede de la consulta de componentes del curso. Al abrir el editor de observación de una celda se conserva `GET /profesor/calificaciones/{matriculaId}/{actividadId}`; su disquete utiliza `PUT` de esa misma ruta con nota y observación válidas de **esa celda**. No se guarda una observación sin nota. Para mostrar el porcentaje obtenido actualizado tras guardar, el cliente vuelve a consultar el concentrado. Por sí mismo, ese rediseño de la tabla no añade operaciones HTTP, no cambia las respuestas existentes ni convierte la descarga `.xlsx` en un archivo editable de importación.

## Eliminar un componente

- `GET /profesor/cursos/{cursoId}/componentes` incluye `tiene_calificaciones: boolean` en cada componente. Es `true` si alguna actividad del componente tiene una calificación con puntos registrados, incluida `0,00`; se calcula de los datos vigentes. Los demás campos permanecen.
- `DELETE /profesor/cursos/{cursoId}/componentes/{componenteId}` requiere JWT de profesor y comprueba que el componente pertenece al curso indicado. Se puede usar en cualquier momento del cuatrimestre. Con éxito devuelve `200` y `{ "data": { "eliminado": true } }`. Si no existe el curso o el componente en ese curso, devuelve `404`; mantiene `401`, `403` y `500` según el contrato general. Un valor de `confirmar_con_notas` distinto de `true` o `false` devuelve `400`.
- La solicitud omitirá normalmente `confirmar_con_notas`. Si hay calificaciones en el momento de borrar y no se envió `?confirmar_con_notas=true`, devolverá `409` con `{ "error": "El componente tiene calificaciones; confirme su eliminación." }` y no modificará datos. Tras mostrar la advertencia y recibir confirmación explícita, el frontend repetirá el `DELETE` con ese parámetro. El backend vuelve a comprobar las asociaciones y borra atómicamente el componente, sus actividades, calificaciones y observaciones. El parámetro solo certifica que el profesor vio la advertencia; no limita la eliminación a los datos que se mostraron al abrir el cuadro.
- La interfaz pedirá confirmación incluso cuando `tiene_calificaciones` sea `false`. Si aparece una calificación entre la consulta y el primer `DELETE`, el `409` exige refrescar los datos y mostrar una nueva advertencia antes de repetir la operación. Después del éxito, las consultas vigentes de componentes, catálogo, notas, resultados y concentrados reflejarán la eliminación; el curso, matrículas, asistencia y registros independientes permanecerán.

## Calendario automático y asistencia

- `POST /api/profesor/cuatrimestres` conserva `{codigo, fecha_inicio, fecha_cierre}`. Rechaza con `400` una diferencia distinta de 98 días; en éxito crea atómicamente las quince fechas semanales y responde como creación vigente. No se añade una ruta para crear sesiones del cuatrimestre manualmente.
- `POST /api/profesor/cursos` conserva su cuerpo y respuesta; en éxito crea además las quince sesiones pendientes del curso, una por fecha programada. No crea condiciones de asistencia.
- `GET /api/profesor/cursos/{cursoId}/sesiones` devuelve exactamente quince elementos ordenados por `numero` ascendente. Cada elemento contiene `id`, `curso_id`, `numero` (1–15), `fecha` (`AAAA-MM-DD`), `asistencia_registrada` booleano y `asistencias` como arreglo de `{matricula_id, estudiante_id, condicion}`. En una sesión pendiente, el indicador es `false` y el arreglo está vacío; en una registrada, el indicador es `true` y el arreglo incluye una condición válida por cada matrícula vigente. La fecha y el número proceden de `sesiones_cuatrimestre`.
- `PUT /api/profesor/sesiones/{sesionId}/asistencias` conserva el cuerpo `{asistencias: [{matricula_id, condicion}, ...]}` y el `200` actual. Sirve tanto para la primera captura como para correcciones. Requiere exactamente una condición por matrícula vigente del curso de esa sesión; rechaza faltantes, duplicados, matrículas ajenas o condiciones inválidas con `400`, sin guardar cambios parciales. Si la sesión no existe, devuelve `404`. En éxito devuelve la sesión con `id`, `numero`, `fecha`, `asistencia_registrada: true` y el conjunto `asistencias` guardado. No se registra una sesión de un curso sin matrículas.
- `POST /api/profesor/cursos/{cursoId}/sesiones` fue retirado de rutas y OpenAPI. No hay alta manual ni modificación de fecha. `GET /api/estudiante/cursos/{cursoId}/asistencia` conserva su forma de respuesta y solo incluye las sesiones con condición guardada para la matrícula autenticada; las pendientes no figuran.
- La matrícula tardía conserva su cuerpo y respuesta; crea `justificado` solo en sesiones con `asistencia_registrada: true`. La migración aplicada a `3-2026` conservó sus fechas, la sesión original y sus diez condiciones.
