# Auditoria de seguridad

## Alcance

Este documento cierra el Sprint 19: seguridad y vulnerabilidades. Cubre autenticacion y sesiones, control de acceso por propietario, inyeccion, XSS, CSRF, cabeceras HTTP, CSP, rate limiting, verificacion de email, secretos y exposicion de directorios privados.

## Resumen ejecutivo

| Area | Estado previo | Correccion aplicada | Estado |
| --- | --- | --- | --- |
| Control de acceso / IDOR | Edicion y borrado de gastos e ingresos operaban por `id` sin filtrar propietario | `Gasto::eliminarGasto`, `Gasto::actualizarGasto`, `Ingreso::eliminarIngreso` e `Ingreso::actualizarIngreso` reciben `usuario_id` y filtran `WHERE id = :id AND usuario_id = :usuario_id` | OK |
| Autenticacion | Login con bcrypt y regeneracion de sesion, pero sin bloqueo de cuentas no verificadas | Registro genera token hasheado de verificacion; login rechaza cuentas con `email_verificado_en IS NULL` | OK |
| Rate limiting | Login y reset no tenian bloqueo temporal persistente | Tabla `intentos_acceso` y modelo `IntentoAcceso` para login, reset y reenvio de verificacion | OK |
| Sesiones | Cookie endurecida, sin timeout explicito por inactividad | `last_activity` y expiracion por `SESSION_IDLE_TIMEOUT` en `public/index.php` | OK |
| Cabeceras HTTP | Ausentes o no centralizadas | `bh_security_headers()` emite cabeceras defensivas y CSP en el front controller | OK |
| Secretos | `.env` ignorado, permisos locales demasiado amplios antes de la fase E | `.env` reducido localmente y politica de produccion documentada | OK con nota local |
| Pruebas | Suite Sprint 17 sin cobertura especifica de verificacion de email | Tests de IDOR y verificacion de email integrados en `tests/Integration` | OK |

## Autenticacion y sesiones

| Control | Estado | Evidencia |
| --- | --- | --- |
| Hash de passwords | OK | `Usuario::registrar()` usa `password_hash(..., PASSWORD_BCRYPT)` y `AuthController::login()` valida con `password_verify()` |
| Regeneracion de sesion al login | OK | `session_regenerate_id(true)` antes de guardar `usuario_id` |
| Cookie de sesion | OK | `session_set_cookie_params()` usa `httponly`, `samesite=Lax` y `secure` dinamico si hay HTTPS |
| Timeout por inactividad | OK | `public/index.php` invalida la sesion si `last_activity` supera `SESSION_IDLE_TIMEOUT` |
| Login sin email verificado | OK | `AuthController::login()` bloquea el acceso si `email_verificado_en` esta vacio |

La verificacion de email se implementa con tokens aleatorios (`random_bytes`), almacenamiento exclusivo del hash SHA-256, expiracion de 30 minutos y limpieza del token al marcar la cuenta como verificada.

## Rate limiting

| Accion | Clave | Ventana / bloqueo | Estado |
| --- | --- | --- | --- |
| Login | Hash del email normalizado | 5 intentos, 900 s | OK |
| Reset de password | Hash del email normalizado | 3 solicitudes, 3600 s | OK |
| Reenvio de verificacion | Hash del email normalizado | 3 solicitudes, 3600 s | OK |

La tabla `intentos_acceso` mantiene contador, primer intento, ultimo intento y `bloqueado_hasta`. Los mensajes de reset y reenvio son neutros para evitar enumeracion de usuarios.

## Control de acceso / IDOR

| Endpoint / modelo | Riesgo previo | Correccion | Estado |
| --- | --- | --- | --- |
| `Gasto::eliminarGasto()` | Borrado por `id` ajeno | `DELETE FROM gastos WHERE id = :id AND usuario_id = :usuario_id` y `rowCount()` | OK |
| `Gasto::actualizarGasto()` | Edicion por `id` ajeno | `UPDATE gastos SET ... WHERE id = :id AND usuario_id = :usuario_id` y `rowCount()` | OK |
| `Ingreso::eliminarIngreso()` | Borrado por `id` ajeno | `DELETE FROM ingresos WHERE id = :id AND usuario_id = :usuario_id` y `rowCount()` | OK |
| `Ingreso::actualizarIngreso()` | Edicion por `id` ajeno | `UPDATE ingresos SET ... WHERE id = :id AND usuario_id = :usuario_id` y `rowCount()` | OK |
| Proyecciones / metas | Debia confirmar scoping | Mantienen el patron `obtenerPorIdYUsuario` / operaciones por usuario | OK |

Los controladores AJAX pasan siempre `$_SESSION['usuario_id']` y devuelven mensajes genericos de no encontrado o sin permiso, sin revelar si el `id` existe en otra cuenta.

## Inyeccion

| Control | Estado | Evidencia |
| --- | --- | --- |
| SQL con parametros | OK | Modelos revisados usan PDO con prepared statements y binds |
| IDs de cliente | OK | IDs convertidos a enteros y operaciones sensibles filtradas por propietario |
| Categorias | OK | Whitelist de categorias de gastos e ingresos antes de insertar |

No se han detectado consultas sensibles interpolando entrada del usuario sin parametros en el alcance del Sprint 19.

## XSS

| Control | Estado | Evidencia |
| --- | --- | --- |
| Escape en vistas | OK | Uso generalizado de `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')` en salidas variables |
| JSON-LD | OK | `bh_render_json_ld()` usa flags `JSON_HEX_*` y nonce CSP |
| Scripts inline | OK | Scripts inline permitidos por nonce, no por `unsafe-inline` en `script-src` |

La CSP reduce el impacto de inyecciones de script al exigir nonce para scripts inline propios.

## CSRF

| Control | Estado | Evidencia |
| --- | --- | --- |
| Token global | OK | `csrf_token()`, `csrf_field()` y `csrf_validate()` |
| Comparacion segura | OK | `hash_equals()` |
| Validacion centralizada | OK | `public/index.php` valida solicitudes POST antes del dispatch |

## Cabeceras, CSP y SRI

| Cabecera / control | Estado | Evidencia |
| --- | --- | --- |
| `X-Content-Type-Options` | OK | `nosniff` en `bh_security_headers()` |
| `X-Frame-Options` | OK | `DENY` |
| `Referrer-Policy` | OK | `strict-origin-when-cross-origin` |
| `Permissions-Policy` | OK | Camara, microfono, geolocalizacion, pago y USB deshabilitados |
| `Content-Security-Policy` | OK | `default-src 'self'`, `frame-ancestors 'none'`, `object-src 'none'`, CDNs reales y nonce |
| HSTS | OK | Solo en `APP_ENV=production` y peticiones HTTPS |
| SRI CDN | OK | Bootstrap, Bootstrap Icons, Chart.js y Flatpickr declaran `integrity` y `crossorigin` |

La CSP permite los origenes usados por la aplicacion: recursos propios, `cdn.jsdelivr.net`, Google Fonts y `data:` para imagenes/fuentes. Los scripts inline propios se emiten con `bh_nonce_attr()`.

## Secretos y configuracion

| Control | Estado | Evidencia |
| --- | --- | --- |
| `.env` ignorado por Git | OK | `.gitignore` contiene `.env` |
| `.env` no trazado | OK | `git ls-files -- .env .env.example` devuelve solo `.env.example` |
| `.env` sin historial Git | OK | `git log --all --full-history --oneline -- .env` no devuelve commits |
| Permisos locales | OK con excepcion documentada | `.env` queda como `640 hiram9112:www-data .env` |

En local no se aplica `chmod 600` estricto porque obligaria a cambiar el propietario a `www-data` o podria dificultar la edicion del archivo durante el desarrollo. La configuracion local adoptada mantiene el propietario `hiram9112`, usa el grupo `www-data` para compatibilidad con Apache/PHP y reduce permisos de `664` a `640`.

En produccion, el `.env` no debe estar dentro de la raiz publica. Debe tener permisos estrictos, preferiblemente `600`, y pertenecer al usuario efectivo que ejecuta PHP en ese entorno. En Hostinger, esta revision debe hacerse desde SSH, SFTP o el administrador de archivos, sin asumir que los permisos locales se trasladan automaticamente al hosting.

## Raiz publica y DocumentRoot

| Entorno | Estado | Evidencia |
| --- | --- | --- |
| Local `benehom.local` | OK | VirtualHost Apache con `DocumentRoot /var/www/html/benehom/public` |
| Produccion Hostinger | Politica documentada | `public_html` debe contener el contenido de `public/`; `app/`, `config/`, `database/`, `vendor/` y `.env` quedan fuera |
| Defensa por acceso accidental a `/benehom/` | OK | `.htaccess` en la raiz del repo bloquea `.env`, `app/`, `config/`, `database/` y `vendor/` |

El VirtualHost local activo es `benehom.local` y sirve directamente desde `public/`. Se mantiene una defensa adicional en la raiz del repositorio para el caso de que el DocumentRoot global de Apache siga exponiendo `/var/www/html` y alguien acceda al proyecto como subdirectorio.

## Verificacion HTTP documentada

Verificacion contra la arquitectura correcta (`http://benehom.local/`):

| URL | Resultado |
| --- | --- |
| `http://benehom.local/` | `200 OK` |
| `http://benehom.local/.env` | `403 Forbidden` |
| `http://benehom.local/app/helpers/utils.php` | `404 Not Found` |
| `http://benehom.local/config/database.php` | `404 Not Found` |
| `http://benehom.local/database/schema.sql` | `404 Not Found` |
| `http://benehom.local/vendor/autoload.php` | `404 Not Found` |

Verificacion defensiva contra el acceso heredado como subdirectorio (`http://127.0.0.1/benehom/`):

| URL | Resultado |
| --- | --- |
| `http://127.0.0.1/benehom/.env` | `403 Forbidden` |
| `http://127.0.0.1/benehom/app/helpers/utils.php` | `403 Forbidden` |
| `http://127.0.0.1/benehom/config/database.php` | `403 Forbidden` |
| `http://127.0.0.1/benehom/database/schema.sql` | `403 Forbidden` |
| `http://127.0.0.1/benehom/vendor/autoload.php` | `403 Forbidden` |

## Configuracion Apache local

El sitio local queda registrado como:

```apache
<VirtualHost *:80>
    ServerName benehom.local
    ServerAlias www.benehom.local
    DocumentRoot /var/www/html/benehom/public

    <Directory /var/www/html/benehom/public>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

`apache2ctl -S` muestra `benehom.local` como NameVirtualHost en puerto 80 y Apache valida la configuracion con `Syntax OK`.

## Pruebas automatizadas

| Suite | Cobertura relevante |
| --- | --- |
| `tests/Integration/OwnershipScopingTest.php` | Regresion IDOR: un usuario no puede editar ni eliminar gastos/ingresos ajenos; el propietario si puede |
| `tests/Integration/EmailVerificationTest.php` | Token valido, token expirado, limpieza al verificar y bloqueo de login sin `email_verificado_en` |
| `tests/Integration/IntentoAccesoTest.php` | Bloqueo al alcanzar maximo, limpieza y reinicio de ventana |
| `tests/Integration/PasswordResetTest.php` | Tokens de reset validos, expirados y limpieza |
| `tests/Unit/CsrfTest.php` | Generacion y validacion CSRF |

## Verificacion de fase F

| Verificacion | Resultado | Evidencia |
| --- | --- | --- |
| Suite automatizada | OK | `composer test` -> 51 tests, 298 assertions |
| Diff limpio | OK | `git diff --check` sin salida |
| Sintaxis PHP | OK | `php -l app/controllers/AuthController.php` y `php -l tests/Integration/EmailVerificationTest.php` sin errores |
| Cabeceras HTTP | OK | `curl -I -s http://benehom.local/` incluye `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy` y `Content-Security-Policy` |
| HSTS local HTTP | OK | No se emite `Strict-Transport-Security` en `http://benehom.local/` |
| IDOR manual en AJAX | OK | Con `usuario_id` atacante, editar/eliminar gasto e ingreso ajenos devolvio `ok:false` y los movimientos del propietario conservaron `200.00`/`100.00` y `2000.00`/`1000.00` |
| CSP en navegador | OK | Sin errores ni warnings en home, login, registro, recuperacion, dashboard, proyecciones, blog, cuenta, privacidad, terminos y aviso |
| Flujo registro-verificacion-login | OK | Registro temporal genero enlace `[DEV][VERIFY LINK]`, login quedo bloqueado antes de verificar, `verificacion/verificar` marco la cuenta y el login posterior accedio al dashboard |
| Reenvio y rate limiting | OK | Cuenta temporal pendiente genero enlaces en las tres primeras solicitudes de reenvio; la cuarta quedo bloqueada con `bloqueado_hasta` en `intentos_acceso` |
| Secretos | OK | `git ls-files -- .env .env.example` devuelve solo `.env.example`; `git log --all --full-history --oneline -- .env` sin salida; `stat -c '%a %U:%G %n' .env` devuelve `640 hiram9112:www-data .env` |

Durante la verificacion manual se detecto que la base de datos local de desarrollo no tenia aun la tabla `intentos_acceso`, aunque `database/schema.sql` ya la define y la suite de integracion la crea en `benehom_test`. Se creo la tabla local con el mismo esquema para poder verificar el rate limiting end-to-end. Esta accion no modifica el entregable de codigo; en otros entornos debe aplicarse mediante reconstruccion/migracion de BD segun el procedimiento del sprint.

## Fuera de alcance

La rotacion de contrasenas reales, incluyendo credenciales SMTP o de base de datos, queda fuera de este sprint y debe realizarse manualmente por el responsable del entorno. La politica de permisos de Hostinger debe verificarse en el hosting real antes de desplegar.

Queda fuera tambien una auditoria externa de pentesting y una politica completa de logging/alertas de seguridad en produccion.
