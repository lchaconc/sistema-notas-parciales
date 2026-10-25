# Restricciones de trabajo del proyecto

## Estado actual del trabajo

- El usuario autorizó la implementación del backend, la base de datos y el frontend. Desarrolla el cliente en `frontend/` conforme a `SPECS/frontend.specs.md`, `SPECS/frontend.tasks.md` y `SPECS/frontend.criteria.md`, y verifica su integración con la API dentro de Docker.
- La eliminación individual de componentes de evaluación está implementada en el backend, el contrato de API y la interfaz del profesor. Mantén alineados estos tres niveles y sus pruebas al modificar esta funcionalidad.
- La captura de calificaciones del profesor usa una tabla editable con guardado individual por celda y advertencia por cambios pendientes. Mantén ese flujo alineado con las especificaciones, sus pruebas y la API existente; el esquema de la base de datos no cambia por esta interfaz.
- Al iniciar cada sesión de trabajo, localiza y lee todos los archivos `.md` propios del proyecto, incluidos los de la raíz y `SPECS/`, antes de planificar o modificar cualquier cosa. Comprueba también los cambios existentes en el repositorio para no sobrescribir trabajo previo.
- Trata esos archivos `.md` como la única fuente de verdad vigente para requisitos, diseño, tareas, criterios de aceptación y restricciones del proyecto. `SPECS/requerimientos-iniciales.docx` es un antecedente histórico ya incorporado a las especificaciones Markdown; no lo uses para sustituir o contradecir las decisiones documentadas en `.md`.
- Si dos archivos `.md` se contradicen o falta un criterio necesario, identifica la discrepancia y solicita el criterio del usuario antes de convertir una interpretación en requisito confirmado. Mantén coherentes los documentos relacionados cuando el usuario resuelva una decisión.
- No inventes requisitos, reglas de negocio, flujos, límites, pantallas, contratos de API ni decisiones técnicas. Identifica cualquier ambigüedad, omisión o contradicción y pide el criterio del usuario antes de convertir una interpretación en requisito confirmado.
- Mantén visibles en la especificación las decisiones pendientes y actualízalas cuando el usuario proporcione su criterio.

## Stack obligatorio para la implementación

- Organiza la solución como monorepositorio y trabaja todo el proyecto dentro de un entorno Docker: frontend, backend y MySQL deben poder levantarse y comunicarse mediante sus contenedores.
- Al iniciar la implementación, prepara y configura primero el entorno Docker hasta poder levantarlo y usarlo. Solo después desarrolla los módulos del backend y del frontend sobre ese entorno. Ejecuta allí los comandos del proyecto, las migraciones, el comando de creación del profesor y las pruebas; no dependas de instalaciones locales del stack para trabajar en el aplicativo.
- Documenta cómo levantar, detener y reconstruir el entorno, cómo acceder a frontend y API, y cómo ejecutar las pruebas dentro de Docker. Comprueba el arranque y la comunicación entre servicios antes de continuar con las funcionalidades.
- Para inspeccionar la base de desarrollo desde DBeaver en Windows, publica solo el puerto de MySQL de desarrollo en `127.0.0.1`, configurable mediante `MYSQL_HOST_PORT` (predeterminado `3307`). No publiques `db_test`; el backend sigue usando el host interno `db` y el puerto `3306`. Mantén las credenciales en `.env`, fuera de Git.
- **Frontend:** carpeta independiente con Vite, React, Tailwind CSS y Motion. Usa JavaScript; no uses TypeScript. Consume la API del backend con `fetch` nativo.
- **Backend:** toda la aplicación PHP/CodeIgniter reside en `Backend/` y usa MySQL. Su única función de interfaz es ofrecer una API al frontend; no construyas vistas de servidor para el aplicativo.
- **Documentación de API:** mantén `API-DOCS/openapi.yaml` alineado con todas las rutas de `Backend/app/Config/Routes.php`. Swagger UI corre en un contenedor independiente y no constituye implementación del cliente en `frontend/`.
- Almacena las contraseñas únicamente como hashes en la base de datos, tanto al generarlas como al cambiarlas. Nunca guardes la contraseña en texto claro.
- El único profesor debe tener una cuenta con correo y contraseña almacenada como hash. Crea esa cuenta una sola vez mediante un comando de consola de CodeIgniter; no generes cuentas de profesor adicionales.
- Protege las rutas de la API mediante JWT, salvo las dos rutas públicas acordadas: `POST /api/login`, que recibe correo y contraseña y entrega un token tras autenticar, y `GET /api/healt`, que comprueba la disponibilidad del servidor y la conexión a MySQL. Solicita el criterio del usuario antes de añadir cualquier otra ruta pública.
- No agregues otros frameworks ni reemplaces el stack acordado sin autorización del usuario.

## Dependencias

- No instales librerías o módulos innecesarios. Antes de añadir una dependencia, justifica la necesidad concreta que no cubren el stack o las herramientas existentes.
- Cuando se autorice instalar dependencias, comprueba que sus versiones sean estables, estén actualizadas, tengan mantenimiento y sean aceptadas por la comunidad. Verifica su compatibilidad con el resto del stack y evita versiones con problemas de seguridad conocidos.

## Variables de entorno y secretos

- Cuando se autorice la implementación, usa variables de entorno para la configuración que cambie entre entornos y para los secretos, como las credenciales de la base de datos y la clave de firma de JWT. No incluyas valores secretos en el código ni en archivos versionados.
- Mantén los archivos de entorno locales fuera de Git mediante el `.gitignore` del repositorio, también si se crean dentro de las carpetas de frontend o backend. Antes de publicar cambios, comprueba que ningún archivo con secretos esté preparado para commit.
- Si se necesita documentar la configuración, usa archivos de ejemplo sin credenciales reales. No expongas secretos del backend en el frontend.

## Organización y responsabilidades

- Divide las funcionalidades en módulos con responsabilidades claras. Evita concentrar lógica de presentación, negocio y acceso a datos en un mismo archivo. Extrae código compartido a archivos `helpers` o `utils` cuando tenga una responsabilidad reutilizable.
- **Frontend:** separa vistas o páginas, componentes reutilizables, acceso a la API y `helpers`/`utils`. Mantén las llamadas con `fetch` fuera de los componentes de presentación cuando sea posible.
- **Backend:** separa rutas, controladores, servicios, modelos, middleware o filtros de CodeIgniter y `helpers`/`utils`, además de los archivos necesarios para cada módulo. Las rutas definen los puntos de entrada; los controladores atienden HTTP; los servicios contienen las reglas de negocio; los modelos gestionan el acceso a datos; los filtros atienden las responsabilidades transversales.
