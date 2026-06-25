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

## Responsive

La escala canonica de breakpoints queda alineada con Bootstrap y se usa siempre con valores `px` literales, porque CSS no permite `var()` dentro de la condicion de `@media`:

| Clave | Ancho | Uso |
| --- | --- | --- |
| `sm` | `576px` | Movil amplio / tablet pequena |
| `md` | `768px` | Aparece el sidebar |
| `lg` | `992px` | Layouts de escritorio compacto |
| `xl` | `1200px` | Escritorio amplio |

Convencion mobile-first: los estilos base, sin media query, describen el layout movil. Las mejoras progresivas se escriben con `@media (min-width: ...)`. Las media queries de preferencias, como `prefers-reduced-motion`, no son breakpoints de ancho y se mantienen intactas.

Mapeo para refactors responsive:

| Breakpoint actual | Mapeo canonico |
| --- | --- |
| `max-width: 767.98px` | Base movil + `@media (min-width: 768px)` |
| `max-width: 991.98px` | Base movil + `@media (min-width: 992px)` |
| `max-width: 575.98px`, `max-width: 576px`, `max-width: 36rem` | Base movil + `@media (min-width: 576px)` |
| `min-width: 768px`, `min-width: 992px` | Conservar como anclas de la escala |
| `420px`, `480px` | Absorber en la base movil o subir a `576px` si el ajuste solo aplica desde tablet |
| `560px` | Sustituir por `576px` |
| `900px` | Sustituir por `992px` |

Si un ajuste arbitrario es imprescindible en un ancho exacto, debe documentarse como excepcion antes de conservarlo.

No edites `../app.min.css` manualmente. Se regenera desde estos archivos con:

```bash
composer build:css
```

Ejecuta ese comando antes de desplegar para que el CSS minificado refleje los ultimos cambios.
