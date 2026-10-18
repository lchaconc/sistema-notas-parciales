# Plan de tareas para implementar el backend

## Alcance y fuentes

Este plan ordena la implementación del backend según [general.md](general.md), [backend.specs.md](backend.specs.md), [database.specs.md](database.specs.md) y [AGENTS.md](../AGENTS.md). El usuario ya autorizó implementar el backend y la base de datos. Cada paso incluye las pruebas que deben ejecutarse al implementarlo. El contrato HTTP concreto se documenta en [backend.api.md](backend.api.md).

## Secuencia de trabajo

### 1. Preparar y comprobar el entorno Docker

- Cuando se autorice la implementación, configurar primero los contenedores de frontend, backend y MySQL para que puedan levantarse y comunicarse dentro del monorepositorio. Preparar la estructura inicial necesaria para comprobar los tres servicios antes de desarrollar funcionalidades.
- Definir las variables de entorno necesarias, archivos de ejemplo sin secretos y exclusiones en `.gitignore`. Preparar una base de datos de prueba separada de la de desarrollo. Incorporar únicamente dependencias justificadas, estables y compatibles, dentro de los contenedores.
- Documentar los comandos para levantar, detener y reconstruir el entorno, entrar a los servicios y ejecutar comandos y pruebas dentro de Docker. Mantener la ejecución del stack y sus herramientas dentro de este entorno.
- **Comprobación:** los tres servicios arrancan; el frontend alcanza la API, el backend conecta con MySQL y con la base de prueba; el entorno puede detenerse y volver a levantarse; los secretos locales no quedan versionados.

### 2. Cerrar el contrato técnico de la API y organizar el backend

- Enumerar las operaciones necesarias para profesor y estudiante a partir de `backend.specs.md`: autenticación, catálogo, cuatrimestres y cursos, componentes, matrículas, calificaciones, asistencia, consultas, concentrados y descarga `.xlsx`.
- Definir para cada operación método y ruta, datos de entrada y salida, validaciones, errores, rol autorizado y pertenencia al curso. Mantener el inicio de sesión como única ruta pública acordada.
- Revisar el modelo de datos frente a los flujos completos y resolver con el usuario cualquier regla de negocio aún ambigua antes de fijarla en el contrato o en las migraciones. Organizar rutas, controladores, servicios, modelos, filtros y funciones auxiliares por responsabilidad.
- **Comprobación:** una matriz relaciona cada requisito de las especificaciones con una operación, una regla de datos y una prueba prevista. El contrato no introduce funcionalidades nuevas sin criterio del usuario y la suite inicial puede ejecutarse en Docker.

### 3. Crear el esquema relacional y sus datos iniciales

- Convertir `database.specs.md` en migraciones de MySQL: usuarios, estudiantes, dominios permitidos, cuatrimestres, cursos, catálogo, componentes, actividades individuales, matrículas, calificaciones, sesiones y asistencias.
- Establecer claves foráneas, índices únicos y precisión decimal. Configurar la unicidad del correo distinguiendo mayúsculas y la del nombre del catálogo sin distinguir mayúsculas ni tildes. Cargar el dominio `ucatolica.ac.cr` y las actividades iniciales; no crear una contraseña fija para el profesor.
- Definir cómo se aplican las restricciones que requieren varias tablas o filas mediante servicios y transacciones, además de las restricciones de la base de datos.
- **Pruebas de integración:** ejecutar migraciones y datos iniciales sobre una base limpia; comprobar relaciones, precisión, duplicados, comparación de correos y nombres, y que el esquema pueda prepararse nuevamente para otra ejecución de pruebas.

### 4. Implementar identidad, cuenta inicial y acceso

- Crear el comando de consola de CodeIgniter para dar de alta al único profesor con correo de dominio permitido y contraseña almacenada como hash; rechazar una segunda cuenta o un correo ya usado.
- Implementar inicio de sesión por correo y contraseña, emisión de JWT válido durante una hora y filtro de autenticación y autorización para todas las demás rutas. Respetar la comparación de correos que distingue mayúsculas.
- Implementar la generación y sustitución de contraseña del estudiante por el profesor y el cambio de contraseña del estudiante desde su perfil, guardando solo hashes.
- **Pruebas unitarias y de API:** credenciales válidas e inválidas; profesor único; dominio y correo duplicado; hash no reversible en respuestas ni en datos; contraseña anterior invalidada; JWT ausente, manipulado o vencido; rechazo de operaciones de otro rol o estudiante.

### 5. Implementar cuatrimestres, cursos y catálogo

- Registrar y consultar cuatrimestres con código `N-AAAA` y fechas compartidas; crear y consultar cursos identificados por cuatrimestre, código y número de grupo.
- Implementar el CRUD del catálogo, su carga inicial y el bloqueo de edición y eliminación mientras una actividad esté vinculada a un componente.
- Posponer la eliminación del curso hasta el paso 10, cuando ya existan todas las relaciones que debe limpiar.
- **Pruebas de integración y API:** formatos y fechas válidos; grupos con código repetido sin mezclar datos; rechazo de combinaciones duplicadas; nombres del catálogo repetidos con distinto uso de mayúsculas o tildes; bloqueo de actividades en uso.

### 6. Implementar componentes y distribución de actividades

- Crear componentes dentro del curso con actividad del catálogo, título, descripción, cantidad, porcentaje y puntaje conforme a los límites acordados.
- Verificar que la suma de porcentajes de componentes del curso no exceda 100 %. Generar la cantidad indicada de actividades individuales con reparto igual y truncamiento a dos decimales, sin trasladar residuos.
- Guardar componente y actividades individuales como una sola operación consistente.
- **Pruebas unitarias:** límites de cantidad, puntaje, porcentaje y longitud; entradas con dos decimales; reparto exacto y no exacto. **Pruebas de integración:** 10 % y 10 puntos en tres actividades producen tres filas de `3,33 %` y `3,33` puntos; una operación fallida no deja filas incompletas.

### 7. Implementar estudiantes y matrículas

- Crear un estudiante al matricularlo o reutilizar su cuenta cuando ya exista el mismo correo; rechazar el correo del profesor y los dominios no permitidos. Conservar una sola identidad para todos sus cursos.
- Impedir una segunda matrícula del mismo estudiante en el mismo curso. Al matricularlo en un curso con sesiones existentes, crear una asistencia `justificado` para cada sesión anterior dentro de la misma operación.
- **Pruebas de integración y API:** reutilización en varios cursos, unicidad sensible a mayúsculas, rechazo de matrícula duplicada, ausencia de registros parciales si falla la operación y alta tardía en una o varias sesiones.

### 8. Implementar calificaciones y resultados

- Registrar por matrícula y actividad individual los puntos obtenidos y la observación. Verificar que la actividad pertenezca al curso de la matrícula y que la nota esté entre cero y el puntaje asignado.
- Calcular el aporte porcentual de cada actividad según la proporción de puntos, truncarlo a dos decimales antes de sumar y calcular el resultado del curso. Tratar la ausencia de nota como aporte cero sin confundirla con una nota explícita de `0,00`.
- **Pruebas unitarias:** `8,50` de 10 puntos con peso de 2 % aporta `1,70 %`; reparto truncado; notas cero, máximas, ausentes y fuera de rango; suma de aportes previamente truncados. **Pruebas de integración y API:** observación de máximo 125 caracteres y rechazo de una actividad de otro curso o grupo.

### 9. Implementar las quince sesiones y la asistencia

- Crear y consultar sesiones por curso y fecha, dentro del intervalo inclusivo del cuatrimestre, como máximo quince y sin repetir día en el mismo curso.
- Exigir una condición válida por cada estudiante matriculado al crear la sesión. Permitir editar condiciones de sesiones anteriores, incluidas las creadas como `justificado` por matrícula tardía.
- **Pruebas de integración y API:** fechas límite y fuera de rango, día duplicado, sesión dieciséis, condición faltante o inválida, pertenencia de matrícula y sesión al mismo curso y edición posterior de asistencia histórica.

### 10. Implementar eliminación segura del curso

- Exigir que haya pasado la fecha de cierre del cuatrimestre; el frontend debe solicitar la confirmación previa prevista en las especificaciones.
- Eliminar en una transacción curso, matrículas, componentes, actividades individuales, calificaciones, observaciones, sesiones y asistencias relacionadas. Conservar siempre estudiantes, sus cuentas, cuatrimestre y catálogo, incluso si quedan sin cursos.
- **Pruebas de integración y API:** rechazo antes del cierre; eliminación completa después del cierre; permanencia de datos compartidos y de otros grupos; rollback sin estado parcial ante un fallo de la operación.

### 11. Implementar consultas y concentrados

- Proporcionar al estudiante autenticado sus cursos, tarjetas con nota, aporte porcentual y observación, y asistencia agrupada por curso. Impedir que consulte datos de otra cuenta.
- Generar en tiempo de ejecución el concentrado del profesor por curso y grupo y el del estudiante por curso. Incluir nota y porcentaje obtenido por actividad, incluso cuando todavía no haya nota, conforme al contrato que se defina en el paso 1.
- Generar para el profesor un `.xlsx` con los mismos datos de su tabla. Limitar la descarga al rol profesor y evitar guardar una copia permanente del concentrado.
- **Pruebas de API e integración:** aislamiento entre estudiantes y grupos, coherencia entre tarjetas, resultados y concentrados, actualización visible tras cambiar una nota, equivalencia entre tabla del profesor y archivo `.xlsx`, y rechazo de descarga solicitada por un estudiante.

### 12. Ejecutar la validación integral y documentar la entrega

- Ejecutar toda la suite en un entorno limpio: pruebas unitarias, de integración con MySQL y de API. Completar un recorrido de extremo a extremo del backend desde la creación del profesor hasta las consultas, la descarga y la eliminación de un curso.
- Revisar autenticación y autorizaciones en cada ruta, manejo de errores, secretos fuera del repositorio, integridad transaccional y ausencia de datos cruzados entre cursos o usuarios.
- Documentar configuración, migraciones, comando inicial del profesor, ejecución de pruebas y resultados. Corregir fallos detectados y repetir únicamente las comprobaciones afectadas y las obligatorias de la suite.
- **Criterio de cierre:** cada regla de `backend.specs.md` tiene evidencia de verificación; la suite completa pasa y no quedan fallos conocidos en los flujos acordados.

## Pruebas del frontend relacionadas con este plan

La planificación detallada del frontend se hará por separado. Su implementación deberá incluir pruebas de funciones auxiliares con lógica propia, componentes y flujos de formularios, tablas y tarjetas, además de recorridos de extremo a extremo con esta API. Deben verificarse el cierre de sesión al vencer el JWT, la separación de permisos, los errores de validación, la confirmación de eliminación y la concordancia entre los datos mostrados y los entregados por el backend. El estudiante solo visualiza su concentrado; el profesor también puede descargarlo.
