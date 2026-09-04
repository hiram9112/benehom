# Cierre de Numa

Acta definitiva de cierre de la tarea 17.6. Recoge la evidencia disponible en
el registro tecnico local de 2026-09-04 y declara cerrada la tarea.

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

## Evidencia de revision manual final

- La UX privada y publica, en escritorio y movil, junto con teclado, foco y comportamiento general, fue revisada y aceptada durante las tareas anteriores.
- `prefers-reduced-motion: reduce` se comprobo manualmente; Numa siguio funcionando correctamente.
- Sin JavaScript, BeneHom permanecio navegable y con el contenido principal disponible; el personaje mostro su fallback estatico y Numa su aviso de que requiere JavaScript.
- Con `gsap.min.js` temporalmente ausente, la web y Numa siguieron funcionando y conservaron el fallback visual correcto.
- La comprobacion semantica basica en DevTools Accessibility confirmo roles y nombres accesibles coherentes en los controles inspeccionados.
- Se registraron dos respuestas `503`, `NUMA_PROVIDER_INVALID_RESPONSE` y `NUMA_PROVIDER_TIMEOUT`. La investigacion posterior de logs las determino transitorias: cada una fue seguida inmediatamente por una peticion correcta, no se reprodujo un defecto y los logs mantuvieron el contrato de privacidad.

## Checklist final

### Funcionalidad

- [x] Regresion PHP, analisis estatico, build de assets, navegador, RAG automatizado y evaluacion adversarial correctos. Evidencia: resultados anteriores.
- [x] Numa puede activarse por configuracion en local sin modificar codigo. Evidencia: `.env` local con `APP_ENV=local`, `NUMA_ENABLED=true` y `NUMA_PUBLIC_ENABLED=true`; el estado publico confirmo disponibilidad.
- [x] La documentacion de activacion y desactivacion repetible ya existe en [runbook.md](runbook.md).
- [x] Validacion humana del recorrido visual y de interaccion en las vistas aplicables completada y aceptada. Evidencia: revision manual final.

### Seguridad

- [x] Acceso web a rutas y recursos sensibles comprobado desde el VirtualHost correcto. Evidencia: resultados de Apache anteriores.
- [x] No hay secretos detectados en el arbol actual y `.env` no se versiona. Evidencia: Gitleaks y `git check-ignore` ejecutados.
- [x] Los errores y respuestas tecnicas no se exponen en el recorrido cubierto por las pruebas. Evidencia: suite completa y pruebas especificas de `NumaMinimalLogger` correctas.

### Privacidad

- [x] Los logs revisados no contienen contenido conversacional, datos financieros, secretos ni cabeceras de autenticacion. Evidencia: revision descrita en Resultados automatizados.
- [x] El transcript se limita a la sesion PHP y la cuota publica usa una identidad seudonimizada. Evidencia: pruebas de Numa y politica de privacidad versionada.
- [x] La politica de privacidad y el runbook documentan la minimizacion de datos y el diagnostico seguro.

### Coste

- [x] La ejecucion automatizada no genero coste externo. Evidencia: Playwright, RAG y adversarial usaron proveedores fake; no se ejecuto Gemini real durante este cierre.
- [x] Limites de uso, tokens, reintentos y timeouts estan cubiertos por configuracion y pruebas automatizadas. Evidencia: suite completa y pruebas de configuracion correctas.

### Accesibilidad

- [x] Cobertura automatizada de teclado, foco, dialogo, estados anunciados, movimiento reducido, viewport movil y transcript con Lenis. Evidencia: los 28 tests de Playwright correctos y pruebas de Numa asociadas.
- [x] El panel declara nombres accesibles, estados ARIA y alternativa sin JavaScript; el personaje conserva fallback estatico sin GSAP.
- [x] Revision humana de accesibilidad completada: UX privada y publica, escritorio, movil, teclado/foco, movimiento reducido, fallbacks sin JavaScript y GSAP, y comprobacion semantica basica en DevTools Accessibility. Evidencia: revision manual final.

## Riesgos residuales

- No hay defectos reproducibles abiertos. Los dos `503` observados durante la revision manual fueron incidencias transitorias controladas y seguidas inmediatamente por solicitudes correctas; no requieren cambios de codigo.
- Las comprobaciones efectivas de Google Cloud/Gemini y de los flags del entorno de produccion se realizan en el futuro despliegue de Numa. Son tareas operativas de lanzamiento y no bloquean el cierre de 17.6.

## Tareas diferidas

Estas mejoras estan conscientemente fuera del alcance actual y no son defectos abiertos:

- Ledger detallado por peticion, reconciliacion tras caidas e idempotencia durable.
- Panel administrativo de consumo o costes, monitorizacion avanzada y pruebas de carga o soak.
- Recuperacion de respuestas tras desconexion, sincronizacion entre pestanas y streaming de respuestas.

## Verificaciones previas al futuro despliegue

Estas verificaciones operativas se realizaran antes de desplegar Numa a produccion. Google Cloud ya fue revisado en una fase anterior; deben reconfirmarse sus ajustes efectivos en el contexto del despliegue:

1. En Google Cloud/Gemini, confirmar proyecto, clave restringida a las APIs necesarias, facturacion, presupuesto, alertas, cuotas y los ajustes de privacidad operativa de [privacidad-operativa.md](privacidad-operativa.md), incluidos registros y almacenamientos opcionales desactivados.
2. En el servidor de produccion, confirmar que el fichero de entorno protegido mantiene `NUMA_ENABLED=false` y `NUMA_PUBLIC_ENABLED=false` hasta el despliegue correspondiente y aplicar los flags aprobados al habilitar Numa.

## Estado final

La tarea 17.6 queda cerrada. No quedan pendientes bloqueantes dentro de su
alcance; las verificaciones de Google Cloud/Gemini y de produccion se mantienen
como tareas operativas previas al futuro despliegue de Numa.
