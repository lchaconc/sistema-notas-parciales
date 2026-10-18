# Documentación OpenAPI

El contrato de las 27 operaciones HTTP implementadas en `Backend/app/Config/Routes.php` está en [openapi.yaml](openapi.yaml). Usa OpenAPI 3.0.4 y se visualiza con Swagger UI en `http://localhost:8081` al ejecutar `docker compose up -d api_docs` desde la raíz del repositorio. `docker compose up --build -d` también lo levanta.

Swagger UI expone la especificación en `http://localhost:8081/openapi.yaml` y envía las pruebas de `/api` al backend mediante un proxy del mismo origen. Para probar operaciones protegidas, inicie sesión con `POST /login`, copie el campo `data.token` y péguelo en **Authorize** sin anteponer `Bearer`. El JWT dura una hora. La cuenta inicial del profesor se crea por consola según el [README principal](../README.md).

Comprobar el contrato desde Docker:

```sh
docker compose run --rm --no-deps -v ./API-DOCS:/docs -w /docs frontend npx --yes @redocly/cli@2.64.1 lint openapi.yaml
docker compose run --rm --no-deps -v ./:/workspace:ro -w /workspace/API-DOCS frontend node check-routes.mjs
```

La primera comprobación valida el OpenAPI. La segunda detecta operaciones del backend sin documentar y operaciones documentadas sin ruta. Swagger UI se limita a documentar y probar la API; no añade rutas públicas a CodeIgniter ni implementa el cliente en `frontend/`.
