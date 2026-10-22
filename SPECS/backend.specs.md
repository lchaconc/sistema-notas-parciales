# Especificación detallada del backend del sistema de notas parciales

## Propósito y fuente

El backend ofrece una API para gestionar cursos, estudiantes, evaluaciones, calificaciones y asistencia, y para consultar los resultados según el rol autenticado. Esta especificación desarrolla las reglas confirmadas en [general.md](general.md). Cuando una decisión no esté definida allí, se identifica al final de este documento y no se considera aprobada.

**Alcance de este documento:** comportamiento y responsabilidades del backend. No define rutas HTTP concretas, nombres de tablas, estructura exacta de respuestas, bibliotecas JWT o diseño físico de la base de datos.

## Plataforma y organización

- El backend reside en una carpeta propia dentro del monorepositorio, utiliza PHP y CodeIgniter, se conecta a MySQL y se ejecuta en contenedores Docker.
- La conexión del backend a MySQL usa el nombre interno `db` y el puerto `3306`. Para inspeccionar la base de desarrollo con DBeaver en Windows, Docker publica ese mismo MySQL solo en `127.0.0.1` con puerto configurable `MYSQL_HOST_PORT` (predeterminado `3307`); la base de pruebas no se publica al anfitrión.
- Su interfaz para el aplicativo es una API. Las vistas para profesor y estudiante corresponden al frontend.
- La implementación debe separar rutas, controladores, servicios, modelos, filtros o middleware y funciones auxiliares según su responsabilidad. Las rutas exponen las operaciones; los controladores atienden las solicitudes y respuestas; los servicios aplican reglas de negocio; los modelos gestionan datos; los filtros verifican acceso.
- Las credenciales de MySQL, la clave de firma JWT y demás secretos se proporcionan mediante variables de entorno. Los archivos de entorno locales se mantienen fuera de Git. No se incluyen secretos en respuestas destinadas al frontend.
- Solo se incorporan dependencias necesarias, estables, mantenidas y compatibles con el stack acordado.

## Conceptos de datos

La siguiente tabla define relaciones funcionales. No prescribe nombres de tablas ni columnas.

| Concepto | Datos y relaciones necesarios |
| --- | --- |
| Profesor | Existe una sola cuenta con correo y hash de contraseña. Su correo no puede coincidir con el de ningún estudiante. |
| Estudiante | Nombre, dos apellidos, correo único en toda la aplicación y hash de contraseña cuando se haya generado. Un estudiante puede tener varias matrículas. |
| Dominio permitido | La tabla `dominios_permitidos` contiene los dominios de correo aceptados. Inicialmente contiene `ucatolica.ac.cr`; no tiene módulo CRUD en el aplicativo. |
| Cuatrimestre | Identificador en formato `N-AAAA`, con `N` entre 1 y 3, fecha de inicio y fecha de cierre compartidas por sus cursos. |
| Curso o grupo | Nombre, código, número de grupo y cuatrimestre. El mismo código puede repetirse en otros cuatrimestres o grupos; la combinación de cuatrimestre, código y número de grupo distingue un curso. |
| Actividad del catálogo | Opción reutilizable que el profesor puede crear, consultar, editar o eliminar mientras no esté asociada a un componente. |
| Componente de evaluación | Pertenece a un curso y refiere a una actividad del catálogo. Contiene título, descripción, cantidad de actividades individuales, porcentaje y puntaje total. |
| Actividad individual | Pertenece a un componente y recibe una parte igual del porcentaje y del puntaje, truncada a dos decimales cuando sea necesario. |
| Matrícula | Relaciona un estudiante con un curso; permite registrar sus calificaciones y asistencia en ese curso. |
| Calificación | Registra los puntos obtenidos y una observación para una actividad individual y un estudiante matriculado. |
| Sesión de asistencia | Pertenece a un curso y tiene una fecha. Cada curso tiene quince sesiones; solo se admite una sesión por curso y fecha. |
| Condición de asistencia | Para cada estudiante matriculado y sesión se registra exactamente una de estas condiciones: presente, ausente o justificado. |

## Autenticación y autorización

### Cuenta inicial del profesor

- La única cuenta del profesor se crea una vez mediante un comando de consola de CodeIgniter. El comando recibe el correo y la contraseña del profesor y guarda solo el hash de la contraseña.
- No se crea una segunda cuenta de profesor. El comando de creación no forma parte de la API pública.
- El correo del profesor debe pertenecer a un dominio registrado en `dominios_permitidos`.
- Rechazar la creación de la cuenta del profesor si su correo ya pertenece a un estudiante.

### Inicio de sesión y JWT

- La ruta de inicio de sesión acepta correo y contraseña sin JWT previo. Si las credenciales son válidas, entrega un JWT para acceder a las rutas protegidas.
- El JWT es válido durante una hora. Una vez vencido, el backend rechaza su uso en rutas protegidas y el frontend cierra la sesión del usuario.
- La otra ruta pública es `GET /api/healt`: comprueba que el servidor atiende la solicitud y ejecuta una consulta simple en MySQL sin requerir JWT. Devuelve disponibilidad cuando la consulta funciona y un estado HTTP de servicio no disponible cuando falla, sin revelar credenciales ni detalles internos.
- Todas las rutas de la API salvo `POST /api/login` y `GET /api/healt` requieren un JWT válido. El backend identifica al usuario y su rol a partir de la autenticación, y verifica el permiso correspondiente antes de ejecutar la operación.
- El profesor puede realizar las operaciones de gestión. El estudiante solo puede consultar sus propias calificaciones y asistencia, consultar sus concentrados y cambiar su contraseña. La descarga `.xlsx` del concentrado corresponde solo al profesor.
- La autorización de las consultas del estudiante se aplica en el backend: el estudiante no puede obtener datos de otro estudiante mediante parámetros manipulados.
- Al recargar el frontend antes de que venza el JWT, la sesión del usuario sigue disponible hasta el vencimiento; el backend mantiene la vigencia de una hora y no amplía el token por la recarga.

### Contraseñas de estudiantes

- El profesor selecciona a un estudiante y solicita generar una contraseña. El backend genera una contraseña nueva, entrega su valor al profesor para que la comunique por correo y reemplaza en la base de datos el hash anterior. La contraseña anterior deja de ser válida.
- El estudiante puede cambiar su contraseña desde **Perfil > Cambiar contraseña**. El backend guarda únicamente el hash de la nueva contraseña.
- Ni la contraseña del profesor ni las contraseñas de estudiantes se guardan en texto claro.

## Operaciones de gestión del profesor

### Cuatrimestres y cursos

- Registrar el cuatrimestre con el formato `N-AAAA` y sus fechas de inicio y cierre. Los cursos del mismo cuatrimestre comparten esas fechas.
- Crear un curso con nombre, código, número de grupo y cuatrimestre. Dos cursos pueden compartir código y cuatrimestre si tienen números de grupo diferentes.
- Permitir eliminar un curso únicamente después de la fecha de cierre de su cuatrimestre y después de recibir confirmación del profesor en el flujo del frontend.
- Al eliminar el curso, eliminar sus matrículas, componentes, actividades individuales, calificaciones, observaciones y asistencias. Conservar los registros de estudiantes y del cuatrimestre, aunque queden sin otros cursos. Conservar siempre las actividades del catálogo.
- La eliminación debe dejar los datos relacionados en un estado consistente: no deben quedar calificaciones, matrículas ni asistencias referidas al curso eliminado.

### Catálogo de actividades

- El catálogo inicial contiene: prueba parcial, proyecto, tarea, trabajo en clase, indagación y exposición, prueba corta y repositorio GitHub. **Indagación y exposición** es una sola opción.
- El profesor puede crear, consultar, editar y eliminar opciones del catálogo.
- Rechazar la creación o el cambio de nombre de una actividad si ya existe otra con ese mismo nombre, sin distinguir mayúsculas, minúsculas ni tildes. Por ejemplo, `Prueba parcial`, `prueba parcial` y `Prúeba parcial` son duplicados.
- Impedir la edición o eliminación de una opción mientras esté asociada a cualquier componente de evaluación. Eliminar un curso no elimina su opción del catálogo.
- Al consultar el catálogo, informar para cada actividad un valor booleano `en_uso` calculado a partir de sus asociaciones vigentes. Una actividad recién creada no está en uso. El rechazo del backend sigue siendo obligatorio aunque el frontend bloquee esas acciones.

### Componentes de evaluación

- Agregar componentes a un curso mediante una actividad seleccionada del catálogo, un título de máximo 128 palabras, una cantidad entera de actividades individuales entre 1 y 15, una descripción de máximo 256 caracteres, un porcentaje con hasta dos decimales y un puntaje total de 5 a 100 puntos con hasta dos decimales.
- Rechazar la incorporación de un componente si la suma de porcentajes de todos los componentes del curso superaría el 100 %.
- Distribuir por igual el porcentaje y el puntaje total del componente entre sus actividades individuales. Truncar cada valor asignado a dos decimales; no asignar el residuo a ninguna actividad. Ejemplo: 10 % y 10 puntos repartidos entre 3 actividades producen `3,33 %` y `3,33` puntos para cada una.
- Al matricular a un estudiante en el curso, poner a su disposición todas las actividades individuales de sus componentes para registrar calificaciones.

### Estudiantes y matrículas

- Registrar nombre, dos apellidos y correo al matricular a un estudiante. La dirección de correo completa es única entre las cuentas del profesor y de los estudiantes.
- Comparar la dirección completa distinguiendo mayúsculas de minúsculas: `Persona@ucatolica.ac.cr` y `persona@ucatolica.ac.cr` se consideran direcciones distintas.
- Si el correo ya corresponde a un estudiante, reutilizar su registro y asociarlo al curso seleccionado. No crear una segunda cuenta para el mismo correo.
- Si el correo pertenece al profesor, rechazar la matrícula con esa dirección.
- Admitir únicamente correos cuyo dominio figure en `dominios_permitidos`. La tabla contiene inicialmente `ucatolica.ac.cr` y permite añadir otros dominios en el futuro sin cambiar la regla de validación. No se expone un CRUD de dominios en la API.
- Permitir que un estudiante esté matriculado simultáneamente en varios cursos.

### Calificaciones y observaciones

- Permitir al profesor registrar para cada estudiante matriculado una calificación por actividad individual, expresada en puntos obtenidos con dos decimales.
- Rechazar una calificación negativa o superior al puntaje asignado a la actividad. Aceptar el cero y el puntaje completo.
- Tratar como cero, para el cálculo del resultado del curso, una actividad cuya calificación todavía no se ha registrado.
- Asociar a esa actividad y estudiante una observación del profesor de máximo 125 caracteres.
- Permitir que el profesor consulte la nota y observación vigentes de una matrícula y actividad individual antes de volver a editarlas. Si todavía no hay calificación, devolver ambas como `null`; rechazar asociaciones de cursos o grupos distintos. Esta consulta requiere JWT de profesor y no añade observaciones al concentrado.
- Calcular el aporte porcentual de cada actividad en proporción a la nota ingresada: `aporte = (puntos obtenidos / puntaje asignado a la actividad) × porcentaje asignado a la actividad`.
- Ejemplo: en una actividad de 10 puntos y 2 %, una nota de `8,50` puntos aporta `1,70 %`; una nota de `10,00` puntos aporta `2,00 %`.
- Truncar a dos decimales el aporte porcentual de cada actividad antes de sumarlo. Calcular el resultado del curso como suma de esos aportes truncados y truncar también el resultado final a dos decimales.

### Asistencia

- Mantener quince sesiones de asistencia por curso. Cada sesión se identifica por el curso y la fecha; no permitir dos sesiones para el mismo curso en la misma fecha.
- Rechazar una fecha de sesión anterior al inicio o posterior al cierre del cuatrimestre del curso. Las fechas de inicio y cierre sí están permitidas.
- Al crear una sesión, presentar al profesor los estudiantes matriculados en ese curso y exigir una condición de asistencia para cada uno: presente, ausente o justificado.
- Permitir consultar una sesión guardada y modificar sus condiciones de asistencia.
- Al matricular a un estudiante después de que ya existen sesiones de asistencia en el curso, incorporarlo a cada sesión anterior con la condición **justificado**. Permitir al profesor modificar posteriormente la condición de ese estudiante en esas sesiones.

## Consultas y concentrados

### Profesor

- Proporcionar los datos del concentrado de un curso y grupo, calculados en tiempo de ejecución a partir de MySQL. Para cada estudiante y actividad individual, entregar la nota y el porcentaje obtenido.
- Generar a solicitud del profesor un archivo `.xlsx` del mismo curso y grupo, con los mismos datos de la tabla presentada en pantalla. Esta operación requiere JWT de profesor.
- Los concentrados no incluyen puntaje máximo, observaciones ni resultado total del curso.

### Estudiante

- Proporcionar únicamente los cursos del estudiante autenticado, con sus calificaciones y asistencia agrupadas por curso.
- Para las tarjetas de cada actividad individual, proporcionar la nota, el porcentaje obtenido y las observaciones del profesor.
- Para la tabla de concentrado del estudiante, proporcionar por curso la nota y el porcentaje obtenido de cada actividad individual. El estudiante no puede descargar el concentrado en `.xlsx`.

## Validaciones y consistencia

- Aplicar en el backend todas las restricciones de formato, longitud, rango, unicidad y pertenencia descritas arriba, aunque el frontend también las valide.
- Verificar la pertenencia de una actividad individual al curso del estudiante antes de registrar o consultar su calificación. Verificar la matrícula en el curso antes de registrar asistencia o mostrar información al estudiante.
- Verificar que las operaciones del profesor no alteren registros de otro curso o grupo por una identificación incorrecta, especialmente cuando el código de curso se repite.
- Devolver al frontend el motivo de una solicitud rechazada sin revelar contraseñas, hashes ni secretos de configuración. El contrato exacto de errores queda para el diseño de la API.
- El concentrado y el `.xlsx` se construyen al solicitarse con los datos vigentes en la base de datos; no se consideran copias permanentes que sustituyan las calificaciones originales.

## Criterios de aceptación verificables

- Dos cursos del mismo cuatrimestre pueden compartir código si tienen números de grupo diferentes; no se confunden sus matrículas, notas ni asistencias.
- Una actividad del catálogo utilizada por un componente no se puede editar ni eliminar. Si se borra el curso asociado, la actividad permanece en el catálogo.
- Para un componente de 10 % y 10 puntos con 3 actividades, cada actividad recibe `3,33 %` y `3,33` puntos, sin trasladar el residuo a la última.
- Para una actividad de 10 puntos y 2 %, una nota de `8,50` puntos produce un aporte de `1,70 %`.
- Una actividad sin nota aporta cero al resultado del curso; una nota fuera del intervalo entre cero y el puntaje asignado se rechaza.
- Un JWT vencido después de una hora no permite acceder a rutas protegidas.
- Un correo ajeno a los dominios de `dominios_permitidos` se rechaza al crear la cuenta del profesor o matricular a un estudiante.
- Dos correos que solo difieren en mayúsculas y minúsculas se tratan como direcciones distintas al aplicar la unicidad.
- Un componente admite porcentaje y puntaje total con hasta dos decimales; la creación de una actividad con nombre ya presente en el catálogo se rechaza aunque cambie el uso de mayúsculas, minúsculas o tildes.
- Se rechaza crear una sesión de asistencia con fecha fuera del intervalo inclusivo del cuatrimestre del curso.
- Una dirección de correo asignada al profesor no se acepta para un estudiante, ni una dirección de estudiante para crear la cuenta del profesor.
- No se guarda una segunda sesión de asistencia con la misma fecha para un mismo curso, ni una sesión sin condición para alguno de los estudiantes matriculados incluidos en ella.
- Si un estudiante se matricula después de tres sesiones registradas, aparece en las tres con condición inicial **justificado**; el profesor puede cambiar cada condición posteriormente.
- Un estudiante matriculado en varios cursos conserva una sola cuenta y puede consultar únicamente sus propios datos agrupados por curso.
- El concentrado visible del profesor y su archivo `.xlsx` para un curso y grupo contienen las mismas notas y porcentajes obtenidos; el estudiante no dispone de esa descarga.
- Al eliminar un curso después del cierre del cuatrimestre, desaparecen sus datos propios y se conservan estudiantes, cuatrimestre y actividades del catálogo.
