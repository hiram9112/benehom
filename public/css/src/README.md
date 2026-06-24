# CSS fuente de BeneHom

Esta carpeta contiene la version editable del CSS propio de BeneHom separada por responsabilidad. Durante la fase A del Sprint 18, `public/css/custom.css` se conserva intacto como referencia y sigue siendo el CSS activo.

Orden canonico previsto para carga y concatenacion:

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

No edites `app.min.css` manualmente cuando exista el pipeline de produccion; debera generarse desde estos archivos.
