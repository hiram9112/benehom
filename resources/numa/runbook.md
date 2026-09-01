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
