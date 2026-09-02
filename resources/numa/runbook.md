# Runbook operativo de Numa

Este documento describe la activacion y recuperacion de Numa. No contiene claves,
prompts, conversaciones, resultados de tools ni datos de usuarios.

## Configuracion validada

`NumaConfiguration` valida los valores efectivos antes de atender chat privado o
publico, indexar conocimiento o ejecutar la evaluacion RAG real. Los valores ausentes
usan los defaults de `.env.example`; los valores presentes e invalidos bloquean el
recorrido antes de crear proveedores o iniciar una llamada pagada.

| Grupo | Variables |
| --- | --- |
| Activacion | `NUMA_ENABLED`, `NUMA_PUBLIC_ENABLED` |
| Limites HTTP | `NUMA_MAX_MESSAGE_LENGTH`, `NUMA_MAX_REQUEST_BODY_BYTES`, `NUMA_MAX_PROVIDER_RESPONSE_BODY_BYTES`, `NUMA_CHAT_BURST_*` |
| Cuota privada | `NUMA_DAILY_LIMIT`, `NUMA_MONTHLY_LIMIT`, `NUMA_RESERVATION_TTL_SECONDS` |
| Generacion | `NUMA_PROVIDER`, `NUMA_MODEL`, `NUMA_API_KEY`, `NUMA_MAX_INPUT_TOKENS`, `NUMA_MAX_OUTPUT_TOKENS`, `NUMA_MAX_PROVIDER_CALLS`, `NUMA_PROVIDER_TIMEOUT_SECONDS`, `NUMA_REQUEST_TIMEOUT_SECONDS`, `NUMA_MAX_TRANSIENT_RETRIES` |
| Limites globales | `NUMA_GLOBAL_*` |
| Embeddings y RAG | `NUMA_EMBEDDING_PROVIDER`, `NUMA_EMBEDDING_MODEL`, `NUMA_EMBEDDING_DIMENSIONS`, `NUMA_MAX_RAG_RESULTS`, `NUMA_MAX_RAG_CHUNK_CHARS`, `NUMA_RAG_MIN_SIMILARITY` |
| Tools | `NUMA_MAX_TOOL_CALLS`, `NUMA_MAX_TOOL_RESULT_CHARS`, `NUMA_MAX_TOOL_RANGE_DAYS` |
| Modo publico | `NUMA_PUBLIC_HASH_KEY`, `NUMA_PUBLIC_DAILY_LIMIT`, `NUMA_PUBLIC_MONTHLY_LIMIT`, `NUMA_PUBLIC_GLOBAL_*` |
| Evaluacion RAG real | `NUMA_RAG_EVALUATION_DB_*` |

Los modelos iniciales son `gemini-3.1-flash-lite` para generacion y
`gemini-embedding-001` con 768 dimensiones para embeddings. El modo `fake` solo se
admite con `APP_ENV=testing`; nunca es una alternativa de produccion.

Los limites seguros iniciales son: 300 caracteres, 16.000 tokens de entrada para alojar
las seis declaraciones financieras completas, hasta 9 llamadas pagadas por interaccion
(clasificacion, embedding RAG, cinco tools, redaccion final y un reintento transitorio),
1.000 tokens de salida, 10 segundos por llamada, 25 segundos por peticion, 15 llamadas
diarias y 60 mensuales por identidad privada o publica. Los limites globales iniciales
estan en `.env.example` y deben ajustarse antes de activar cada entorno.

## Activacion y desactivacion

1. Mantener `NUMA_ENABLED=false` hasta completar configuracion, indexacion y pruebas.
2. Crear una unica clave de servidor restringida a Gemini API y guardar su valor solo
   en el fichero de entorno protegido fuera del DocumentRoot.
3. Configurar los modelos, limites y `NUMA_API_KEY`; configurar tambien
   `NUMA_PUBLIC_HASH_KEY` si se activara el modo publico.
4. Ejecutar `php bin/indexar-numa.php` en el mismo entorno y verificar que informa de
   fragmentos, firma y cero errores.
5. Consultar `GET numa/status` autenticado. Debe devolver `availability: available`
   solo si configuracion, tablas, indice compatible y limites locales son validos.
6. Activar en desarrollo o preproduccion con `NUMA_ENABLED=true`. Activar el modo
   publico solo si procede mediante `NUMA_PUBLIC_ENABLED=true`.
7. Mantener produccion desactivada hasta la validacion y el despliegue aprobados.

Para detener todo el servicio, cambiar `NUMA_ENABLED=false` y recargar PHP. Para
detener solo el modo publico, conservar el flag general y cambiar
`NUMA_PUBLIC_ENABLED=false`. Ninguna de las dos operaciones elimina cuota, indice ni
transcript de la sesion.

## Indexacion e indice

Ejecutar una vez despues de desplegar un alta, edicion, retirada, cambio de estado o
cambio de slug de un articulo elegible:

```bash
php bin/indexar-numa.php
```

El comando solo se ejecuta por CLI, toma un lock local, valida los nueve documentos y
el catalogo publico del blog, y es idempotente. Nunca se ejecuta al servir una pagina.
La disponibilidad comprueba que exista al menos un fragmento compatible con la firma
actual. Para una inspeccion operativa adicional, con una cuenta de solo lectura:

```sql
SELECT COUNT(*) AS fragmentos, dimensiones, firma_embedding
FROM numa_conocimiento
GROUP BY dimensiones, firma_embedding;
```

La calibracion real se ejecuta manualmente y fuera de CI con una base aislada terminada
en `_test` o `_sandbox`:

```bash
php bin/evaluar-rag-numa.php --real
```

## Cuota, proveedor y claves

Si se alcanza una cuota local o global, no aumentar limites como respuesta inmediata.
Confirmar el contador afectado, esperar al siguiente periodo o aplicar un cambio de
limite aprobado en configuracion. Si Gemini responde con error, cuota externa, rate
limit o timeout, mantener Numa desactivada o temporalmente no disponible hasta revisar
la configuracion y el estado del proveedor; no reintentar manualmente peticiones de
usuarios ni registrar su contenido.

Ante sospecha de exposicion de `NUMA_API_KEY`:

1. Desactivar Numa con los feature flags.
2. Revocar la clave afectada en Google AI Studio o Google Cloud.
3. Crear una nueva clave con la misma restriccion a Gemini API y actualizar solo el
   entorno protegido.
4. Recargar PHP, comprobar `status` sin enviar datos de usuarios y revocar la clave
   anterior si aun no se habia revocado.
5. Revisar configuracion y logs sin copiar secretos ni contenido privado.

Configurar manualmente en Google Cloud un presupuesto mensual bajo, alertas al 50 %,
75 %, 90 % y 100 %, y las cuotas mas bajas compatibles con la etapa del producto. Las
alertas externas no sustituyen los limites duros configurados por BeneHom. Verificar
ademas que el proyecto de pago, la unica clave restringida y el almacenamiento opcional
de Gemini permanecen con la configuracion aprobada en `privacidad-operativa.md`.

## Esquema en bases existentes

`database/schema.sql` es la fuente canonica para bases nuevas. Para una base existente,
realizar backup, ensayar primero en staging y ejecutar solo las sentencias aditivas que
no existan todavia. No hay runner de migraciones.

```sql
-- Comprobar antes de cada ALTER; sustituir DATABASE() por el esquema objetivo si hace falta.
SELECT TABLE_NAME, COLUMN_NAME
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN ('numa_uso_proveedor', 'numa_conocimiento');

-- Aplicar solo si falta la columna indicada.
ALTER TABLE numa_uso_proveedor
  ADD COLUMN llamadas_publicas INT NOT NULL DEFAULT 0 AFTER llamadas;

ALTER TABLE numa_conocimiento
  ADD COLUMN firma_embedding VARCHAR(500) NOT NULL AFTER dimensiones;

-- Crear solo las tablas que no existan aun copiando su definicion actual desde schema.sql:
-- numa_uso, numa_reservas, numa_uso_publico, numa_reservas_publicas,
-- numa_uso_proveedor y numa_conocimiento.
-- Verificar tambien los indices numa_* definidos en database/schema.sql antes de activar.
```

No ejecutar `database/schema.sql` directamente sobre una base con datos: sus `CREATE
TABLE` no son un mecanismo de actualizacion. Registrar las sentencias realmente
aplicadas y conservar el backup hasta verificar `GET numa/status` e indexacion.

## Proteccion de rutas

El `.htaccess` de raiz rechaza `bin`, `knowledge`, `resources`, `tests`, `.git` y los
demas directorios internos incluso si se configura por error el DocumentRoot en la raiz
del repositorio. El DocumentRoot correcto sigue siendo `public/`; esta proteccion es una
defensa adicional, no un sustituto de esa configuracion.

## Validacion local con Apache

Para una validacion manual local, acceder mediante `http://benehom.local`, cuyo
VirtualHost debe servir `public/`, y observar
`/var/log/apache2/benehom.local-error.log`. Confirmar el host efectivo con
`apache2ctl -S`. Las peticiones al host por defecto, como
`http://localhost/benehom/public`, se registran en `/var/log/apache2/error.log`.

## Validacion financiera controlada

Antes de continuar con la aceptacion general, ejecutar fuera de CI una validacion con
Gemini real, una cuenta de prueba y movimientos sinteticos. No guardar mensajes,
respuestas, importes ni payloads completos: conservar solo una ficha tecnica por caso
con la tool elegida, nombres de argumentos, `finishReason`, numero de llamadas,
unidades consumidas y si la respuesta final coincidió con el resultado autorizado.

Los casos minimos son una consulta de `electricidad` y su expresion "luz", una de
`comida_domicilio` y "comida a domicilio", un filtro por grupo, una metrica, un periodo
simbolico y un rango explicito. Para cada uno, comprobar que `tools.functionDeclarations`
incluye las seis declaraciones completas, que la clasificacion no contiene el antiguo
arbol financiero de `responseSchema` y que el intercambio conserva exactamente
`functionCall` (nombre e identificador), `functionResponse` y el turno del modelo.

Registrar por separado los rechazos de `MAX_TOKENS`, tool desconocida, argumentos
adicionales, enums invalidos, periodos incompletos y combinaciones incompatibles. Ningun
caso rechazado puede ejecutar una consulta financiera ni devolver un resultado parcial.
Confirmar tambien que el usuario procede de la sesion, las tools son solo lectura y el
modo publico no recibe declaraciones financieras.

Para investigar de forma temporal una respuesta de Gemini, activar
`NUMA_PROVIDER_RESPONSE_DIAGNOSTICS=true`. El log estructurado incluye solo el turno
del proveedor, el numero de `functionCall`, sus nombres e IDs, `finishReason` y las
claves de cada `part`; nunca incluye el prompt, argumentos, resultados ni credenciales.
Desactivarlo tras obtener la evidencia necesaria.

### Cierre controlado 17.3.3

Este registro cierra la validacion financiera controlada de 17.3.3. La evidencia de
Gemini real procede de una ejecucion manual externa con cuenta de prueba y datos
sinteticos. No se realizaron llamadas reales adicionales para redactar este cierre.
Los argumentos completos de Gemini, IDs, payloads completos, tokens y unidades no
indicados a continuacion son **no registrados**.

#### Comprobado con Gemini real

| Caso | Resultado y evidencia registrada | Llamadas y detalle no registrado |
| --- | --- | --- |
| `cuanto gasté en luz en junio` | PASS. NUMA devolvio 97,00 EUR de electricidad, coincidente con BeneHom. La ejecucion final registro `outcome=success` y `error_code=null`. | `calls=3`. `finishReason`, argumentos e IDs: no registrados. |
| `cuanto gasté en comida a domicilio en junio?` | PASS. NUMA devolvio 52,40 EUR, coincidente con BeneHom, y conservo `comida_domicilio`; no se degrado a un resumen de gastos flexibles. | Llamadas, `finishReason`, argumentos e IDs: no registrados. |
| `cuánto gasté en suministros en junio?` | PASS. NUMA devolvio 204,80 EUR y el desglose autorizado: electricidad 97,00 EUR; internet y telefonia basica 44,90 EUR; agua 33,10 EUR; gas 29,80 EUR. Se valido el grupo `suministros`. | Llamadas, `finishReason`, argumentos e IDs: no registrados. |
| `Compara cuánto gasté en electricidad y comida a domicilio en junio` | PASS tras correccion. Resultado final: electricidad 97,00 EUR y comida a domicilio 52,40 EUR. | La ejecucion inicial fallo con `NUMA_PROVIDER_INVALID_RESPONSE` y `calls=2`; las llamadas de la repeticion final, `finishReason`, argumentos e IDs: no registrados. |
| `¿Cuánto gasté en electricidad?` en una conversacion nueva sin periodo | PASS tras correccion. La respuesta final fue: `Necesito que concretes un poco más la consulta para poder ayudarte.` No se ejecutaron tools ni consultas financieras cuando no habia un periodo autorizado. | La ejecucion inicial con HTTP 503 registro `calls=2`; las llamadas de la repeticion final, tokens, argumentos e IDs: no registrados. |
| Continuacion con `en junio` | PASS. NUMA reutilizo la intencion financiera anterior y devolvio electricidad de junio: 97,00 EUR, con periodo del 1 al 30 de junio de 2026. No fue necesario repetir la consulta completa. | Llamadas, `finishReason`, argumentos e IDs: no registrados. |

#### Observado en logs

Para una de las ejecuciones sin periodo se registro esta secuencia diagnostica, sin
argumentos, IDs ni payloads completos:

| Turno | `function_call_count` | Tool | `finishReason` | Resultado observado |
| --- | --- | --- | --- | --- |
| 1 | 0 | No aplicable | `STOP` | Respuesta de texto. |
| 2 | 1 | `obtener_estadisticas_movimientos` | `STOP` | Gemini solicito una tool. |
| 3 | 0 | No aplicable | `STOP` | Respuesta final de texto. |

El resto de `finishReason`, argumentos e IDs de la validacion real son no registrados.

#### Incidencias descubiertas y estado final

| Incidencia | Evidencia y causa confirmada | Estado final |
| --- | --- | --- |
| `additionalProperties` incompatible con Gemini | Antes del primer PASS se observo HTTP 400 porque las `functionDeclarations` incluian ese campo, no admitido por Gemini. | Corregida: se proyecta para Gemini un schema compatible sin `additionalProperties`; la validacion estricta interna de BeneHom se conserva. |
| Rechazo inicial de varios `functionCall` | La comparacion inicial fallo con `NUMA_PROVIDER_INVALID_RESPONSE` y `calls=2`: Gemini devolvio varias llamadas en un turno y el backend rechazo la segunda. | Corregida y validada con Gemini real: se admiten lotes de `functionCall`, con un `functionResponse` por llamada y conservacion de nombre e ID. |
| Falta de atomicidad del lote | Se identifico como requisito de seguridad al corregir los lotes. | Corregida y cubierta por pruebas automatizadas: el lote se valida completo antes de ejecutar tools; si una llamada es invalida, se ejecutan 0 tools y 0 consultas financieras. No hay un payload real de este caso registrado. |
| Periodo ausente convertido inicialmente en 503 | En conversacion nueva se observo `NUMA_PROVIDER_INVALID_RESPONSE`, HTTP 503 y `calls=2`. | Corregida: sin periodo autorizado se devuelve aclaracion y se ejecutan 0 tools y 0 consultas financieras. La repeticion real fue PASS. |
| Periodo inventado o asumido por Gemini | Gemini asumio septiembre y NUMA ejecuto la consulta sin que el usuario hubiera dado periodo. La causa confirmada fue aceptar el periodo propuesto por Gemini y resolverlo contra la fecha actual sin verificar su procedencia. | Corregida y validada con Gemini real: el periodo solo puede venir del mensaje actual o de un periodo estructurado e inequivoco del contexto conversacional; no hay fallback al mes actual ni al mes del dashboard. |
| Fallback generico de aclaracion | La ausencia de periodo requeria una respuesta segura y consistente. | Corregido y validado con Gemini real: `Necesito que concretes un poco más la consulta para poder ayudarte.` |

#### Cubierto unicamente por pruebas automatizadas

Las pruebas de esta seccion usan transportes fake/mocks, fakes de proveedor o base de
datos de prueba; no constituyen nuevas llamadas a Gemini real. La cobertura relevante
de 17.3.3 queda en `GeminiNumaProviderTest`,
`NumaFinancialFunctionCallingTest`, `NumaFinancialToolRegistryTest`,
`NumaFinancialToolsTest`, `NumaProviderContractTest`, `NumaControllerTest` y
`NumaPublicServiceTest`.

| Defensa | Cobertura automatizada |
| --- | --- |
| Declaraciones y schema | Verifica la presencia de `tools.functionDeclarations`, las seis declaraciones completas, sus nombres, descripciones, enums, alternativas de periodo y schemas. Verifica ademas que Gemini recibe la proyeccion compatible sin `additionalProperties`, mientras el contrato interno mantiene su prohibicion. |
| Separacion clasificacion/tools | Verifica que el antiguo arbol de seleccion financiera no aparece en `responseSchema`; la clasificacion permanece estructurada y las tools se declaran en la solicitud posterior. |
| Protocolo function calling | Verifica la secuencia `functionCall -> validacion PHP -> tool -> functionResponse -> respuesta`, la correspondencia de nombre e ID, y la conservacion del turno `model` del proveedor, incluida su `thoughtSignature` cuando existe. |
| Llamadas sucesivas y en lote | Verifica una segunda tool secuencial, varios `functionCall` en un mismo turno y un `functionResponse` emparejado por llamada. Tambien verifica el rechazo de un lote que excede el presupuesto maximo de tools. |
| Atomicidad y rechazos | Verifica que un lote con una llamada invalida ejecuta 0 tools, prepara 0 consultas financieras y no devuelve resultados parciales. Cubre tool desconocida, argumentos adicionales, enums invalidos, periodos ausentes o incompletos, rangos incompatibles y combinaciones incompatibles. |
| Truncado y limites de proveedor | Verifica `MAX_TOKENS`, respuestas truncadas o no utilizables, respuestas sin `finishReason`, salida por encima del limite y rechazo antes de consultar tools. |
| Periodos autorizados | Verifica aclaracion y 0 tools/0 consultas para una consulta sin periodo o con un periodo inventado por Gemini sin contexto. Verifica tambien que un seguimiento reutiliza un periodo estructurado autorizado y no el periodo propuesto por Gemini. |
| Integridad de la respuesta | Verifica que una cifra final no respaldada por `functionResponse` se sustituye por un fallback determinista basado en los hechos autorizados. |
| Aislamiento y autorizacion | Verifica que las tools usan el usuario autenticado procedente del backend/sesion, rechazan `usuario_id` en argumentos y no exponen datos de otro usuario. Las tools financieras estan restringidas al catalogo de solo lectura y a resultados agregados o acotados. |
| Modo publico | Verifica que el modo publico no resuelve el registro de tools ni recibe declaraciones financieras. |
| Limites agregados y consumo | Verifica los hard caps de tools, rango temporal y resultado agregado, ademas del consumo de llamadas/unidades en recorridos correctos, rechazados y secuenciales. Los valores de consumo de Gemini real fuera de los `calls` indicados arriba son no registrados. |

#### Riesgos residuales

No hay riesgos residuales abiertos registrados para 17.3.3: las incidencias halladas
tienen correccion, regresion automatizada y validacion real satisfactoria en los casos
indicados. La evidencia real no incluye los campos expresamente marcados como no
registrados; no se infiere su contenido ni su consumo.
