# Auditoria de seguridad

## Alcance

Este documento recoge el cierre de la fase E del Sprint 19: higiene de secretos y configuracion. La verificacion cubre permisos locales de `.env`, trazabilidad Git del archivo de entorno y exposicion por URL de directorios privados.

## Secretos y `.env`

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
| Produccion Hostinger | Política documentada | `public_html` debe contener el contenido de `public/`; `app/`, `config/`, `database/`, `vendor/` y `.env` quedan fuera |
| Defensa por acceso accidental a `/benehom/` | OK | `.htaccess` en la raiz del repo bloquea `.env`, `app/`, `config/`, `database/` y `vendor/` |

El VirtualHost local activo es `benehom.local` y sirve directamente desde `public/`. Se mantiene una defensa adicional en la raiz del repositorio para el caso de que el DocumentRoot global de Apache siga exponiendo `/var/www/html` y alguien acceda al proyecto como subdirectorio.

## Verificacion HTTP

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

## Fuera de alcance

La rotacion de contraseñas reales, incluyendo credenciales SMTP o de base de datos, queda fuera de esta fase y debe realizarse manualmente por el responsable del entorno. La politica de permisos de Hostinger debe verificarse en el hosting real antes de desplegar.
