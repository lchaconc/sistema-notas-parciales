# Especificación del frontend del sistema de notas parciales

## Propósito y fuentes

El frontend permite al único profesor gestionar cursos, evaluaciones, matrículas, calificaciones y asistencia, y a cada estudiante consultar sus propios resultados y cambiar su contraseña. Esta especificación desarrolla [general.md](general.md) con las restricciones de [AGENTS.md](../AGENTS.md). El documento [requerimientos-iniciales.docx](requerimientos-iniciales.docx) aporta el origen de los flujos; las decisiones posteriores registradas en los Markdown prevalecen cuando lo precisan. El contrato HTTP vigente es [API-DOCS/openapi.yaml](../API-DOCS/openapi.yaml), resumido en [backend.api.md](backend.api.md).

Este documento describe el comportamiento esperado del cliente. El usuario autorizó su implementación. La ejecución se organiza en [frontend.tasks.md](frontend.tasks.md) y se verifica mediante [frontend.criteria.md](frontend.criteria.md).

La eliminación individual de componentes está disponible en la API y en la interfaz del profesor. Su comportamiento se verifica con pruebas de componentes y de extremo a extremo.

## Plataforma y responsabilidades

- El cliente reside en `frontend/` dentro del monorepositorio y se ejecuta en Docker junto con el backend y MySQL. Usa JavaScript, Vite, React, Tailwind CSS y Motion; no usa TypeScript. Consume la API con `fetch` nativo.
- Se organizan las vistas del profesor y del estudiante, los componentes reutilizables, el acceso a la API y las funciones auxiliares según su responsabilidad. La lógica de llamadas HTTP se mantiene separada de los componentes de presentación. Solo se añaden dependencias necesarias, estables y compatibles con el stack acordado.
- El cliente no se conecta directamente a MySQL ni contiene credenciales de base de datos o la clave de firma JWT. Recibe los datos de la API. En el entorno Docker, Vite reenvía `/api` al backend.
- Las reglas de negocio y la autorización las aplica el backend. El frontend guía la entrada y muestra los rechazos, sin tratar sus validaciones locales como sustituto de las de la API.
- En la interfaz y la documentación funcional se usa exclusivamente el rol **profesor**. No hay un rol ni un módulo separado llamado «administrador».

## Integración con la API y estados comunes

- Todas las rutas HTTP de este documento llevan el prefijo `/api`. Las respuestas JSON correctas usan `{ "data": ... }` y los errores controlados usan `{ "error": "motivo" }`. El cliente utiliza los campos del contrato OpenAPI y no presupone propiedades adicionales.
- `POST /api/login` y `GET /api/healt` son las únicas rutas públicas. Las demás solicitudes incluyen `Authorization: Bearer <JWT>` y respetan el rol devuelto por el inicio de sesión. `/api/healt` comprueba servidor y MySQL; no define por sí solo una pantalla o flujo de usuario.
- Las peticiones con cuerpo envían un objeto JSON. El frontend trata las respuestas `201` como creación exitosa, `400` como dato inválido, `401` como credenciales o sesión inválidas, `403` como permiso insuficiente, `404` como recurso no disponible, `409` como conflicto de estado o duplicado y `500` como error interno. La comprobación de salud puede devolver `503` si falla MySQL. En todos los casos muestra el motivo disponible sin exponer secretos.
- Las vistas distinguen carga, datos vacíos, éxito y error. Tras una operación que modifica datos, las consultas afectadas se actualizan desde la API para mostrar el estado vigente y evitar resultados obsoletos.
- Los importes decimales recibidos de la API son cadenas con dos cifras y punto decimal; `null` en una nota significa **sin calificar**, distinto de `0,00`. El cliente presenta al usuario la nota y el porcentaje obtenido sin alterar las reglas de cálculo del backend. La API acepta entradas decimales con punto o coma y hasta dos cifras decimales.
- Los identificadores internos (`cursoId`, `matriculaId`, `actividadId`, `sesionId`, etc.) se usan para relacionar solicitudes. El código de curso puede repetirse: en la interfaz se identifica cada curso junto con su cuatrimestre y número de grupo.

## Inicio de sesión y acceso por rol

- Profesor y estudiante ingresan correo y contraseña en un mismo flujo de acceso. El frontend envía `correo` y `contrasena` a `POST /api/login`, recibe `data.token`, `data.expires_in` y `data.rol`, y presenta las funciones del rol autenticado.
- El correo se transmite sin cambiar su combinación de mayúsculas y minúsculas: la API considera diferentes `Persona@ucatolica.ac.cr` y `persona@ucatolica.ac.cr`.
- El JWT dura una hora. Si el usuario recarga la página antes de que venza, la sesión permanece activa hasta ese vencimiento. Al vencer o cuando la API rechaza una sesión vencida con `401`, el cliente deja de usar el token y cierra la sesión; las áreas protegidas dejan de mostrar datos de esa sesión. Un `403` se presenta como falta de permiso y no habilita una vista de otro rol.
- El frontend nunca muestra hashes, la clave de firma ni credenciales de configuración. La cuenta inicial del profesor se crea mediante el comando de CodeIgniter, fuera de la interfaz; no existe un formulario público para crear profesores.

## Área del profesor

### Cuatrimestres, cursos y grupos

| Necesidad de interfaz | Datos y operación de API |
| --- | --- |
| Consultar cuatrimestres y sus fechas | `GET /api/profesor/cuatrimestres`: `id`, `codigo`, `fecha_inicio`, `fecha_cierre`. |
| Crear cuatrimestre | `POST /api/profesor/cuatrimestres` con `codigo` en formato `N-AAAA` (`N` de 1 a 3), `fecha_inicio` y `fecha_cierre`; el inicio no puede ser posterior al cierre. |
| Consultar cursos y grupos | `GET /api/profesor/cursos`: nombre, código, número de grupo, cuatrimestre y fechas compartidas. |
| Crear curso | `POST /api/profesor/cursos` con `cuatrimestre_id`, `nombre`, `codigo` y `numero_grupo`. Se permiten códigos repetidos cuando cambia el cuatrimestre o el grupo. |
| Eliminar curso | `DELETE /api/profesor/cursos/{cursoId}` únicamente después del cierre del cuatrimestre. Antes de solicitarlo, la interfaz pide confirmación explícita al profesor. |

La confirmación de eliminación informa que desaparecerán los datos propios del curso, incluidas matrículas, componentes, calificaciones y asistencia. Los registros de estudiantes, cuatrimestre y catálogo permanecen. Si el backend rechaza la operación, la interfaz conserva el curso y muestra el motivo; solo actualiza la lista como eliminada tras una respuesta exitosa.

### Catálogo de actividades

- El profesor consulta, crea, edita y elimina opciones mediante `GET` y `POST /api/profesor/catalogo`, y `PUT` y `DELETE /api/profesor/catalogo/{id}`. El formulario de creación y edición envía `nombre`.
- La lista inicial contiene prueba parcial, proyecto, tarea, trabajo en clase, **indagación y exposición** como una sola opción, prueba corta y repositorio GitHub.
- Los nombres no pueden duplicarse aunque varíen mayúsculas o tildes. El backend decide la unicidad y devuelve el conflicto; el cliente muestra ese rechazo y no considera guardado el cambio.
- Una opción asociada a cualquier componente queda bloqueada para editarla o eliminarla. `GET /api/profesor/catalogo` entrega `en_uso` para cada actividad; si es `true`, la interfaz bloquea ambas acciones. Tras crear o eliminar un componente, o eliminar un curso, se vuelve a consultar el catálogo para actualizar ese estado. El backend también mantiene el rechazo de una operación que llegue cuando la actividad ya esté en uso. El catálogo permanece independiente al borrar un curso o componente.

### Componentes y actividades individuales

- Para un curso y grupo seleccionados, el profesor consulta sus componentes y actividades individuales con `GET /api/profesor/cursos/{cursoId}/componentes`. Para crear uno, elige una opción del catálogo y envía a `POST` de esa ruta `actividad_catalogo_id`, `titulo`, `cantidad_actividades`, `descripcion` cuando exista, `porcentaje_total` y `puntaje_por_actividad`.
- El formulario etiqueta el campo como **Puntaje por actividad**. Admite título de hasta 128 palabras, descripción de hasta 256 caracteres, entre 1 y 15 actividades individuales, porcentaje total con hasta dos decimales y puntaje por actividad de `1,00` a `100,00` con hasta dos decimales. La suma de porcentajes de componentes del curso no debe superar 100 %. Los errores de límite o conflicto se muestran sin perder el contexto del curso.
- El backend distribuye solo el porcentaje total en partes iguales, truncadas a dos decimales sin redistribuir residuos, y asigna íntegro el puntaje indicado a cada actividad. La interfaz muestra `porcentaje_asignado` y `puntaje_asignado` tal como llegan de la API; no recalcula esos valores. Ejemplo: `10,00 %` y `10,00` puntos por actividad en cinco actividades producen cinco filas de `2,00 %` y `10,00` puntos; en tres actividades producen `3,33 %` y `10,00` puntos por fila.
- Los componentes anteriores al cambio conservan sus puntajes asignados, notas y porcentajes, salvo la corrección puntual de **Tareas** documentada en [general.md](general.md). El valor `puntaje_por_actividad` mostrado para otro componente histórico puede ser inferior a `1,00`; el formulario de alta sigue exigiendo el mínimo nuevo y no recalcula datos históricos. La vista muestra los puntajes individuales vigentes devueltos por la API.
- **Eliminación individual:** cada componente de la pestaña **Componentes** tiene una acción **Eliminar**. La acción abre siempre un cuadro de confirmación propio de la interfaz, también si no hay notas; no se emplean `alert()` ni `confirm()` de JavaScript. El cuadro identifica el curso, grupo y componente y explica que desaparecerán sus actividades, notas y observaciones, mientras permanecen el curso, las matrículas, la asistencia, los demás componentes y la opción del catálogo.
- La respuesta de componentes incluye `tiene_calificaciones`. Cuando sea `true`, el cuadro destaca que el componente está en uso y que se perderán calificaciones registradas, incluida una nota `0,00`; ofrece **Cancelar** y **Eliminar componente** sin bloquear esta última acción. El profesor puede eliminarlo incluso si el cuatrimestre terminó. Cancelar cierra el cuadro sin enviar `DELETE`.
- Tras confirmar con advertencia, el cliente solicitará `DELETE /api/profesor/cursos/{cursoId}/componentes/{componenteId}?confirmar_con_notas=true`; si no había calificaciones, omitirá el parámetro. Si la API devuelve `409` porque aparecieron notas entre la consulta y el borrado, la interfaz no anunciará éxito: actualizará la información y solicitará otra confirmación con la advertencia antes de reenviar la solicitud. Ante otro rechazo o fallo de red conserva el componente y muestra el motivo.
- Tras una respuesta exitosa, se actualizan desde la API los componentes y el catálogo; las consultas de calificaciones, tarjetas, resultados y concentrados se refrescan cuando se visiten o estén abiertas. La opción del catálogo deja de aparecer `en_uso` solo cuando no queda asociada a otro componente. El cliente no calcula ni mantiene copias propias de notas o resultados eliminados.

### Estudiantes, matrícula y contraseñas

- `GET /api/profesor/estudiantes` permite consultar las cuentas estudiantiles registradas. Para un curso y grupo seleccionados, `GET /api/profesor/cursos/{cursoId}/matriculas` muestra sus matriculados.
- El formulario de matrícula pide `nombre`, `primer_apellido`, `segundo_apellido` y `correo`; `POST /api/profesor/cursos/{cursoId}/matriculas` crea la matrícula o reutiliza el estudiante del mismo correo exacto. El frontend no crea una segunda cuenta por su cuenta ni cambia las mayúsculas del correo. El backend rechaza un correo del profesor, un dominio no permitido o una matrícula duplicada.
- El cliente valida formato de correo, pero no fija en el frontend una lista cerrada de dominios: la API consulta `dominios_permitidos` y podría admitir otros en el futuro. Si la matrícula ocurre después de sesiones guardadas, la API incorpora al estudiante en esas sesiones con condición inicial **justificado**; la interfaz muestra ese estado cuando consulta nuevamente la asistencia.
- Para **Generar contraseña**, el profesor selecciona al estudiante y solicita `POST /api/profesor/estudiantes/{estudianteId}/contrasena`. La API reemplaza la contraseña anterior y entrega `data.contrasena` una sola vez. La interfaz muestra esa contraseña al profesor para que la comunique por correo; la aplicación no envía el correo automáticamente. Nunca presenta ni guarda el hash.

### Calificaciones y observaciones

- El profesor selecciona curso, grupo, estudiante matriculado y actividad individual. Las matrículas, componentes y actividades se obtienen de las consultas anteriores. La operación `PUT /api/profesor/calificaciones/{matriculaId}/{actividadId}` envía `puntos_obtenidos` y, si existe, `observacion`.
- La nota son puntos obtenidos por esa actividad, con hasta dos decimales, entre cero y su `puntaje_asignado` inclusive. La observación corresponde a esa actividad individual y estudiante y admite hasta 125 caracteres. La interfaz distingue nota sin registrar de nota explícita `0,00`.
- El frontend presenta los porcentajes calculados por la API, no recalcula el resultado del curso como regla de negocio. Una nota de `8,50` sobre 10 puntos con peso de 2 % aporta `1,70 %`; una actividad sin nota aporta cero al resultado.
- Al abrir o volver a abrir la calificación de una actividad, el profesor consulta `GET /api/profesor/calificaciones/{matriculaId}/{actividadId}`. La respuesta entrega los puntos y la observación ya registrados; ambos son `null` si aún no existe nota. El formulario muestra la observación previa y permite modificarla con el `PUT` correspondiente. El concentrado sigue mostrando únicamente nota y porcentaje, sin observaciones.

### Asistencia

- `GET /api/profesor/cursos/{cursoId}/sesiones` permite revisar las sesiones y sus condiciones. La interfaz combina sus `matricula_id` con `GET /api/profesor/cursos/{cursoId}/matriculas` para identificar a cada estudiante por nombre.
- **Crear asistencia** solicita fecha y presenta a todos los matriculados del curso. Cada uno debe recibir exactamente una condición: `presente`, `ausente` o `justificado`. El cliente envía `POST /api/profesor/cursos/{cursoId}/sesiones` con `fecha` y `asistencias: [{matricula_id, condicion}, ...]`.
- Cada curso admite hasta quince sesiones, solo una por fecha, dentro del intervalo inclusivo del cuatrimestre. La interfaz muestra las fechas ya registradas y los rechazos del backend por duplicado, límite o fecha fuera de rango.
- Una sesión guardada puede cargarse para corregir condiciones mediante `PUT /api/profesor/sesiones/{sesionId}/asistencias`. El profesor también puede modificar las condiciones `justificado` creadas por matrículas tardías. Tras guardar, la interfaz vuelve a consultar la sesión para reflejar el estado vigente.

### Concentrado por curso y grupo

- `GET /api/profesor/cursos/{cursoId}/concentrado` genera una tabla en pantalla, similar a una hoja de cálculo, para **un** curso y grupo por vez. Las filas corresponden a estudiantes matriculados y las columnas a actividades individuales. Cada celda muestra `nota` y `porcentaje_obtenido` de ese estudiante en esa actividad.
- Una `nota: null` se presenta como **sin calificar**, mientras que el porcentaje obtenido continúa siendo `0,00 %`. El concentrado no muestra puntaje máximo, observaciones ni resultado total del curso.
- El profesor puede descargar los mismos datos vigentes con `GET /api/profesor/cursos/{cursoId}/concentrado.xlsx`. La solicitud lleva JWT y recibe un archivo `.xlsx` binario; la interfaz lo ofrece como descarga. Esta función no está disponible para estudiantes.

## Área del estudiante

- `GET /api/estudiante/cursos` devuelve solo los cursos del estudiante autenticado. Si cursa varios, el cliente agrupa las consultas y vistas por curso, identificado por su cuatrimestre, código y número de grupo.
- En cada curso, `GET /api/estudiante/cursos/{cursoId}/calificaciones` entrega `resultado` y `tarjetas`. Una tarjeta corresponde a una actividad individual y presenta su título, número, nota obtenida, porcentaje obtenido y observación. Muestra el **porcentaje que obtuvo** el estudiante: en el ejemplo confirmado, `1,70 %`, no el peso completo `2,00 %` de la actividad. La nota nula se identifica como pendiente de calificar.
- `GET /api/estudiante/cursos/{cursoId}/asistencia` muestra fecha y condición (`presente`, `ausente` o `justificado`) de cada sesión de ese curso.
- `GET /api/estudiante/cursos/{cursoId}/concentrado` presenta una tabla por curso con las propias notas y porcentajes obtenidos por actividad individual. Usa únicamente el registro del estudiante entregado por la API. No incluye observaciones, puntaje máximo ni total del curso en esa tabla y no ofrece descarga `.xlsx`.
- En **Perfil > Cambiar contraseña**, el estudiante introduce la nueva contraseña y el frontend envía `PUT /api/estudiante/perfil/contrasena` con `contrasena_nueva`. La API guarda el hash; el cliente no recibe ni muestra el hash.
- El frontend no solicita datos de otro estudiante y no ofrece funciones de gestión del profesor al rol estudiante. La API verifica además que cada curso consultado pertenezca a su matrícula.

## Validaciones, errores y datos sensibles

| Caso | Comportamiento esperado del cliente |
| --- | --- |
| Campos obligatorios, formato o rango inválido | Indicar el campo y permitir corregirlo antes de enviar, además de mostrar el motivo devuelto por la API si esta rechaza la solicitud. |
| Duplicado o actividad bloqueada (`409`) | Mantener los datos de trabajo, mostrar el motivo y actualizar la vista cuando corresponda. |
| JWT ausente, inválido o vencido (`401`) | Cerrar la sesión protegida; el inicio de sesión con credenciales incorrectas muestra el rechazo sin abrir una sesión. |
| Rol incorrecto (`403`) | Impedir el acceso a la función solicitada y mostrar el rechazo, sin exponer datos de otro rol. |
| Recurso inexistente (`404`) | Indicar que el dato solicitado ya no está disponible y permitir volver al contexto del curso o a la lista. |
| Fallo del servidor o de la red | Mostrar el fallo sin anunciar éxito ni descartar silenciosamente los datos aún no guardados. |

La contraseña generada por el profesor es información sensible: solo se presenta en la respuesta de esa operación, para que el profesor la entregue por correo. Las credenciales y el JWT no se incluyen en mensajes de error, registros visibles de la interfaz ni archivos descargados que no los requieran.

## Pruebas previstas para la implementación

- Pruebas unitarias de funciones auxiliares con lógica propia: formato y límites de entradas, representación de cadenas decimales y distinción entre `null` y `0,00`.
- Pruebas de componentes y flujos: acceso según rol, formularios del profesor, validaciones y rechazos, confirmación de eliminación, carga y edición de asistencia, presentación de tarjetas, tablas y estados vacíos o de error.
- Pruebas de integración con la API: JWT, conservación de sesión al recargar antes de una hora y cierre al vencer; datos de cursos y grupos sin mezclarse; reutilización de estudiantes; catálogo con `en_uso`; porcentaje recibido frente al mostrado; carga y edición de observaciones previas por actividad; concentración actualizada tras una nota; descarga `.xlsx` solo para profesor y consultas del estudiante limitadas a sus cursos.
- Pruebas de extremo a extremo de los recorridos principales de profesor y estudiante dentro de Docker, con datos de prueba separados de la base de desarrollo. La suite debe ser repetible y ejecutarse antes de dar por implementado el frontend.
- Pruebas de conformidad con el contrato HTTP vigente: verificar los campos consumidos, cuerpos enviados, códigos y errores relevantes frente a OpenAPI y a la API real, incluida la respuesta binaria del `.xlsx`.
- Comprobaciones de accesibilidad y adaptación visual en computadora y celular: navegación por teclado, foco, etiquetas, mensajes, contraste, menú móvil, pestañas deslizables y consulta completa de las tablas mediante desplazamiento horizontal.

## Diseño visual y navegación

- La cabecera y la pantalla de acceso muestran el nombre **Sistema de Notas Parciales**.
- La dirección visual acordada es **institucional sobria**. Mientras no se aporten logotipo ni colores oficiales, se usa una identidad provisional: azul profundo `#17324D` para navegación y títulos, verde azulado `#087E83` para acciones primarias, fondo claro `#F5F7FA`, superficies blancas `#FFFFFF` y texto oscuro `#1C2933`. Los estados de error y éxito tendrán colores y texto identificables; el color no será su única señal. Esta paleta no se presenta como identidad oficial de la universidad.
- La interfaz ofrece un único botón de cambio entre **tema claro y oscuro**, identificado con iconos de sol y luna. Está disponible en la pantalla de acceso y en las áreas del profesor y del estudiante, tanto en computadora como en celular. El tema claro conserva el diseño actual; el oscuro adapta fondos, superficies, texto, formularios, tablas, tarjetas, mensajes y estados de foco sin cambiar la estructura ni las funciones. La elección se guarda localmente en el navegador para conservarse al recargar y al cerrar sesión; si no existe una elección previa, se usa el tema claro. El control puede operarse con teclado y anuncia de forma textual el tema al que cambiará.
- El área del profesor utiliza un **menú lateral fijo** en computadora, con **Cursos, Cuatrimestres, Catálogo de actividades y Estudiantes**, además de la acción **Cerrar sesión**. Las notas, la asistencia y el concentrado se abren desde un curso; no tienen accesos globales en el menú.
- Al seleccionar un curso y grupo, el profesor cambia entre **Componentes, Matrícula, Calificaciones, Asistencia y Concentrado** mediante pestañas dentro de ese curso. El curso, cuatrimestre y número de grupo permanecen visibles como contexto.
- Tras iniciar sesión, el profesor llega a la **lista de cursos y grupos**. En celular, un botón abre el menú como panel lateral que puede cerrarse para recuperar el espacio de contenido.
- El menú del estudiante contiene **Mis cursos, Perfil y Cerrar sesión**. Tras iniciar sesión, entra a la lista de sus cursos. Al abrir uno, navega mediante pestañas entre **Calificaciones, Asistencia y Concentrado**. El cambio de contraseña se encuentra en **Perfil**.
- Para registrar una nota, el profesor entra en **Calificaciones**, elige primero al estudiante matriculado y luego la actividad individual. El formulario carga la nota y observación actuales antes de editarlas.
- La aplicación debe poder utilizarse cómodamente en **computadora y celular**. En celular, las pestañas de cada curso ocupan una fila deslizable horizontalmente. El concentrado mantiene su estructura de tabla y permite desplazamiento horizontal para consultar todas las actividades; no se ocultan datos por el tamaño de la pantalla.
- Se usa una **densidad visual equilibrada**: separación suficiente entre controles y filas legibles sin convertir cada dato de tabla en una tarjeta grande. Los encabezados, etiquetas y estados de foco deben seguir siendo claros en ambas pantallas.
