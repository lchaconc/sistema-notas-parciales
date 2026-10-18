# Sistema de notas parciales

Backend API en CodeIgniter 4, MySQL y plantilla base de frontend en React/Vite, Tailwind CSS y Motion. El trabajo y las decisiones funcionales se describen en `SPECS/`. El cliente todavía no tiene funcionalidades; su desarrollo espera la indicación del usuario.

## Primer arranque

1. Copie `.env.example` a `.env` y sustituya **todas** las contraseñas y `JWT_SECRET` por valores locales aleatorios. `.env` está excluido de Git.
2. Levante los servicios desde la raíz: `docker compose up --build -d`. La primera ejecución instala las dependencias dentro de los contenedores.
3. Prepare el esquema y los datos iniciales: `docker compose exec backend php spark migrate` y `docker compose exec backend php spark db:seed InitialDataSeeder`.
4. Cree la única cuenta de profesor en una terminal interactiva: `docker compose exec -it backend php spark profesor:crear profesor@ucatolica.ac.cr`. El comando solicita la contraseña sin mostrarla.

El backend escucha en `http://localhost:8080`, la plantilla del frontend en `http://localhost:5173` y Swagger UI en `http://localhost:8081`. Vite envía las solicitudes `/api` al backend. El inicio de sesión es `POST /api/login` con `correo` y `contrasena`; devuelve un JWT válido durante una hora. Las demás rutas de la API exigen el token Bearer y el rol correspondiente. El contrato OpenAPI y su verificación están en [API-DOCS](API-DOCS/README.md).

## Pruebas

La base de pruebas usa un contenedor MySQL separado con almacenamiento temporal. No ejecute la suite contra la base de desarrollo.

1. `docker compose --profile test up -d --wait db_test`
2. `docker compose run --rm --no-deps -e DB_TARGET=test backend php spark migrate -g tests`
3. `docker compose run --rm --no-deps -e DB_TARGET=test backend vendor/bin/phpunit`

Las pruebas de integración limpian sus propios datos en `db_test`; repetir el tercer comando es seguro. Para detener el entorno: `docker compose --profile test down`. El volumen de desarrollo se conserva; `db_test` se recrea vacío si se detiene su contenedor.

## Código y decisiones

La aplicación del backend está completa en `Backend/`: rutas, filtros JWT, controladores, servicios, modelos y utilidades. `Backend/app/Database` contiene migraciones y datos iniciales. El concentrado se consulta en tiempo de ejecución; la descarga `.xlsx` corresponde al profesor. Consulte `SPECS/backend.specs.md`, `SPECS/database.specs.md` y `SPECS/backend.criteria.md` para reglas y límites.
