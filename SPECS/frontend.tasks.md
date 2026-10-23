# Plan de tareas para implementar el frontend

## Alcance y fuentes

Este plan descompone [frontend.specs.md](frontend.specs.md) en trabajo verificable. Rigen también [general.md](general.md), [AGENTS.md](../AGENTS.md) y el contrato vigente de [API-DOCS/openapi.yaml](../API-DOCS/openapi.yaml). La plantilla base del frontend y el entorno Docker ya existen. El usuario autorizó la implementación del cliente.

Cada tarea funcional incluye la comprobación de sus estados de carga, vacío, éxito y error cuando correspondan. Las pruebas se preparan y ejecutan durante la implementación de cada bloque, con datos separados de la base de desarrollo. No se añaden bibliotecas por anticipado: antes de incorporar una herramienta de prueba u otra dependencia se verifica si es necesaria, estable, mantenida y compatible con el stack acordado.

## Secuencia de trabajo

### 1. Verificar la base técnica y el contrato antes de desarrollar

- Revisar la plantilla existente en `frontend/`, la configuración de Vite y Docker, y el proxy `/api`. Levantar frontend, backend y MySQL dentro de Docker y comprobar la comunicación mediante `GET /api/healt`.
- Cotejar los datos y errores de cada operación que usará el cliente con OpenAPI y con las respuestas reales del backend, en particular la lectura de observaciones, `en_uso` y los concentrados. Registrar y resolver cualquier discrepancia documental o técnica antes de depender de ella en una vista.
- Preparar una matriz de casos por flujo y tipo de prueba (unitaria, componente, integración y extremo a extremo), junto con datos de prueba repetibles y separados de desarrollo. Decidir las herramientas de prueba solo cuando se autorice implementarlas y después de verificar su necesidad y compatibilidad.
- Identificar la configuración pública necesaria para el cliente sin incorporar credenciales de MySQL, clave JWT ni otros secretos. Mantener los archivos locales de entorno excluidos de Git.
- **Comprobación:** los contenedores arrancan, el cliente alcanza `/api` desde Docker y existe una correspondencia verificable entre cada flujo de `frontend.specs.md` y su operación HTTP.

### 2. Organizar la estructura y el sistema visual

- Separar páginas por rol, componentes comunes, funciones de acceso a la API y utilidades con lógica reutilizable. Mantener las llamadas `fetch` fuera de los componentes de presentación.
- Establecer estilos reutilizables con Tailwind CSS para la dirección institucional sobria: nombre «Sistema de Notas Parciales», paleta provisional, tipografía legible, espaciado equilibrado, formularios, botones, tablas, tarjetas y estados de foco, carga, vacío y error. No presentar la paleta provisional como identidad oficial.
- Preparar la estructura adaptable a computadora y celular: menú lateral fijo del profesor en escritorio, panel lateral abierto por botón en celular, y pestañas de curso deslizables en una sola fila en celular. Mantener visible el contexto de curso, cuatrimestre y grupo.
- Añadir un botón compartido de sol y luna en acceso y en ambas áreas autenticadas para alternar temas claro y oscuro. Conservar el aspecto claro actual, adaptar todos los componentes y estados al modo oscuro y guardar la elección localmente para recuperarla tras recargar o cerrar sesión. Mantener el control visible y operable con teclado en computadora y celular, sin cambiar navegación ni contratos de API.
- **Pruebas de componentes y revisión visual:** navegación por teclado, foco visible, etiquetas y mensajes comprensibles, contraste de textos y controles, y uso sin pérdida de funciones en tamaños de computadora y celular.
- **Pruebas del tema:** comprobar el cambio en ambos sentidos, valor claro inicial, persistencia al recargar y cerrar sesión, nombre accesible del botón y revisión visual de acceso, formularios, tablas, tarjetas, mensajes y foco en ambos temas y tamaños.

### 3. Implementar acceso a la API, sesión y navegación por rol

- Crear el acceso centralizado a la API con `fetch`: envío de JSON, lectura de `{data}` y `{error}`, token Bearer para rutas protegidas, manejo de `201`, `400`, `401`, `403`, `404`, `409`, `500` y fallo de red; tratar la descarga binaria `.xlsx` por separado.
- Aislar en utilidades las transformaciones que tengan lógica propia, como presentación de decimales recibidos, distinción entre nota `null` y cero, interpretación del vencimiento y asociación de respuestas con el curso seleccionado. Mantener los cálculos de calificación en el backend.
- Crear el inicio de sesión único con correo y contraseña. Conservar la grafía exacta del correo, guardar la sesión para que sobreviva a una recarga hasta el vencimiento del JWT de una hora y retirarla al vencer o ante un `401` de sesión. Un rechazo de credenciales en `/api/login` no debe abrir una sesión.
- Organizar las rutas y accesos del profesor y del estudiante según `data.rol`, impedir la presentación de páginas de otro rol y ofrecer **Cerrar sesión**. No crear pantalla pública de alta de profesor ni una función de usuario «administrador».
- **Pruebas unitarias, de componentes e integración:** transformaciones de datos con lógica propia, nota `null` frente a cero, interpretación de respuestas y errores, JWT agregado solo donde corresponde, inicio de sesión válido e inválido, recarga antes del vencimiento, cierre al vencer, `401`, `403`, cierre manual y protección de vistas de ambos roles. Contrastar cuerpos y campos consumidos con OpenAPI y la API real.

### 4. Construir la navegación y la gestión básica del profesor

- Mostrar **Cursos** como primera vista tras iniciar sesión. Construir el menú lateral con **Cursos, Cuatrimestres, Catálogo de actividades, Estudiantes y Cerrar sesión**; dentro de cada curso y grupo, las pestañas **Componentes, Matrícula, Calificaciones, Asistencia y Concentrado**.
- Consultar y crear cuatrimestres con código y fechas; consultar y crear cursos con cuatrimestre, nombre, código y número de grupo. Identificar siempre un curso mediante cuatrimestre, código y grupo, ya que el código puede repetirse.
- Permitir eliminar un curso solo después de solicitar confirmación explícita que describa sus datos dependientes. Mantenerlo visible si la API rechaza la eliminación y actualizar la lista tras una respuesta correcta.
- **Pruebas de flujo e integración:** vista inicial y navegación, fechas y campos inválidos, cursos con igual código en grupos distintos, aislamiento del contexto seleccionado, confirmación y cancelación de borrado, rechazo del backend y actualización posterior.

### 5. Implementar catálogo y componentes de evaluación

- Construir el CRUD del catálogo. Usar `en_uso` para bloquear edición y eliminación de una actividad asociada a componentes, y actualizar ese estado después de operaciones que puedan cambiarlo. Mostrar los conflictos por nombres duplicados o cambios concurrentes sin anunciar éxito.
- Dentro de un curso, consultar componentes y sus actividades individuales. Crear componentes con opción del catálogo, título, cantidad, descripción opcional, porcentaje total y **puntaje por actividad** de `1,00` a `100,00` mediante el campo `puntaje_por_actividad` del contrato actualizado.
- Mostrar el porcentaje repartido y el puntaje íntegro de cada actividad exactamente como los devuelve la API, sin recalcularlos en el cliente. Mostrar los componentes históricos con su puntaje individual conservado, incluso cuando sea inferior al nuevo mínimo de alta.
- **Pruebas de componentes e integración:** catálogo inicial y opción «indagación y exposición» única, actividad `en_uso`, rechazo de duplicados y límites, suma de porcentajes inválida, cinco actividades de `10,00` puntos cada una con `2,00 %` de un componente de `10,00 %`, reparto porcentual no exacto sin dividir puntos, componente histórico intacto y actualización de la vista tras crear o borrar un curso.

### 6. Implementar estudiantes, matrícula y generación de contraseña

- Consultar estudiantes y matrículas del curso; matricular con nombre, dos apellidos y correo, conservando mayúsculas y minúsculas. Actualizar la lista con la matrícula confirmada por la API y mostrar los rechazos por dominio, correo del profesor o matrícula duplicada.
- Permitir al profesor generar una contraseña para un estudiante; mostrarla solo al recibir la respuesta de esa operación y explicar en esa vista que el profesor la comunica por correo. No almacenar ni registrar la contraseña en el cliente ni automatizar el envío.
- Actualizar la matrícula y, si ya existían sesiones, la asistencia del curso para mostrar los registros iniciales `justificado` del estudiante incorporado tarde.
- **Pruebas de componentes e integración:** reutilización de un estudiante en varios cursos, correo exacto, duplicado, dominio rechazado, contraseña mostrada una sola vez en el flujo y asistencia previa tras matrícula tardía.

### 7. Implementar captura de calificaciones y observaciones

- En la pestaña **Calificaciones**, seleccionar primero el estudiante matriculado y luego una actividad individual del curso. Consultar su nota y observación previas al abrir el formulario; distinguir `null` de una nota `0,00`.
- Enviar puntos obtenidos y observación con los límites especificados. Mostrar los porcentajes y el resultado devueltos por la API sin reproducir sus reglas de cálculo en el cliente. Después de guardar, volver a consultar los datos afectados.
- **Pruebas de componentes e integración:** nota nueva, nota cero, observación preexistente editable, puntos negativos o superiores al máximo, longitud de observación, cambio de estudiante o actividad sin mezclar datos y aporte mostrado de `1,70 %` para `8,50` sobre 10 puntos con peso de 2 %.

### 8. Implementar sesiones y asistencia

- Listar las sesiones de un curso y mostrar los estudiantes de cada una. Crear una sesión con fecha y una condición obligatoria para cada matrícula: `presente`, `ausente` o `justificado`.
- Cargar una sesión existente y permitir corregir sus condiciones, incluidas las asistencias de sesiones anteriores creadas como `justificado` por matrícula tardía. Volver a consultar la sesión tras guardar.
- **Pruebas de formularios e integración:** falta de condición, fecha fuera del cuatrimestre, fecha duplicada, límite de quince sesiones, edición de una sesión pasada, matrícula tardía y rechazo de datos que ya cambiaron en el servidor.

### 9. Implementar el concentrado del profesor

- Mostrar la tabla de un curso y grupo por vez: estudiantes en filas y actividades individuales en columnas, con nota y porcentaje obtenido por celda. Indicar «sin calificar» cuando `nota` sea `null` y mantener visible el porcentaje `0,00 %` recibido.
- Permitir descargar con JWT el `.xlsx` del curso y grupo actual. La tabla en pantalla y el archivo deben corresponder a datos vigentes; actualizar la consulta cuando cambie una calificación o matrícula.
- En celular, mantener todas las columnas consultables mediante desplazamiento horizontal, sin sustituir la tabla por tarjetas ni ocultar datos.
- **Pruebas de componentes, integración y descarga:** separación entre grupos con el mismo código, datos actualizados, distinción entre `null` y cero, respuesta binaria y fallo de descarga; comprobar que el archivo corresponde al curso seleccionado.

### 10. Implementar el área del estudiante

- Mostrar **Mis cursos** como entrada y el menú **Mis cursos, Perfil y Cerrar sesión**. Abrir cada curso con pestañas **Calificaciones, Asistencia y Concentrado**, sin mostrar funciones del profesor.
- Presentar las tarjetas por actividad con nota, porcentaje efectivamente obtenido y observación, además del resultado del curso entregado por la API. Mostrar la asistencia por fecha y condición. Presentar el concentrado propio en tabla por curso con nota y porcentaje por actividad, sin descarga `.xlsx`.
- En **Perfil > Cambiar contraseña**, enviar la nueva contraseña al endpoint del estudiante y mostrar el resultado de la operación sin exponer hashes.
- **Pruebas de componentes e integración:** varios cursos sin mezcla de datos, tarjeta con `1,70 %` obtenido, actividad sin nota, tabla propia, ausencia de descarga y de accesos del profesor, cambio de contraseña y rechazo de un curso ajeno.

### 11. Verificar recorridos completos, accesibilidad y entrega

- Ejecutar pruebas de extremo a extremo dentro de Docker para el recorrido principal del profesor: iniciar sesión, crear cuatrimestre y curso, crear componente, matricular, registrar nota y asistencia, consultar y descargar concentrado. Cubrir también el recorrido del estudiante: iniciar sesión, consultar cursos, tarjetas, asistencia y tabla, cambiar contraseña y cerrar sesión.
- Repetir los recorridos críticos en computadora y celular; comprobar menú, pestañas, formularios, tarjetas y tablas con teclado y mensajes legibles. Verificar carga, vacío, errores de red y API, recarga de sesión y vencimiento del JWT.
- Documentar los comandos para ejecutar las pruebas del frontend dentro de Docker y el resultado de las verificaciones. Revisar que no se versionen secretos y que la implementación siga las especificaciones y OpenAPI vigentes.
- **Cierre:** ejecutar la suite unitaria, de componentes, de integración y de extremo a extremo sobre datos de prueba separados. Una funcionalidad se considera terminada solo si sus casos normales, límites y rechazos relevantes pasan y la interfaz no mezcla datos de cursos o usuarios.

## Dependencias entre tareas

Las tareas 1 a 3 preparan el entorno, la presentación y el acceso compartidos. La tarea 4 establece el contexto de curso para las tareas 5 a 9. La matrícula de la tarea 6 precede a la captura de notas y asistencia; el concentrado depende de esos datos. La tarea 10 reutiliza sesión, navegación y acceso a la API, pero consulta exclusivamente endpoints del estudiante. Las pruebas de la tarea 11 cierran los recorridos construidos y no sustituyen las pruebas ejecutadas durante cada tarea.
