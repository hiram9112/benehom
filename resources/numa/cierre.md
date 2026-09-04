# Cierre de Numa

Acta de cierre en progreso de la tarea 17.6. Recoge solo la evidencia disponible
en el registro tecnico local de 2026-09-04. No declara cerrada la tarea.

## Resultados automatizados

| Comprobacion | Evidencia ya ejecutada |
| --- | --- |
| PHPUnit completo | `composer test`: PHPUnit 11.5.55 sobre PHP 8.3.6, `788 tests`, `5201 assertions`, `00:16.710`. Tras la reversión de un cambio no necesario de `getenv()`, se ejecuto de nuevo: `788 tests`, `5201 assertions`, `00:15.786`. |
| PHPStan | `vendor/bin/phpstan analyse`: configuracion `phpstan.dist.neon`, `No errors`. |
| Build y guard CSS | `composer build:css`: generado `public/css/app.min.css` desde `13 source files`. `composer lint:design`: `Sin hallazgos`. |
| Build frontend | `npm run build`: proveedores versionados de GSAP y Lenis copiados a `public/js/vendor/`. |
| Playwright | `npm run test:e2e`: `28 passed (18.0s)`, con `APP_ENV=testing`, ambos flags de Numa desactivados y proveedores fake; sin llamadas ni coste de Gemini. |
| Evaluacion RAG automatizada | `vendor/bin/phpunit tests/Integration/NumaRagEvaluationTest.php`: `2 tests`, `215 assertions`, `00:00.141`, con fakes. |
| Evaluacion adversarial | `vendor/bin/phpunit tests/Unit/NumaAdversarialEvaluationTest.php`: `25 tests`, `121 assertions`, `00:00.013`. |
| Secretos | Gitleaks 8.30.1 analizo 266 commits y el arbol de trabajo. El unico hallazgo historico fue un falso positivo de `.env.example` con `NUMA_EMBEDDING_API_KEY` vacia; `gitleaks dir` devolvio `no leaks found`. `.env` esta ignorado por Git. |
| Apache y rutas sensibles | `apache2ctl -S` confirmo `benehom.local` con `DocumentRoot` en `public/`. Desde ese host, `/.env` devolvio `403`; recursos internos devolvieron `404`; `numa/status` sin sesion devolvio `401 application/json`; `numa/public/status` devolvio `200 application/json` sin contadores ni detalles internos. |
| Logs | Revision completa de los logs de las peticiones controladas: dos denegaciones de `/.env` en error log y 18 GET en access log. No hubo coincidencias para secretos, prompts, mensajes, respuestas, tools, importes ni cabeceras de autenticacion. |

## Checklist final

### Funcionalidad

- [x] Regresion PHP, analisis estatico, build de assets, navegador, RAG automatizado y evaluacion adversarial correctos. Evidencia: resultados anteriores.
- [x] Numa puede activarse por configuracion en local sin modificar codigo. Evidencia: `.env` local con `APP_ENV=local`, `NUMA_ENABLED=true` y `NUMA_PUBLIC_ENABLED=true`; el estado publico confirmo disponibilidad.
- [x] La documentacion de activacion y desactivacion repetible ya existe en [runbook.md](runbook.md).
- [ ] Validacion humana del recorrido visual y de interaccion en las vistas aplicables antes de la aceptacion final. Vease Accesibilidad para el alcance.

### Seguridad

- [x] Acceso web a rutas y recursos sensibles comprobado desde el VirtualHost correcto. Evidencia: resultados de Apache anteriores.
- [x] No hay secretos detectados en el arbol actual y `.env` no se versiona. Evidencia: Gitleaks y `git check-ignore` ejecutados.
- [x] Los errores y respuestas tecnicas no se exponen en el recorrido cubierto por las pruebas. Evidencia: suite completa y pruebas especificas de `NumaMinimalLogger` correctas.
- [ ] Confirmar manualmente en Google Cloud/Gemini la propiedad del proyecto y la restriccion efectiva de la unica clave a las APIs necesarias.

### Privacidad

- [x] Los logs revisados no contienen contenido conversacional, datos financieros, secretos ni cabeceras de autenticacion. Evidencia: revision descrita en Resultados automatizados.
- [x] El transcript se limita a la sesion PHP y la cuota publica usa una identidad seudonimizada. Evidencia: pruebas de Numa y politica de privacidad versionada.
- [x] La politica de privacidad y el runbook documentan la minimizacion de datos y el diagnostico seguro.
- [ ] Confirmar manualmente en Google Cloud/Gemini los ajustes operativos de privacidad documentados en [privacidad-operativa.md](privacidad-operativa.md), incluidos los registros y almacenamientos opcionales desactivados.

### Coste

- [x] La ejecucion automatizada no genero coste externo. Evidencia: Playwright, RAG y adversarial usaron proveedores fake; no se ejecuto Gemini real durante este cierre.
- [x] Limites de uso, tokens, reintentos y timeouts estan cubiertos por configuracion y pruebas automatizadas. Evidencia: suite completa y pruebas de configuracion correctas.
- [ ] Confirmar manualmente que el proyecto de Google tiene facturacion, presupuesto, alertas y cuotas configurados conforme al [runbook.md](runbook.md).

### Accesibilidad

- [x] Cobertura automatizada de teclado, foco, dialogo, estados anunciados, movimiento reducido, viewport movil y transcript con Lenis. Evidencia: los 28 tests de Playwright correctos y pruebas de Numa asociadas.
- [x] El panel declara nombres accesibles, estados ARIA y alternativa sin JavaScript; el personaje conserva fallback estatico sin GSAP.
- [ ] Completar la revision humana con lector de pantalla, teclado y foco, vistas autenticadas y publicas, movil real, fallbacks sin JavaScript y GSAP, transcript, Lenis y baseline visual del personaje.

## Riesgos residuales

- El estado del servidor de produccion no puede acreditarse desde el entorno local. Antes del despliegue aprobado debe confirmarse que su `.env` protegido mantiene `NUMA_ENABLED=false` y `NUMA_PUBLIC_ENABLED=false`.
- Los controles operativos externos de Google Cloud/Gemini requieren confirmacion manual; hasta entonces no hay evidencia de su configuracion efectiva.

## Tareas diferidas

Estas mejoras estan conscientemente fuera del alcance actual y no son defectos abiertos:

- Ledger detallado por peticion, reconciliacion tras caidas e idempotencia durable.
- Panel administrativo de consumo o costes, monitorizacion avanzada y pruebas de carga o soak.
- Recuperacion de respuestas tras desconexion, sincronizacion entre pestanas y streaming de respuestas.

## Validaciones manuales pendientes

1. En Google Cloud/Gemini, confirmar proyecto, clave restringida, facturacion, presupuesto, alertas, cuotas y ajustes de privacidad operativa.
2. En el servidor de produccion, confirmar que el fichero de entorno protegido mantiene `NUMA_ENABLED=false` y `NUMA_PUBLIC_ENABLED=false` hasta el despliegue correspondiente.
3. Realizar la aceptacion humana de UX y accesibilidad: lector de pantalla, teclado/foco, vistas privadas y publicas, movil real, fallbacks sin JavaScript/GSAP, transcript, Lenis y baseline visual.

## Estado actual

La tarea 17.6 permanece abierta hasta completar y registrar evidencia de las
validaciones manuales pendientes. Este documento debera actualizarse una ultima vez
cuando se hayan completado; hasta entonces no constituye una declaracion de cierre.
