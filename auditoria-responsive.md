# Auditoria responsive de BeneHom

## Sprint 18.1: Responsive

Este documento recoge el cierre de verificacion de la fase D del Sprint 18.1. La auditoria combina revision estatica de CSS/markup, comprobaciones de build y verificacion real con Playwright MCP en rutas publicas y privadas autenticadas con el usuario demo (`demo@benehom.local`).

## Escala canonica

| Clave | Ancho | Uso |
| --- | --- | --- |
| `sm` | `576px` | Movil amplio / tablet pequena |
| `md` | `768px` | Aparece el sidebar y layouts de dos columnas |
| `lg` | `992px` | Escritorio compacto |
| `xl` | `1200px` | Escritorio amplio |

Convencion aplicada: base movil sin media query y mejoras progresivas con `@media (min-width: ...)`. Las media queries de preferencias, como `prefers-reduced-motion`, no son breakpoints de ancho.

## Mapeo de breakpoints

| Breakpoint anterior | Mapeo aplicado |
| --- | --- |
| `max-width: 767.98px` | Base movil + `@media (min-width: 768px)` |
| `max-width: 991.98px` | Base movil + `@media (min-width: 992px)` |
| `max-width: 575.98px`, `max-width: 576px`, `max-width: 36rem` | Base movil + `@media (min-width: 576px)` |
| `min-width: 768px`, `min-width: 992px` | Conservados como anclas de la escala |
| `420px`, `480px` | Absorbidos por la base movil |
| `560px` | Sustituido por `576px` |
| `900px` | Sustituido por `992px` |

No quedan breakpoints de ancho fuera de la escala canonica en `public/css/src`. Los valores `max-width: 36rem` en `home.css` y `perspective: 900px` en `layout.css` son propiedades, no condiciones `@media`.

## Verificaciones tecnicas

| Comprobacion | Estado | Evidencia |
| --- | --- | --- |
| CSS fuente con breakpoints canonicos | OK | `@media` de ancho solo usa `min-width: 576px`, `768px`, `992px`, `1200px` |
| Sin `@media (max-width: ...)` | OK | Sin coincidencias en `public/css/src/*.css` |
| Sin breakpoints arbitrarios `420/480/560/900/36rem` | OK | Sin coincidencias como condiciones `@media` |
| `auth.css` solo autenticacion | OK | Selectores propios `bh-auth-*` y Bootstrap acotado bajo `.bh-auth-form` |
| Blog repatriado | OK | Reglas `.bh-blog-*` viven en `blog.css`; base movil colapsa cards, hero, lectura y mapa |
| CSS produccion actualizado | OK | `composer build:css` genera `public/css/app.min.css` desde 11 archivos fuente |
| Versionado de assets | OK | `bh_asset()` usa `filemtime()` y no hay `v=.*time()` en `app/views` |
| Tablas | OK estatico | No hay `<table>` renderizadas por vistas PHP actuales; no se detecta punto de overflow vivo |
| Playwright MCP publico | OK | 36 combinaciones de ruta/ancho a 320/375/768/1200 sin scroll horizontal real ni targets tactiles propios menores de 44px |
| Playwright MCP privado | OK | 12 combinaciones autenticadas de dashboard, proyecciones y cuenta a 320/375/768/1200 sin scroll horizontal real ni targets tactiles propios menores de 44px |
| Texto al 200% | OK | 8 pantallas representativas a 1200px con `font-size: 200%` sin scroll horizontal real |

## Matriz por pantalla y breakpoint

| Pantalla | 320px | 375px | 768px | Escritorio | Estado / correccion aplicada |
| --- | --- | --- | --- | --- | --- |
| Home | OK headless | OK headless | OK headless | OK headless | `.bh-home-wrap` usa `min(100% - nrem, 1140px)`, hero/mockups parten de 1 columna y solo pasan a dos columnas desde `992px`; slip del mockup es estatico en movil. |
| Login | OK headless | OK headless | OK headless | OK headless | Card `width: min(100%, 520px)`, inputs y toggle de password con minimo tactil de 44px. |
| Registro | OK headless | OK headless | OK headless | OK headless | Comparte layout de autenticacion; campos y controles parten de ancho fluido. |
| Recuperacion | OK headless | OK headless | OK headless | OK headless | Comparte layout de autenticacion; sin columnas fijas ni controles menores de 44px. |
| Reset | OK estatico | OK estatico | OK estatico | OK estatico | Comparte layout de autenticacion; password toggle mantiene ancho tactil. |
| Dashboard | OK Playwright | OK Playwright | OK Playwright | OK Playwright | Cabecera de metricas: base 1 columna, `576px` 2 columnas, `768px` 3 columnas y `992px` 5 columnas. Instantanea de inversion validada en movil con categoria real del grafico. |
| Proyecciones | OK Playwright | OK Playwright | OK Playwright | OK Playwright | Offcanvas `width: min(100%, 34rem)` validado a 320px; formularios/listas/metricas parten de 1 columna. |
| Blog listado | OK Playwright | OK Playwright | OK Playwright | OK Playwright | Filtros con `flex-wrap`, cards y hero parten de 1 columna; buscador ajustado a `min-height: 44px`. |
| Blog detalle | OK Playwright | OK Playwright | OK Playwright | OK Playwright | Layout de lectura parte de 1 columna, mapa no es sticky en movil y solo recupera sidebar desde `992px`. |
| Cuenta | OK Playwright | OK Playwright | OK Playwright | OK Playwright | Identidad de cuenta parte apilada y pasa a fila desde `576px`; requisitos de password usan `auto-fit` con minimo contenido. |
| Privacidad | OK headless | OK headless | OK headless | OK headless | Documento legal usa card fluida con `max-width` y padding responsive compartido. |
| Terminos | OK headless | OK headless | OK headless | OK headless | Misma estructura legal fluida. |
| Aviso | OK headless | OK headless | OK headless | OK headless | Misma estructura legal fluida. |

## Puntos calientes revisados

| Punto | Estado | Detalle |
| --- | --- | --- |
| Cabecera del dashboard | OK Playwright | `.bh-summary-metrics` evita el `repeat(5, minmax(7.25rem, 1fr))` en movil y escala progresivamente. |
| Modal de instantanea de inversion | OK Playwright | Abierto a 320px con categoria real del grafico (`Restaurantes, bares y cafeterias`); sin scroll horizontal. |
| Offcanvas de proyecciones | OK Playwright | Abierto a 320px con ancho 320px y sin scroll horizontal. |
| `.bh-field-row` | OK | Base 1 columna; mejora con `auto-fit` desde `576px`. |
| Filtros del blog | OK Playwright | `.bh-blog-filter-list` permite `flex-wrap`, los chips tienen `min-height: 44px` y el filtro activo no genera overflow. |
| Home hero/mockups | OK Playwright | Hero y mockups se apilan en base; menu movil abre a 320px sin overflow. |
| Graficos | OK estatico | Alturas con `clamp()` y contenedores fluidos; Chart.js redimensiona el panel revelado con `grafico.resize()`. |
| Objetivos tactiles | OK Playwright | `.bh-btn`, `.bh-btn-icon`, `.bh-input`, `.bh-select`, `.bh-segmented-button` y buscador del blog mantienen minimo de 44px. |

## Criterio WCAG 1.4.10

Estado: verificado con Playwright MCP en rutas publicas, rutas privadas autenticadas y texto al 200% en escritorio.

No se detecta scroll horizontal real de pagina entre 320px y escritorio en home, autenticacion, blog, legales, dashboard, proyecciones y cuenta. La prueba de texto al 200% en escritorio tampoco permite desplazamiento horizontal real. Los layouts principales parten de una columna y usan `min-width: 0`, `min()`, `clamp()`, `auto-fit`, `flex-wrap` o anchos fluidos.

## Resultado de aceptacion

El entregable tecnico del Sprint 18.1 queda cerrado a nivel de repositorio: breakpoints canonicos documentados, CSS mobile-first, reglas repatriadas, `auth.css` acotado, puntos calientes cubiertos por CSS/JS, `auditoria-responsive.md` creado y `app.min.css` regenerado.

El criterio de aceptacion queda cumplido: no hay scroll horizontal real ni contenido recortado evidente en la auditoria Playwright, los objetivos tactiles propios cumplen 44px, las media queries usan la escala canonica, el aspecto se conserva en local y produccion tras `composer build:css`, y esta auditoria documenta pantallas, breakpoints y mapeo.
