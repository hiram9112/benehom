# Registro técnico del cierre de rendimiento móvil `0eed35e`

Este registro no contiene secretos, credenciales, datos privados ni identificadores internos de producción.

## 1. Identificación

- Fecha: 2026-09-29
- Rama de trabajo: `perf/mobile-page-speed`
- SHA desplegado: `0eed35e3434c53c955498f0b34469e6abcce675e`
- Commit: `Merge pull request #17 from hiram9112/perf/mobile-page-speed`
- Estado CI y deployment: GitHub Actions finalizado correctamente.
- Referencia técnica del deployment: GitHub Actions es la fuente técnica del despliegue; este Markdown es el registro posterior.

## 2. Problema inicial

- Rendimiento móvil: aproximadamente 60-66.
- FCP: aproximadamente 4,8-5,1 s.
- LCP: aproximadamente 6,6-7,6 s.
- Se identificaron recursos CSS que bloqueaban el renderizado de la home.

## 3. Investigación y cambios realizados

- Se eliminaron las animaciones GSAP del primer viewport y se conservaron las animaciones de las secciones inferiores.
- La home sustituyó los iconos de Tabler Icon Font por 11 SVG inline oficiales de Tabler 1.119.0.
- La home dejó de cargar `tabler-icons.min.css` y `tabler-icons.woff2`; Tabler se mantiene en las demás rutas.
- Se eliminó `bootstrap.min.css` exclusivamente de la home.
- Se conservó `bootstrap.bundle.min.js` para el funcionamiento del offcanvas.
- Se incorporó en `home.css` el subconjunto mínimo basado en Bootstrap 5.3.2 para offcanvas, backdrop, estados, cierre, responsive, utilidades y accesibilidad.
- Se restauró `scroll-behavior: smooth` sobre el elemento raíz de la home, antes aportado por Bootstrap Reboot.
- Se añadieron pruebas unitarias y Playwright para proteger la carga condicional de assets, offcanvas, foco, Escape, backdrop, scroll, Lenis, responsive y `visually-hidden`.

## 4. Resultado en producción

- PageSpeed móvil: aproximadamente 90.
- Hostinger: aproximadamente 91.
- FCP: aproximadamente 1,5-1,7 s.
- LCP: aproximadamente 3,3-3,4 s.
- TBT: 0-90 ms.
- Speed Index: aproximadamente 2,8-3,3 s.

Las métricas de Lighthouse/PageSpeed son mediciones de laboratorio y pueden variar entre ejecuciones.

## 5. Estado final y decisión

- El commit `0eed35e` fue desplegado, validado en producción y quedó activo.
- Se considera resuelto el problema de rendimiento móvil identificado.
- No se continuará en esta rama con critical CSS, cambios de imágenes u otras optimizaciones.
- Se cierra el trabajo de rendimiento móvil y se continúa con los siguientes proyectos y features.
