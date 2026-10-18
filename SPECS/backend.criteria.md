# Criterios de aceptación del backend

## Alcance y uso

Estos criterios convierten [backend.specs.md](backend.specs.md), [backend.tasks.md](backend.tasks.md), [database.specs.md](database.specs.md) y [general.md](general.md) en comprobaciones para aceptar la implementación. Los identificadores `BE-XX` permiten registrar evidencia de cada resultado. Los pasos entre paréntesis corresponden a la secuencia de `backend.tasks.md`.

**Estado actual:** el usuario autorizó implementar el backend y la base de datos. El contrato HTTP implementado se documenta en [backend.api.md](backend.api.md). Los criterios siguen siendo la referencia de aceptación funcional.

## Preparación y contrato (pasos 1 y 2)

- **BE-01 — Entorno Docker operativo.** Con la configuración documentada, se levantan frontend, backend y MySQL; el frontend puede comunicarse con la API y el backend con MySQL. El entorno se detiene y vuelve a levantar sin instalar el stack del aplicativo en el equipo anfitrión. **Evidencia:** ejecución de los comandos documentados y comprobación de conectividad dentro de Docker.
- **BE-02 — Pruebas aisladas.** Las migraciones, comandos del backend y pruebas se ejecutan en Docker. Las pruebas usan una base separada de los datos de desarrollo y pueden repetirse desde un estado limpio. **Evidencia:** ejecución documentada de la suite sobre la base de prueba.
- **BE-03 — Configuración y secretos.** Las credenciales de MySQL y la clave JWT proceden de variables de entorno; los archivos locales con secretos están excluidos de Git y no se exponen al frontend ni en respuestas de la API. **Evidencia:** revisión de configuración, archivos preparados para commit y respuestas relevantes.
- **BE-04 — Contrato completo y trazable.** Cada operación prevista en `backend.specs.md` tiene definidos entrada, salida, validaciones, errores y rol autorizado; cada regla está vinculada a una comprobación de este documento. El inicio de sesión es la única ruta pública acordada. **Evidencia:** revisión de la matriz de operaciones y permisos antes de crear rutas.
- **BE-05 — Responsabilidades separadas.** La API del backend usa rutas, controladores, servicios, modelos, filtros y auxiliares según su responsabilidad; no sirve vistas del aplicativo. **Evidencia:** revisión de la organización del proyecto y de los flujos implementados.

## Modelo de datos y datos iniciales (paso 3)

- **BE-06 — Esquema reproducible.** Las migraciones crean las entidades, relaciones, índices únicos y campos decimales de `database.specs.md` en una base vacía sin intervención manual. **Evidencia:** prueba de integración con base limpia.
- **BE-07 — Datos iniciales correctos.** `dominios_permitidos` contiene inicialmente `ucatolica.ac.cr` y el catálogo contiene las siete opciones acordadas; «indagación y exposición» ocupa un solo registro. No se inserta una cuenta de profesor con contraseña predeterminada. **Evidencia:** consulta de la base preparada por migraciones y datos iniciales.
- **BE-08 — Reglas de comparación distintas.** El índice y la búsqueda de correos distinguen `Persona@ucatolica.ac.cr` de `persona@ucatolica.ac.cr`, mientras que el nombre del catálogo rechaza `Prueba parcial` si ya existe `prueba parcial` o `Prúeba parcial`. **Evidencia:** pruebas de integración que comprueban inserción y búsqueda.
- **BE-09 — Integridad entre entidades.** Las relaciones impiden registros huérfanos; una calificación solo une una matrícula con una actividad de su curso y una asistencia solo une una matrícula con una sesión de su curso. **Evidencia:** pruebas de integración y rechazos de asociaciones entre cursos.

## Identidad y autorización (paso 4)

- **BE-10 — Profesor único.** El comando de consola de CodeIgniter crea una sola cuenta de profesor con correo de dominio permitido y contraseña hasheada. Rechaza una segunda cuenta y un correo que ya use un estudiante. **Evidencia:** pruebas del comando en la base de prueba.
- **BE-11 — Inicio de sesión.** Con correo y contraseña vigentes se obtiene un JWT sin presentar otro token; las credenciales incorrectas se rechazan. El correo se compara distinguiendo mayúsculas y minúsculas. **Evidencia:** pruebas de API con ambas variantes de correo.
- **BE-12 — Vigencia del JWT.** Un token válido permite operaciones autorizadas durante una hora; después de vencer, o si falta o está alterado, las rutas protegidas lo rechazan. **Evidencia:** pruebas de API con token válido, ausente, alterado y vencido.
- **BE-13 — Permisos por rol y por identidad.** El profesor accede a las operaciones de gestión; un estudiante no puede realizarlas, descargar el `.xlsx` ni consultar datos de otro estudiante mediante cambios en parámetros. **Evidencia:** pruebas de API para ambos roles y dos estudiantes distintos.
- **BE-14 — Contraseñas del estudiante.** El profesor puede generar una nueva contraseña y recibirla para comunicarla; la contraseña anterior deja de autenticar. El estudiante puede cambiar la suya desde su perfil. En la base solo se conserva el hash vigente y ninguna respuesta revela hashes. **Evidencia:** pruebas de API, autenticación anterior y posterior y revisión del registro persistido.

## Cuatrimestres, cursos y catálogo (paso 5)

- **BE-15 — Cuatrimestre válido.** Se registra con código `N-AAAA`, `N` entre 1 y 3, y fechas de inicio y cierre válidas; el inicio no es posterior al cierre. Sus cursos consultan las mismas fechas. **Evidencia:** pruebas de API y de integración con límites y entradas inválidas.
- **BE-16 — Identidad del curso y grupo.** Dos cursos del mismo cuatrimestre pueden compartir código si sus números de grupo difieren. Se rechaza repetir cuatrimestre, código y grupo; las consultas de un grupo no incluyen datos de otro. **Evidencia:** pruebas de integración y API con dos grupos y cuatrimestres distintos.
- **BE-17 — Catálogo gestionable.** El profesor puede crear, consultar, editar y eliminar opciones no vinculadas. La creación o el cambio de nombre se rechaza si duplica otro nombre aunque solo varíen mayúsculas, minúsculas o tildes. **Evidencia:** pruebas de API y de unicidad en MySQL.
- **BE-18 — Catálogo vinculado protegido.** Una actividad usada en cualquier componente no puede editarse ni eliminarse. **Evidencia:** pruebas con actividad libre y vinculada, antes y después de intentar ambas operaciones.

## Componentes y reparto (paso 6)

- **BE-19 — Límites del componente.** Al crear un componente se respetan título de hasta 128 palabras, descripción de hasta 256 caracteres, cantidad entera de 1 a 15, porcentaje y puntaje con hasta dos decimales, y puntaje total de 5 a 100. Las entradas que exceden esos límites se rechazan. **Evidencia:** pruebas unitarias de límites y pruebas de API.
- **BE-20 — Peso total del curso.** Se acepta un componente si la suma de porcentajes del curso no supera 100 % y se rechaza si lo supera. La suma se evalúa dentro del curso correspondiente. **Evidencia:** pruebas de integración en dos cursos.
- **BE-21 — Reparto igual y truncado.** El componente genera exactamente la cantidad indicada de actividades individuales, todas con el mismo puntaje y porcentaje asignados. Para 10 % y 10 puntos entre tres actividades, cada una vale `3,33 %` y `3,33` puntos; el residuo no se asigna a ninguna. **Evidencia:** pruebas unitarias e inspección de filas persistidas.
- **BE-22 — Creación consistente.** Si falla la creación de una actividad individual, no quedan un componente ni actividades parciales de esa operación. **Evidencia:** prueba de integración de la transacción.

## Estudiantes y matrículas (paso 7)

- **BE-23 — Identidad reutilizada.** Al matricular se registran nombre, dos apellidos y correo. Si el correo exacto ya corresponde a un estudiante, se reutiliza la cuenta existente en otro curso; una segunda matrícula en el mismo curso se rechaza. **Evidencia:** pruebas de API y consulta de usuarios y matrículas.
- **BE-24 — Correo permitido y único entre roles.** Solo se admiten dominios registrados en `dominios_permitidos`; el correo del profesor no sirve para crear un estudiante y viceversa. Los correos que solo difieren en mayúsculas se consideran distintos. No existe un CRUD público de dominios. **Evidencia:** pruebas de API y revisión de rutas.
- **BE-25 — Matrícula tardía.** Si ya existen sesiones en el curso, la nueva matrícula aparece exactamente una vez en cada sesión anterior con condición inicial `justificado`; la operación no deja matrículas o asistencias parciales si falla. **Evidencia:** prueba de integración con varias sesiones previas.

## Calificaciones y resultados (paso 8)

- **BE-26 — Nota y observación por actividad.** El profesor registra puntos obtenidos con hasta dos decimales y una observación de hasta 125 caracteres para cada actividad individual y matrícula. La nota debe estar entre cero y el puntaje de esa actividad, ambos inclusive. **Evidencia:** pruebas de API con valores válidos y fuera de rango.
- **BE-27 — Curso correcto.** Una nota se rechaza si la actividad pertenece a un curso o grupo distinto del de la matrícula, aunque ambos cursos compartan código. **Evidencia:** prueba de integración con dos grupos.
- **BE-28 — Aporte proporcional.** Para una actividad de 10 puntos y 2 %, `8,50` puntos producen `1,70 %` y `10,00` producen `2,00 %`. **Evidencia:** prueba unitaria del cálculo y consulta por API.
- **BE-29 — Truncamiento antes de sumar.** Cada aporte se trunca a dos decimales antes de sumar; el resultado del curso es la suma de esos aportes y se presenta con hasta dos decimales, sin redistribuir fracciones descartadas. **Evidencia:** prueba unitaria con aportes de más de dos decimales y consulta del resultado.
- **BE-30 — Nota ausente.** Una actividad sin nota aporta cero al resultado, pero sigue siendo distinguible de una nota explícita de `0,00` en los datos consultados. **Evidencia:** prueba de integración con ambas situaciones.

## Asistencia (paso 9)

- **BE-31 — Sesiones por curso y fecha.** Se permite una sesión por curso y fecha; la sesión quince se acepta y una sesión dieciséis se rechaza. También se rechaza repetir una fecha en el mismo curso. **Evidencia:** pruebas de API y de unicidad en la base.
- **BE-32 — Fecha dentro del cuatrimestre.** Se aceptan las fechas de inicio y cierre; se rechazan fechas anteriores o posteriores a ellas. **Evidencia:** pruebas de API con los cuatro límites.
- **BE-33 — Condición para cada matriculado.** La creación de la sesión exige una condición `presente`, `ausente` o `justificado` para cada estudiante matriculado, sin asignarla a estudiantes de otro curso. Las condiciones faltantes o inválidas se rechazan sin guardar media sesión. **Evidencia:** pruebas de API e integración.
- **BE-34 — Edición histórica.** Una sesión guardada puede consultarse y sus condiciones pueden cambiarse después, incluso la condición `justificado` asignada por una matrícula tardía. **Evidencia:** prueba de API antes y después de la edición.

## Eliminación del curso (paso 10)

- **BE-35 — Momento permitido.** El backend rechaza eliminar un curso antes o durante la fecha de cierre del cuatrimestre y lo permite después del cierre. La confirmación previa del profesor corresponde al flujo del frontend. **Evidencia:** pruebas de API con fechas controladas y prueba del flujo de confirmación en frontend.
- **BE-36 — Borrado completo del curso.** Tras eliminarlo no quedan sus matrículas, componentes, actividades individuales, calificaciones, observaciones, sesiones ni asistencias. La operación es atómica. **Evidencia:** pruebas de integración y rollback ante un fallo.
- **BE-37 — Registros independientes conservados.** Permanecen estudiantes y sus cuentas, el cuatrimestre y todas las actividades del catálogo, aunque el curso borrado fuera su última relación. Permanecen intactos los otros cursos y grupos. **Evidencia:** prueba de integración antes y después del borrado.

## Consultas, concentrados y descarga (paso 11)

- **BE-38 — Consulta del estudiante.** El estudiante ve sus cursos con calificaciones y asistencia agrupadas por curso; las tarjetas de actividad incluyen su nota, el porcentaje obtenido y la observación. No recibe datos de otros estudiantes. **Evidencia:** pruebas de API con varias matrículas y dos identidades.
- **BE-39 — Concentrados en tiempo de ejecución.** El profesor consulta una tabla por curso y grupo con nota y porcentaje obtenido por estudiante y actividad; el estudiante consulta una tabla por cada curso propio con esos dos datos. Al cambiar una calificación, la siguiente consulta refleja el cambio sin depender de una copia persistida. **Evidencia:** pruebas de API antes y después de editar una nota.
- **BE-40 — Contenido de los concentrados.** Las tablas del concentrado contienen nota y porcentaje obtenido por actividad, sin puntaje máximo, observaciones ni resultado total del curso. Las observaciones continúan disponibles en las tarjetas del estudiante. **Evidencia:** comparación de respuestas y especificación del contrato.
- **BE-41 — Descarga reservada al profesor.** El profesor obtiene un `.xlsx` del curso y grupo con los mismos datos vigentes que su tabla. Un estudiante no puede descargarlo y el archivo no sustituye las calificaciones guardadas. **Evidencia:** lectura del archivo generado, comparación con el concentrado y prueba de autorización.

## Cierre y evidencia de pruebas (paso 12)

- **BE-42 — Suite ejecutada en Docker.** Pasan las pruebas unitarias de cálculos y validaciones, las de integración con MySQL y las de API de los flujos y rechazos anteriores, sobre datos de prueba aislados. **Evidencia:** resultados reproducibles y comandos documentados.
- **BE-43 — Recorrido completo.** En un entorno limpio se puede crear el profesor con el comando, iniciar sesión, registrar cuatrimestre, cursos, catálogo, componentes, estudiantes, notas y asistencia, consultar ambos roles, descargar el `.xlsx` como profesor y eliminar un curso cerrado conservando los datos independientes. **Evidencia:** prueba de recorrido y verificación final de la base.
- **BE-44 — Entrega documentada.** Están documentados el arranque y la reconstrucción de Docker, la configuración sin secretos, las migraciones, el comando inicial del profesor y la ejecución de pruebas. Cada criterio tiene resultado registrado y no quedan fallos conocidos de los flujos acordados. **Evidencia:** revisión de documentación, resultados de la suite y matriz de trazabilidad.
