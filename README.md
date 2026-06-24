# BeneHom

BeneHom es una aplicación web para gestionar la economía familiar. Permite registrar ingresos y gastos, diferenciar gastos esenciales y flexibles, calcular el ahorro real del hogar, definir metas de ahorro y proyectar escenarios financieros de forma clara.

Aplicación en producción: https://benehom.es

## Características

- Registro de ingresos mensuales.
- Registro de gastos esenciales y gastos flexibles.
- Cálculo automático de ahorro posible y ahorro real.
- Panel con resumen financiero mensual.
- Gráficos de evolución de gastos y ahorro.
- Metas de ahorro con proyección de aportaciones.
- Proyección de reducción de gastos flexibles.
- Calculadora de inflación.
- Escenarios de inversión con interés compuesto.
- Calculadora de hipoteca.
- Blog educativo sobre finanzas del hogar.
- Autenticación de usuarios y recuperación de contraseña.
- Diseño responsive para escritorio y móvil.

## Tecnologías

### Backend

- PHP 8
- MySQL
- PDO
- Arquitectura MVC personalizada
- Sesiones PHP
- Protección CSRF
- Variables de entorno mediante `.env`

### Frontend

- HTML
- CSS personalizado
- Bootstrap 5
- JavaScript
- Fetch API
- Chart.js
- Flatpickr

### Herramientas de desarrollo

- Composer
- PHPStan
- PHPUnit

## Requisitos

- PHP 8.0 o superior
- MySQL o MariaDB
- Composer
- Servidor web con soporte PHP, por ejemplo Apache, Nginx, XAMPP o MAMP

## Instalación

Clona el repositorio:

```bash
git clone https://github.com/hiram9112/benehom.git
cd benehom
```

Instala las dependencias de Composer:

```bash
composer install
```

Crea el archivo de entorno:

```bash
cp .env.example .env
```

Configura las variables de entorno:

```env
DB_HOST=localhost
DB_PORT=3306
DB_NAME=benehom
DB_USER=usuario
DB_PASS=contraseña

APP_ENV=local
APP_URL=http://localhost/benehom/public/

SMTP_USER=user_smtp
SMTP_PASS=contraseña_smtp
```

Crea la base de datos e importa la estructura:

```bash
mysql -u usuario -p benehom < database/schema.sql
```

Opcionalmente, importa datos de prueba:

```bash
mysql -u usuario -p benehom < database/seed.sql
```

Usuario demo incluido en `database/seed.sql`:

- Email: `demo@benehom.local`
- Contraseña: `Demo1234`

## Ejecución local

Configura el servidor web para servir la aplicación desde el directorio `public/`.

La aplicación usa rutas mediante el parámetro `r`, por ejemplo:

```text
http://localhost/benehom/public/index.php?r=home/index
```

Si usas un entorno como XAMPP o MAMP, asegúrate de que el proyecto tenga acceso a PHP, MySQL y al archivo `.env`.

## Estructura del proyecto

```text
app/
  controllers/
  helpers/
  models/
  views/
config/
database/
public/
  css/
  img/
  js/
```

## Seguridad

BeneHom incluye medidas básicas de seguridad para una aplicación web con autenticación:

- Consultas preparadas con PDO.
- Protección CSRF en formularios POST.
- Hash seguro de contraseñas.
- Tokens seguros para recuperación de contraseña.
- Cookies de sesión con `HttpOnly` y `SameSite=Lax`.
- Separación de credenciales mediante variables de entorno.
- Control de acceso para rutas privadas.
- Sanitización de salida HTML en puntos relevantes.

## Análisis estático

El proyecto incluye PHPStan como dependencia de desarrollo. Para ejecutarlo:

```bash
vendor/bin/phpstan analyse app public config
```

## Testing

El proyecto incluye una suite de PHPUnit ejecutable con `composer test`. Los tests de integración usan una base de datos aislada llamada `benehom_test`; no deben ejecutarse contra la base de datos real.

Prepara la base de datos de test:

```sql
CREATE DATABASE benehom_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'benehom_test_user'@'localhost' IDENTIFIED BY 'test_password_123';
GRANT ALL PRIVILEGES ON benehom_test.* TO 'benehom_test_user'@'localhost';
FLUSH PRIVILEGES;
```

Si necesitas credenciales distintas en local, copia la configuración distribuida y ajusta los valores:

```bash
cp phpunit.xml.dist phpunit.xml
```

`phpunit.xml` fuerza `APP_ENV=testing` y `DB_NAME=benehom_test`. La clase base de integración carga `database/schema.sql` si el esquema no existe y cada test se ejecuta dentro de una transacción con `rollBack`, dejando la base limpia entre pruebas.

Ejecuta la suite completa:

```bash
composer test
```

Ejecuta también el análisis estático antes de cerrar cambios:

```bash
vendor/bin/phpstan analyse app public config
```

Cobertura actual de tests:

- Cálculos financieros puros: hipoteca, interés compuesto, inflación, fechas objetivo, normalización de cantidades y protección frente a resultados no fiables.
- Helpers críticos: whitelists de categorías, formato de categorías/cantidades y CSRF.
- Integración con base de datos: registro de usuarios, hashes de contraseña, emails duplicados, recuperación de contraseña, agregaciones de gastos y aislamiento por usuario.

Queda fuera de esta fase:

- Tests HTTP de controladores que dependen de `echo`, `header` y `exit`.
- Tests e2e de interfaz, Chart.js y comportamiento visual.
- Automatización en GitHub Actions, diferible a un sprint de CI/CD.

## Variables de entorno

| Variable | Descripción |
| --- | --- |
| `DB_HOST` | Host de la base de datos |
| `DB_PORT` | Puerto de la base de datos |
| `DB_NAME` | Nombre de la base de datos |
| `DB_USER` | Usuario de la base de datos |
| `DB_PASS` | Contraseña de la base de datos |
| `APP_ENV` | Entorno de ejecución: `local` o `production` |
| `APP_URL` | URL base de la aplicación |
| `SMTP_USER` | Usuario SMTP para recuperación de contraseña |
| `SMTP_PASS` | Contraseña SMTP |

## Contribución

Las contribuciones deben mantener la estructura MVC actual, validar datos tanto en cliente como en servidor y preservar las medidas de seguridad existentes.

Antes de proponer cambios, revisa que la aplicación siga funcionando en entorno local y que no se expongan credenciales ni datos sensibles.

## Licencia

Este repositorio no incluye actualmente un archivo de licencia. Hasta que se añada una licencia explícita, el código queda bajo derechos reservados del autor.
