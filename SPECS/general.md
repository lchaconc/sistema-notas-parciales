# Especificación general del sistema de notas parciales

## Propósito y alcance

El aplicativo permitirá registrar y consultar calificaciones y asistencia de estudiantes por curso. Lo utilizará un solo profesor, quien contará con un módulo de gestión, y los estudiantes tendrán un módulo de consulta. Esta especificación recoge los requisitos de `requerimientos-iniciales.docx` y los criterios acordados posteriormente.

## Roles y acceso

- **Estudiante:** accede mediante su correo y una contraseña facilitada por el profesor. Puede consultar sus calificaciones, observaciones y asistencia.
- **Profesor:** es el único usuario de este rol en el aplicativo. Tiene una cuenta con correo y contraseña almacenada como hash. La cuenta se crea una sola vez mediante un comando de consola de CodeIgniter. Crea cursos, gestiona el catálogo de actividades, configura componentes de evaluación, matricula estudiantes, registra calificaciones y asistencia, y genera contraseñas para estudiantes.
- Los correos de profesor y estudiantes deben pertenecer a un dominio permitido. La base de datos tiene una tabla `dominios_permitidos`, inicialmente con `ucatolica.ac.cr` como único dominio; la validación consulta esa tabla para admitir posibles dominios adicionales en el futuro. No se requiere un módulo CRUD para esta tabla.
- Una dirección de correo completa no puede pertenecer a dos cuentas, incluso si una es del profesor y la otra de un estudiante.
- Las direcciones que difieren en mayúsculas y minúsculas se consideran distintas; por ejemplo, `Persona@ucatolica.ac.cr` y `persona@ucatolica.ac.cr` pueden identificar cuentas diferentes.

## Gestión de cursos

- Para crear un curso se registran su nombre, código, número de grupo y cuatrimestre. El cuatrimestre tiene una fecha de inicio y una fecha de cierre, comunes a todos los cursos que se imparten en él.
- El cuatrimestre utiliza el formato `N-AAAA`, donde `N` toma un valor de 1 a 3. Ejemplo: `3-2026`.
- Un mismo código de curso puede utilizarse en cuatrimestres distintos y también en varios cursos del mismo cuatrimestre cuando corresponden a grupos separados. Cada grupo se distingue por un número, por ejemplo, grupo `1` y grupo `2`; dos grupos con el mismo código y cuatrimestre deben tener números distintos.
- La fecha de cierre del cuatrimestre determina cuándo termina para todos sus cursos. Cada curso puede eliminarse después de esa fecha.
- Antes de eliminar un curso, el aplicativo solicita confirmación al profesor. Al confirmar, se eliminan el curso y todos sus datos asociados: matrículas en ese curso, componentes de evaluación, actividades individuales, calificaciones, observaciones y registros de asistencia del curso.
- Los registros del estudiante y del cuatrimestre se conservan al eliminar un curso, incluso si era el último curso al que estaban asociados.
- Las actividades del catálogo no se eliminan al borrar un curso, aunque ya no estén asociadas a ningún componente. El catálogo es independiente y se gestiona desde su propio módulo.

## Catálogo de actividades

- El catálogo incluye inicialmente: prueba parcial, proyecto, tarea, trabajo en clase, indagación y exposición, prueba corta y repositorio GitHub. **«Indagación y exposición» es una sola opción.**
- El profesor dispone de un módulo para crear, consultar, editar y eliminar actividades del catálogo.
- No se permiten dos actividades del catálogo con el mismo nombre, aunque difieran solo en mayúsculas y minúsculas. Por ejemplo, `Prueba parcial` y `prueba parcial` son duplicados.
- La eliminación de un curso no elimina actividades del catálogo; estas se gestionan únicamente en este módulo.
- Una actividad del catálogo queda bloqueada para edición y eliminación mientras esté asociada a un componente de evaluación.

## Componentes de evaluación

Después de crear un curso, el profesor puede agregarle componentes de evaluación mediante un formulario con estos campos:

| Campo | Requisito inicial |
| --- | --- |
| Actividad | Se selecciona del catálogo de actividades gestionado por el profesor. |
| Título | Texto de hasta 128 palabras. Ejemplo: «Examen parcial». |
| Cantidad de actividades | Número entero entre 1 y 15. |
| Descripción | Texto de hasta 256 caracteres. |
| Porcentaje | Peso del componente en el curso, con hasta dos decimales. Se distribuye por igual entre sus actividades; por ejemplo, 2 actividades con un componente de 10 % valen 5 % cada una. La suma de porcentajes de los componentes de un curso no puede superar el 100 %. |
| Puntaje | Valor entre 5 y 100 puntos, con hasta dos decimales, para el componente completo. El aplicativo lo distribuye entre la cantidad de actividades del componente. |

El porcentaje y el puntaje del componente se distribuyen por igual entre sus actividades. Si alguna división produce más de dos decimales, el valor asignado a cada actividad se trunca a dos decimales. La fracción truncada no se redistribuye y ninguna actividad recibe un valor diferente. Por ejemplo, un componente de 10 % y 10 puntos con 3 actividades asigna `3,33 %` y `3,33` puntos a cada una.

## Estudiantes y matrícula

- Para matricular a un estudiante se registran nombre, dos apellidos, correo y curso.
- El correo identifica de forma única a cada cuenta. Si ya existe un estudiante con ese correo, se reutiliza su registro para matricularlo en otro curso; no se crea un estudiante duplicado. Si el correo pertenece al profesor, se rechaza la matrícula con esa dirección.
- Un estudiante puede matricularse simultáneamente en varios cursos.
- Al asociar un estudiante con un curso, sus componentes de evaluación quedan disponibles para ser calificados.

## Calificaciones y observaciones

- El profesor selecciona a un estudiante y registra una calificación por cada actividad individual de los componentes del curso en que está matriculado.
- Cada actividad individual asociada al estudiante dispone de una observación del profesor, con un máximo de 125 caracteres.
- El profesor ingresa los puntos obtenidos por el estudiante en cada actividad, como un número con dos decimales; por ejemplo, `8,50`.
- La nota ingresada debe estar entre cero y el puntaje asignado a la actividad, inclusive. Se rechazan notas negativas o superiores a ese puntaje. Una actividad aún sin nota cuenta como cero para el cálculo del resultado del curso.
- La aplicación calcula el aporte porcentual de cada actividad en proporción a los puntos obtenidos: `aporte = (puntos obtenidos / puntaje asignado a la actividad) × porcentaje de la actividad`. Por ejemplo, si la actividad vale 10 puntos y 2 %, obtener `10,00` puntos aporta `2,00 %` y obtener `8,50` puntos aporta `1,70 %`.
- Cada aporte porcentual se trunca a dos decimales antes de sumarlo. El resultado del curso es la suma de esos aportes truncados y el resultado final también se trunca a dos decimales.

## Concentrado de calificaciones

- **Backend:** genera el concentrado en tiempo de ejecución a partir de los datos de la base de datos. Proporciona al frontend la nota y el porcentaje obtenido por estudiante y actividad individual, según el curso, grupo y usuario que realiza la consulta. También genera, cuando el profesor solicita la descarga, un archivo `.xlsx` con los mismos datos de su tabla.
- **Frontend del profesor:** presenta una tabla por curso y grupo, con apariencia de hoja de cálculo. Para cada estudiante muestra la nota y el porcentaje obtenido en cada actividad individual. Permite descargar la tabla en formato `.xlsx`.
- **Frontend del estudiante:** presenta sus calificaciones en tablas agrupadas por curso. Para cada actividad individual muestra la nota y el porcentaje obtenido. Esta vista se suma a las tarjetas de calificaciones ya especificadas. El estudiante consulta estas tablas en pantalla y no dispone de descarga `.xlsx`.
- Los concentrados muestran la nota y el porcentaje obtenido por actividad. No incluyen en estas tablas el puntaje máximo, las observaciones ni el resultado total del curso.

## Asistencia

- Cada curso tiene quince sesiones y se consigna la asistencia en cada una.
- Para crear un registro de asistencia se selecciona el curso y se ingresa la fecha de la sesión. Se muestra la lista de estudiantes matriculados y se asigna obligatoriamente a cada uno una condición: **presente**, **ausente** o **justificado**.
- La fecha de la sesión debe estar entre las fechas de inicio y cierre del cuatrimestre del curso, inclusive. Se rechazan fechas fuera de ese intervalo.
- Solo puede existir un registro de asistencia por curso y fecha; no se crean varios registros para un mismo curso en un mismo día.
- Es posible cargar registros de asistencia guardados y modificarlos.
- Si un estudiante se matricula después de que ya se registraron sesiones en su curso, se agrega a esas sesiones anteriores con la condición inicial **justificado**. El profesor puede editar después la condición de ese estudiante en cualquiera de esas sesiones.

## Acceso y consulta del estudiante

- El profesor selecciona a un estudiante en la función **Generar contraseña**. El aplicativo genera una contraseña, se la muestra al profesor y reemplaza el hash de la contraseña registrada previamente para ese estudiante; la anterior deja de servir para iniciar sesión.
- El profesor comunica por correo al estudiante la contraseña generada. Si el estudiante pierde el acceso, el profesor utiliza de nuevo **Generar contraseña** y le comunica la nueva contraseña por correo.
- El estudiante inicia sesión con su correo y la contraseña vigente. Puede cambiarla en cualquier momento desde la opción **Perfil > Cambiar contraseña**.
- Las contraseñas se almacenan como hashes en la base de datos, tanto al generarlas como al cambiarlas; no se guarda su valor en texto claro.
- El estudiante consulta sus calificaciones y asistencia agrupadas por curso cuando está matriculado en varios cursos.
- Las calificaciones se presentan mediante tarjetas por actividad individual. Cada tarjeta muestra la nota del estudiante, el porcentaje que obtuvo en esa actividad y las observaciones del profesor. Por ejemplo, si una actividad vale 2 % y el estudiante obtuvo `1,70 %`, la tarjeta muestra `1,70 %`.

## Restricciones técnicas indicadas

- La solución se organiza como un monorepositorio y todo el desarrollo y la ejecución del aplicativo se realizan en un entorno Docker que incluye frontend, backend y MySQL.
- El primer trabajo de implementación es preparar y configurar ese entorno para levantar los servicios y comprobar su comunicación. Después se desarrollan las funcionalidades y se ejecutan dentro de Docker los comandos del proyecto, las migraciones y las pruebas, sin requerir instalaciones locales del stack.
- Se documentan las instrucciones para levantar, detener y reconstruir el entorno, acceder al aplicativo y ejecutar las pruebas.
- La carpeta de frontend utiliza Vite, React, Tailwind CSS y Motion. Las solicitudes al backend usan `fetch` nativo.
- Se utiliza JavaScript, sin TypeScript, en el frontend.
- La carpeta de backend contiene la aplicación de servidor con PHP, CodeIgniter y MySQL.
- Las rutas de la API se protegen mediante JWT. La ruta de inicio de sesión es la excepción: acepta correo y contraseña sin JWT previo y, tras autenticar al usuario, entrega un token válido durante una hora. Al vencer el token, el backend deja de aceptar las solicitudes autenticadas y el frontend cierra la sesión del usuario.
- Se evitan dependencias innecesarias. Las dependencias que se incorporen deben tener aceptación en la comunidad y una versión estable y segura.

## Pruebas durante la implementación

- La implementación del backend y del frontend debe incluir pruebas planificadas y ejecutadas como parte de cada funcionalidad. Una tarea no se considera terminada hasta comprobar sus reglas de negocio y los casos de error relevantes.
- **Backend:** incluir pruebas unitarias de las reglas de cálculo, truncamiento y validación; pruebas de integración con MySQL para relaciones, unicidad, transacciones y conservación o eliminación de registros; y pruebas de la API para autenticación, autorización por rol, validaciones, consultas y descarga del concentrado `.xlsx`.
- **Frontend:** incluir pruebas unitarias de funciones auxiliares y transformaciones de datos cuando tengan lógica propia; pruebas de componentes y flujos para formularios, tablas, tarjetas, estados de error y acceso según el rol; y pruebas de extremo a extremo de los recorridos principales del profesor y del estudiante con el backend.
- Las pruebas deben cubrir casos normales, límites y rechazos importantes. Entre ellos: correos que distinguen mayúsculas, nombres duplicados del catálogo sin distinguirlas, reparto y truncamiento de porcentajes y puntos, notas fuera de rango o ausentes, fechas y condiciones de asistencia, matrícula tardía, caducidad del JWT, aislamiento de datos del estudiante y eliminación de un curso sin borrar registros compartidos.
- Las pruebas automatizadas deben ser repetibles y ejecutarse con datos y configuración de prueba separados de los datos reales. Se debe documentar cómo ejecutarlas y revisar sus resultados antes de dar por finalizada la implementación.
