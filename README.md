# BeneHom

[![CI](https://github.com/hiram9112/benehom/actions/workflows/ci.yml/badge.svg)](https://github.com/hiram9112/benehom/actions/workflows/ci.yml)

BeneHom es una aplicación web de gestión financiera doméstica. Centraliza ingresos, gastos, ahorro y proyecciones para ayudar a entender la economía del hogar con datos mensuales claros.

**Producción:** [https://benehom.es](https://benehom.es)

## Qué es BeneHom

El núcleo del producto es un panel privado en el que cada persona gestiona sus movimientos y revisa la evolución de sus finanzas. La aplicación diferencia los gastos esenciales de los flexibles y calcula el ahorro posible y el ahorro real a partir de los datos registrados.

Sus capacidades principales son:

- gestión mensual de ingresos y gastos, con edición, eliminación e importación del mes anterior;
- resumen financiero y visualizaciones de evolución, distribución y hábitos de gasto;
- metas de ahorro y simulación de reducción de gastos flexibles;
- escenarios de inversión con interés compuesto, proyecciones de inflación y cálculo hipotecario;
- registro, verificación de email, recuperación de contraseña, exportación de datos y eliminación de cuenta;
- blog público de educación financiera y una interfaz responsive para escritorio y móvil.

El proyecto está desplegado en producción y evoluciona mediante un flujo de integración y entrega continua basado en GitHub Actions.

## Numa

Numa es la guía inteligente integrada en BeneHom. En las páginas públicas responde dudas sobre el producto y contenidos de educación financiera documentados por BeneHom. Con una sesión autenticada también puede interpretar los ingresos, gastos y movimientos del usuario, explicar diferencias y tendencias y mantener contexto durante la sesión.

El subsistema combina varias piezas:

- **IA generativa:** Gemini clasifica la consulta y redacta la respuesta dentro de un ámbito restringido.
- **RAG y embeddings:** la documentación funcional y los artículos públicos se fragmentan e indexan en `numa_conocimiento`; la recuperación usa embeddings de Gemini y similitud coseno para aportar contexto relevante.
- **Function Calling:** las consultas financieras privadas usan una única herramienta de solo lectura, `consultar_datos_financieros`. PHP valida sus argumentos, toma el identificador de usuario de la sesión y devuelve únicamente hechos financieros autorizados. El modo público no recibe herramientas financieras.
- **Controles de aplicación:** feature flags, límites por usuario o visitante, presupuestos globales de llamadas y tokens, rate limiting, límites de tamaño y tiempo, validación estricta de herramientas y errores seguros.

Numa no es un asistente generalista, no modifica datos y no ofrece asesoramiento financiero personalizado. Su análisis privado actual se limita a ingresos, gastos y movimientos; no analiza datos privados de metas, inversión, inflación o hipotecas. El transcript vive en la sesión PHP y el logging técnico evita registrar mensajes, respuestas o resultados financieros.

La operación, privacidad, indexación y evaluación de este subsistema se documentan en [`resources/numa/`](resources/numa/).

## Arquitectura y tecnologías

BeneHom mantiene una arquitectura deliberadamente directa, sin framework PHP generalista:

| Área | Implementación |
| --- | --- |
| Entrada y routing | `public/index.php` carga el entorno, aplica controles globales y despacha únicamente las acciones registradas en `config/routes.php`. Las rutas internas usan `?r=controlador/accion`; Apache expone además aliases legibles mediante `public/.htaccess`. |
| Backend | PHP con controladores, modelos PDO, servicios y vistas renderizadas en servidor. Las clases se conectan mediante `require_once` explícitos. |
| Datos | MySQL/MariaDB. `database/schema.sql` es el esquema canónico para instalaciones nuevas e incluye datos financieros, autenticación, límites de acceso, consumo de Numa e índice vectorial. |
| Frontend | HTML y CSS propios, JavaScript sin framework, Fetch API y componentes apoyados en Bootstrap. Chart.js y Flatpickr se usan en el dashboard; GSAP y Lenis aportan interacción y movimiento con degradación controlada. |
| Integraciones | PHPMailer para correo transaccional y Gemini API para generación y embeddings. |
| Build | Composer gestiona PHP y la minificación CSS; npm copia versiones bloqueadas de GSAP y Lenis a los assets públicos. |

`public/` es siempre el DocumentRoot. El código de aplicación, la configuración, el esquema, las dependencias y los secretos permanecen fuera de la raíz pública.

## Testing y calidad

La estrategia de validación combina pruebas de lógica, base de datos, despliegue y navegador:

- **PHPUnit:** `composer test` ejecuta las suites Unit, Integration y Deployment. Cubren cálculos, routing y controladores, autenticación, aislamiento por usuario, agregaciones, Numa, RAG y el comportamiento de activación y rollback de releases.
- **Integración:** usa una base MySQL aislada llamada `benehom_test`. La clase base crea las tablas ausentes desde `database/schema.sql` y encapsula sus pruebas en transacciones; las pruebas de concurrencia que necesitan conexiones independientes realizan su propia limpieza.
- **Proveedores externos:** en `APP_ENV=testing`, generación y embeddings reales están bloqueados. Las pruebas usan fakes o transportes inyectados, por lo que la suite automatizada no consume Gemini.
- **Navegador:** Playwright levanta `public/` con un servidor PHP temporal y usa `benehom_test` para los recorridos autenticados. Valida interacción, responsive, accesibilidad, degradación y distintos resultados controlados de Numa. Esta suite se ejecuta por separado y actualmente no forma parte del workflow de CI.
- **Análisis y consistencia:** PHPStan analiza `app`, `public` y `config`; `composer lint:design` controla deriva de tokens y convenciones visuales; el pipeline también comprueba que los assets generados coincidan con el repositorio.

Comandos habituales:

```bash
composer lint:design
composer test
vendor/bin/phpstan analyse
npm run test:e2e
```

Las pruebas de integración y Playwright nunca deben apuntar a datos de aplicación. Para credenciales locales distintas, crea un `phpunit.xml` ignorado por Git a partir de `phpunit.xml.dist`, conserva `DB_NAME=benehom_test` y ajusta host, puerto y credenciales.

Las evaluaciones con Gemini real son manuales, están fuera de PHPUnit y CI, requieren confirmación explícita y pueden consumir cuota. Sus procedimientos y resultados se mantienen en la [documentación de Numa](resources/numa/runbook.md).

## CI/CD y despliegue

El workflow [`ci.yml`](.github/workflows/ci.yml) reproduce el entorno de referencia con PHP 8.3, Node 20 y MariaDB. En cada cambio aplicable instala dependencias, construye los assets, ejecuta el lint de diseño, PHPUnit y PHPStan, y rechaza diferencias en archivos generados.

Los pushes a `main` que superan esos controles activan el despliegue de producción:

1. `composer release:build` construye desde el commit un artefacto con allowlist, dependencias PHP sin paquetes de desarrollo, `RELEASE_SHA` y assets ya generados.
2. Se genera y valida un checksum SHA-256 antes y después de transferir el artefacto por SSH/SCP con verificación estricta del host.
3. El servidor prepara un directorio de release versionado por SHA y enlaza la configuración persistente, mantenida fuera de la release y del DocumentRoot.
4. `public_html` se cambia de forma atómica al `public/` de la nueva release mediante symlink.
5. Se ejecutan smoke tests HTTPS sobre la home, el blog y el CSS publicado. Si fallan, el script restaura el symlink anterior y valida la release recuperada.

El rollback automático cubre código y assets. Los backups, los cambios de esquema y la indexación RAG quedan fuera del pipeline y requieren operación explícita; no existe un runner de migraciones. Los registros técnicos y la plantilla operativa están en [`resources/deployments/`](resources/deployments/).

## Seguridad

La aplicación incorpora controles en varias capas, sin asumir que sustituyen la revisión y operación seguras:

- registro central de rutas con métodos permitidos, tipo de respuesta y separación entre acciones públicas y privadas;
- validación CSRF global en POST, con validación equivalente dentro de los endpoints JSON de Numa que gestionan su propio flujo;
- consultas preparadas con PDO y filtrado por `usuario_id` en lecturas y mutaciones de datos privados;
- contraseñas con hash, verificación de email y tokens aleatorios de recuperación/verificación almacenados como hash y con caducidad;
- rate limiting persistente para login, recuperación y reenvío de verificación;
- regeneración del identificador al iniciar sesión, cierre por inactividad y cookies `HttpOnly`, `SameSite=Lax` y `Secure` bajo HTTPS;
- Content Security Policy con nonce, HSTS en producción HTTPS, protección frente a framing y políticas restrictivas de referrer y permisos;
- escape de salida, validación de entrada y respuestas diferenciadas para HTML y JSON;
- secretos en `.env`, fuera de `public/` y excluidos del artefacto; las releases enlazan una configuración persistente separada;
- controles específicos de Numa para ámbito, identidad seudonimizada en el acceso público, aislamiento de herramientas por sesión, cuotas, límites de payload y logging sin contenido conversacional.

La configuración correcta sigue siendo parte del modelo de seguridad: producción debe servir exclusivamente `public/`, usar HTTPS y mantener el fichero de entorno con permisos adecuados.

## Ejecución local

### Requisitos

- PHP 8.3 recomendado para reproducir CI, con `pdo_mysql` y OpenSSL. El toolchain bloqueado de desarrollo requiere PHP 8.2 o posterior.
- MySQL o MariaDB.
- Composer.
- Node.js 20 y npm para construir assets y ejecutar Playwright.

### Instalación

```bash
git clone https://github.com/hiram9112/benehom.git
cd benehom

npm ci
npm run build
composer install --no-interaction --prefer-dist
composer build:css

cp .env.example .env
```

Edita `.env` con la conexión local y una `APP_URL` coherente. Numa permanece desactivada por defecto; no necesita credenciales de Gemini para revisar el resto de la aplicación. SMTP solo es necesario para probar los correos de verificación y recuperación.

Crea la base e importa el esquema:

```bash
mysql -u root -p -e "CREATE DATABASE benehom CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p benehom < database/schema.sql
```

Opcionalmente, `database/seed.sql` añade un usuario y movimientos de demostración:

```bash
mysql -u root -p benehom < database/seed.sql
```

Para una revisión rápida puede usarse el servidor integrado de PHP:

```bash
php -S 127.0.0.1:8080 -t public
```

La aplicación quedará disponible en `http://127.0.0.1:8080/index.php?r=home/index`. En Apache o Nginx, configura igualmente `public/` como DocumentRoot; los aliases sin `index.php?r=...` dependen de las reglas del servidor web.

## Estructura del proyecto

```text
app/
  controllers/        Acciones HTTP registradas
  helpers/            Routing, seguridad y utilidades compartidas
  models/             Acceso PDO y persistencia
  services/           Cálculos, importación y subsistema Numa
  views/              Vistas PHP y parciales
bin/                  Indexación y evaluaciones manuales de Numa
config/               Rutas, base de datos y catálogo del blog
database/             Esquema canónico y seed opcional
knowledge/numa/       Corpus funcional versionado para RAG
public/               DocumentRoot, front controller y assets
  css/src/            Fuentes CSS
  js/                 JavaScript de la aplicación y vendors generados
resources/
  deployments/        Registros y plantilla de despliegue
  numa/               Runbook, privacidad, prompt y evaluaciones
scripts/              Build, controles de diseño y releases
tests/
  Unit/               Lógica, helpers, routing y contratos
  Integration/        Persistencia y flujos con MySQL
  deployment/         Activación, smoke tests y rollback
  browser/            Pruebas Playwright
.github/workflows/    CI y despliegue automatizado
```

## Documentación adicional

- [Runbook operativo de Numa](resources/numa/runbook.md)
- [Privacidad operativa de Gemini](resources/numa/privacidad-operativa.md)
- [Resultados de evaluación RAG](resources/numa/evaluacion-rag-resultados.md)
- [Evidencia de validación de Numa](resources/numa/cierre.md)
- [Registros de despliegue](resources/deployments/)
- [Esquema de base de datos](database/schema.sql)

## Licencia

Este repositorio se publica únicamente como portfolio y para revisión. No es software open source: el código, el diseño, los textos y los materiales de BeneHom están bajo derechos reservados.

Consulta [`LICENSE`](LICENSE) para conocer los términos completos.
