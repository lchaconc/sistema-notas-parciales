# Restricciones de trabajo del proyecto

## Estado actual del trabajo

- Trabaja únicamente en el análisis y la documentación de requisitos. No implementes funcionalidades, no escribas código y no instales dependencias hasta que el usuario autorice expresamente pasar a la implementación.
- Usa `SPECS/requerimientos-iniciales.docx` como fuente de los requisitos iniciales y `SPECS/general.md` como especificación general actualizada. Las decisiones posteriores del usuario recogidas en `SPECS/general.md` prevalecen sobre el documento inicial; solicita aclaración si aparece una contradicción que esas decisiones no resuelven.
- No inventes requisitos, reglas de negocio, flujos, límites, pantallas, contratos de API ni decisiones técnicas. Identifica cualquier ambigüedad, omisión o contradicción y pide el criterio del usuario antes de convertir una interpretación en requisito confirmado.
- Mantén visibles en la especificación las decisiones pendientes y actualízalas cuando el usuario proporcione su criterio.

## Stack obligatorio cuando se autorice la implementación

- Organiza la solución como monorepositorio y ejecútala mediante contenedores Docker.
- **Frontend:** carpeta independiente con Vite, React, Tailwind CSS y Motion. Usa JavaScript; no uses TypeScript. Consume la API del backend con `fetch` nativo.
- **Backend:** carpeta independiente con PHP, CodeIgniter y MySQL. Su única función de interfaz es ofrecer una API al frontend; no construyas vistas de servidor para el aplicativo.
- Almacena las contraseñas únicamente como hashes en la base de datos, tanto al generarlas como al cambiarlas. Nunca guardes la contraseña en texto claro.
- El único profesor debe tener una cuenta con correo y contraseña almacenada como hash. Crea esa cuenta una sola vez mediante un comando de consola de CodeIgniter; no generes cuentas de profesor adicionales.
- Protege las rutas de la API mediante JWT. La ruta de inicio de sesión es la excepción acordada: recibe correo y contraseña sin JWT previo y entrega un token después de autenticar al usuario. Solicita el criterio del usuario antes de añadir cualquier otra ruta pública.
- No agregues otros frameworks ni reemplaces el stack acordado sin autorización del usuario.

## Dependencias

- No instales librerías o módulos innecesarios. Antes de añadir una dependencia, justifica la necesidad concreta que no cubren el stack o las herramientas existentes.
- Cuando se autorice instalar dependencias, comprueba que sus versiones sean estables, estén actualizadas, tengan mantenimiento y sean aceptadas por la comunidad. Verifica su compatibilidad con el resto del stack y evita versiones con problemas de seguridad conocidos.

## Organización y responsabilidades

- Divide las funcionalidades en módulos con responsabilidades claras. Evita concentrar lógica de presentación, negocio y acceso a datos en un mismo archivo. Extrae código compartido a archivos `helpers` o `utils` cuando tenga una responsabilidad reutilizable.
- **Frontend:** separa vistas o páginas, componentes reutilizables, acceso a la API y `helpers`/`utils`. Mantén las llamadas con `fetch` fuera de los componentes de presentación cuando sea posible.
- **Backend:** separa rutas, controladores, servicios, modelos, middleware o filtros de CodeIgniter y `helpers`/`utils`, además de los archivos necesarios para cada módulo. Las rutas definen los puntos de entrada; los controladores atienden HTTP; los servicios contienen las reglas de negocio; los modelos gestionan el acceso a datos; los filtros atienden las responsabilidades transversales.
