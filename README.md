# Sistema de notas parciales

Aplicación de notas parciales con backend API en CodeIgniter 4, MySQL y frontend en React/Vite, Tailwind CSS y Motion. El trabajo y las decisiones funcionales se describen en `SPECS/`.

## Primer arranque

1. Copie `.env.example` a `.env` y sustituya **todas** las contraseñas y `JWT_SECRET` por valores locales aleatorios. `.env` está excluido de Git.
2. Levante los servicios desde la raíz: `docker compose up --build -d`. La primera ejecución instala las dependencias dentro de los contenedores.
3. Prepare el esquema y los datos iniciales: `docker compose exec backend php spark migrate` y `docker compose exec backend php spark db:seed InitialDataSeeder`.
4. Cree la única cuenta de profesor en una terminal interactiva: `docker compose exec -it backend php spark profesor:crear profesor@ucatolica.ac.cr`. El comando solicita la contraseña sin mostrarla.

El backend escucha en `http://localhost:8080`, la aplicación frontend en `http://localhost:5173` y Swagger UI en `http://localhost:8081`. Vite envía las solicitudes `/api` al backend. El inicio de sesión es `POST /api/login` con `correo` y `contrasena`; devuelve un JWT válido durante una hora. `GET /api/healt` es público y comprueba el servidor y MySQL (`200` disponible, `503` si falla la base). Las demás rutas de la API exigen el token Bearer y el rol correspondiente. El contrato OpenAPI y su verificación están en [API-DOCS](API-DOCS/README.md).

## Conectar DBeaver a MySQL desde Windows

En DBeaver, cree una conexión **MySQL** (tipo TCP/IP) con estos valores:

| Campo | Valor |
| --- | --- |
| Host | `127.0.0.1` |
| Puerto | `3307` por defecto; si se define `MYSQL_HOST_PORT` en `.env`, use ese valor |
| Base de datos | El valor `MYSQL_DATABASE` de `.env` (`snp` por defecto) |
| Usuario | El valor `MYSQL_USER` de `.env` (`snp` por defecto) |
| Contraseña | El valor `MYSQL_PASSWORD` de `.env` |

Después de `docker compose up -d db backend`, use **Probar conexión** en DBeaver. Si solicita un controlador MySQL, permita que DBeaver lo instale. La contraseña de MySQL no es la contraseña del profesor. El puerto está disponible solo en este equipo; `db_test` no se expone. Dentro de Docker, el backend continúa usando `db:3306`. Si `3307` ya está ocupado, agregue `MYSQL_HOST_PORT` con otro puerto libre en `.env` y vuelva a ejecutar `docker compose up -d db backend`.

## Pruebas

La base de pruebas usa un contenedor MySQL separado con almacenamiento temporal. No ejecute la suite contra la base de desarrollo.

1. `docker compose --profile test up -d --wait db_test`
2. `docker compose run --rm --no-deps -e DB_TARGET=test backend php spark migrate -g tests`
3. `docker compose run --rm --no-deps -e DB_TARGET=test backend vendor/bin/phpunit`

Las pruebas de integración limpian sus propios datos en `db_test`; repetir el tercer comando es seguro. Para detener solo la base de pruebas: `docker compose --profile test stop db_test`. El volumen de desarrollo se conserva; `db_test` se recrea vacío si se detiene su contenedor.

### Pruebas del frontend

Con el entorno de desarrollo levantado, ejecute dentro de Docker `docker compose exec frontend npm run test:run` para las pruebas unitarias y de componentes, y `docker compose exec frontend npm run build` para verificar la compilación.

Los recorridos de navegador usan una tercera base MySQL temporal (`db_e2e`), sin puerto publicado ni datos de desarrollo. Antes de cada ejecución, prepare una base nueva y el backend de pruebas con `docker compose --profile e2e up --force-recreate -d --wait db_e2e backend_e2e`. Luego ejecute `docker compose --profile e2e run --rm e2e`. Playwright arranca su propio servidor Vite dentro del contenedor de pruebas y visita la aplicación en computadora y celular. La cuenta y contraseña de este entorno son datos sintéticos de prueba; `E2EProfessorSeeder` se niega a ejecutarse fuera de `db_e2e`. Para detener los servicios temporales sin detener desarrollo: `docker compose --profile e2e stop backend_e2e db_e2e`.

## Código y decisiones

La aplicación del backend está completa en `Backend/`: rutas, filtros JWT, controladores, servicios, modelos y utilidades. `Backend/app/Database` contiene migraciones y datos iniciales. El concentrado se consulta en tiempo de ejecución; la descarga `.xlsx` corresponde al profesor. Consulte `SPECS/backend.specs.md`, `SPECS/database.specs.md` y `SPECS/backend.criteria.md` para reglas y límites.
