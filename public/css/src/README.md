# CSS fuente de BeneHom

Esta carpeta contiene la version editable del CSS propio de BeneHom separada por responsabilidad. Es la fuente de verdad del CSS propio de la aplicacion.

En entorno local se cargan estos archivos separados desde `bh_css_tags()` para que los cambios se vean al recargar. En produccion se sirve `public/css/app.min.css`, generado desde estos mismos archivos.

Orden canonico de carga y concatenacion:

1. `base.css`
2. `layout.css`
3. `components.css`
4. `dashboard.css`
5. `proyecciones.css`
6. `auth.css`
7. `home.css`
8. `blog.css`
9. `cuenta.css`
10. `legal.css`
11. `responsive.css`

Las media queries se mantienen cerca del bloque o vista que modifican. `auth.css` contiene solo autenticacion y `home.css` contiene la home publica. `responsive.css` queda reservado para ajustes realmente globales y transversales.

No edites `../app.min.css` manualmente. Se regenera desde estos archivos con:

```bash
composer build:css
```

Ejecuta ese comando antes de desplegar para que el CSS minificado refleje los ultimos cambios.
