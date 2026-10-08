# Registro técnico del despliegue del MCP de BeneHom `16b98a7`

Este registro no contiene secretos, credenciales ni datos privados de producción.

## 1. Identificación

- Fecha: 2026-10-07
- Rama: `main`
- SHA desplegado: `16b98a7c01aa31439b71110cc3d7ae6e7f5881c0`
- Release publicada: `16b98a7`
- Release activa anterior: `dee2028`
- PR final: #23, «fix(mcp): preserve Host header when building global requests»
- GitHub Actions: workflow `CI`, ejecución #240
- Estado CI y deployment: finalizado correctamente; pruebas, análisis estático, construcción del artefacto, activación y smoke tests en verde.

## 2. Estado previo y backups

- [x] Release anterior `dee2028` conservada durante los intentos.
- [x] Rollback automático disponible.
- [x] Rollback automático ejecutado y verificado en los tres intentos fallidos.

## 3. Servidor

- Proveedor documentado: Hostinger.
- Servidor web documentado: LiteSpeed.
- PHP de producción documentado: 8.3.
- [x] `public/` se mantuvo como DocumentRoot mediante el symlink de release.
- [x] HTTPS validado en el endpoint MCP de producción.
- Endpoint: `https://benehom.es/mcp`.
- Transporte: Streamable HTTP.

## 4. Base de datos

- Motor/versión documentado: MariaDB 11.8.9.
- Schema asociado: tabla `mcp_personal_access_tokens` definida en `database/schema.sql`.
- [x] Persistencia de PAT operativa en producción, evidenciada por la creación, uso, revocación y rotación realizadas durante la validación final.
- [x] Conexión real validada mediante consultas financieras autenticadas.
- [x] El pipeline de deployment no aplicó seed.
- El comando y la hora exactos de aplicación del cambio de schema no constan en los registros consultados y no pueden verificarse.

## 5. Alcance desplegado

- MCP remoto de BeneHom servido mediante Streamable HTTP sobre HTTPS.
- Autenticación mediante `Authorization: Bearer <PAT>`.
- Una única tool expuesta: `consultar_datos_financieros`.
- Gestión web de PAT para creación, listado y revocación; el secreto completo se muestra una sola vez.
- Resolución de la identidad exclusivamente desde el PAT, sin aceptar identificadores de usuario proporcionados como argumentos de la tool.
- Reutilización de `NumaFinancialToolRegistryInterface::execute()` y de la lógica financiera canónica existente.
- Sin resources, prompts, OAuth, escritura financiera ni una segunda implementación del dominio.
- Cambio de schema para persistir selectores, hashes, fechas de uso y revocación de los PAT.

### Dependencias Composer del MCP

- Se incorporaron `mcp/sdk` `^0.8.1`, `nyholm/psr7` `^1.8` y `laminas/laminas-httphandlerrunner` `^2.12`.
- La primera integración incorporó también `nyholm/psr7-server` `^1.1` para construir peticiones desde las variables globales de PHP.
- `php-http/discovery` `1.20.0` ya estaba presente en `composer.lock` como dependencia del conjunto MCP. El commit `28eba55` autorizó explícitamente su plugin mediante `config.allow-plugins`; los registros de CI y de construcción de las releases no muestran un fallo de instalación de Composer atribuible a este plugin.
- La corrección final eliminó `nyholm/psr7-server` del `composer.json` y del lock, declaró directamente `php-http/discovery` `^1.20` y utilizó su fábrica PSR-17 para construir la petición desde globals.
- `nyholm/psr7` se mantuvo como implementación PSR-7. El SDK continuó fijado en `mcp/sdk` `v0.8.1` en el lock.

## 6. Pasos del deploy

- [x] Confirmar SHA final.
- [x] Confirmar CI verde.
- [x] Construir el artefacto con `composer release:build`.
- [x] Instalar en el artefacto las dependencias Composer de producción desde `composer.lock`.
- [x] Verificar checksum SHA-256 del artefacto.
- [x] Transferir y preparar la release en el servidor.
- [x] Activar la release mediante symlink.
- [x] Ejecutar smoke tests HTTPS de home, blog, CSS y autenticación del endpoint MCP.
- [x] Mantener y comprobar el rollback automático durante los intentos fallidos.

### Historial de intentos

#### Intento 1: release `2ced07a`, ejecución #231

- Se intentó desplegar por primera vez el endpoint Streamable HTTP, la tool financiera, la autenticación PAT, la gestión de tokens y el smoke test MCP incorporados por la PR #20.
- Las pruebas y el análisis estático pasaron. También finalizaron correctamente la construcción del artefacto, la instalación de 29 paquetes de producción, el checksum, la transferencia y la preparación remota.
- La activación falló porque el smoke de `/mcp` no aceptó ninguna de sus tres respuestas. El script de ese intento no registraba el estado HTTP ni el valor de `WWW-Authenticate`, por lo que el síntoma exacto y la causa técnica no pueden determinarse a partir de esa ejecución.
- Como primera hipótesis operativa se redujeron condiciones demasiado estrictas del smoke: se permitió `WWW-Authenticate: Bearer` con parámetros y dejaron de bloquear el despliegue las variaciones de cuerpo o cabeceras de caché. Este ajuste no quedó demostrado como causa del fallo.
- La release fue rechazada y el rollback automático restauró y verificó `dee2028`.

#### Intento 2: release `55f9039`, ejecución #234

- Se desplegó el ajuste de la PR #21 con la validación de autenticación MCP relajada.
- La suite, el análisis estático, el build, Composer, el artefacto y la preparación remota volvieron a finalizar correctamente.
- `/mcp` volvió a fallar tres veces durante la activación. Esta ejecución aún no mostraba el estado HTTP ni la cabecera de autenticación, por lo que la causa seguía sin ser verificable.
- Se añadió diagnóstico seguro al smoke para registrar exclusivamente el estado y `WWW-Authenticate`, sin exponer el cuerpo de la respuesta.
- La release fue rechazada y el rollback automático restauró y verificó `dee2028`.

#### Intento 3: release `52d7978`, ejecución #237

- Se desplegó la instrumentación diagnóstica de la PR #22.
- Las fases previas a la activación volvieron a pasar correctamente.
- El nuevo diagnóstico confirmó en los tres intentos `status=404` y ausencia de `WWW-Authenticate`. Home, blog y CSS sí pasaban sus smoke tests.
- La incompatibilidad se localizó en la construcción de la petición PSR-7 desde globals y el tratamiento de `Host` con `nyholm/psr7-server` en producción. La corrección `0ddebe9` sustituyó `ServerRequestCreator` por la fábrica PSR-17 de `php-http/discovery`, preservando un único `Host` válido.
- Se añadieron pruebas para el `Host` construido desde globals, el recorrido hasta el middleware PAT sin `Authorization` y el rechazo de hosts no permitidos.
- La release fue rechazada y el rollback automático restauró y verificó `dee2028`.

#### Despliegue final: release `16b98a7`, ejecución #240

- La PR #23 integró la corrección de compatibilidad HTTP y Composer.
- La ejecución instaló correctamente 28 paquetes de producción, incluido `mcp/sdk` `v0.8.1` y `php-http/discovery` `1.20.0`, sin `nyholm/psr7-server`.
- Pasaron la suite, PHPStan, el lint de diseño, el build de assets, la construcción y verificación del artefacto, la conexión SSH, la transferencia y la preparación remota.
- Tras activar la release pasaron home, blog, CSS y el smoke de `/mcp`, que confirmó el rechazo de una petición sin Bearer.
- Resultado: `16b98a7` quedó publicada y verificada.

## 7. Smoke test

### Aplicación y CD

- [x] Home pública.
- [x] Blog.
- [x] CSS publicado.
- [x] HTTPS.
- [x] Construcción reproducible desde `composer.lock`.
- [x] Checksum SHA-256.
- [x] Endpoint `/mcp` rechaza peticiones sin Bearer.
- [x] Rollback automático verificado durante los intentos fallidos.

### MCP Inspector

Se validó `https://benehom.es/mcp` mediante MCP Inspector, seleccionando Streamable HTTP y enviando un Bearer PAT sin registrar su valor.

- [x] Conexión HTTPS correcta.
- [x] Autenticación mediante PAT correcta.
- [x] Handshake MCP correcto.
- [x] `tools/list` correcto.
- [x] Exposición de `consultar_datos_financieros` confirmada.

### OpenCode

La conexión remota directa desde OpenCode produjo:

```text
SSE error: Non-200 status code (405)
```

En la versión/configuración de OpenCode utilizada durante esta validación, la conexión remota directa intentó negociar SSE y recibió HTTP 405. El MCP estaba operativo mediante Streamable HTTP, por lo que se utilizó `mcp-remote` como bridge local:

```text
OpenCode
  -> stdio
  -> mcp-remote
  -> Streamable HTTP
  -> https://benehom.es/mcp
```

`mcp-remote` se utilizó únicamente como adaptador local del cliente OpenCode durante la validación. No forma parte del runtime, dependencias ni infraestructura de producción de BeneHom.

Configuración conceptual, sin credenciales reales:

```json
{
  "benehom": {
    "type": "local",
    "command": [
      "npx",
      "-y",
      "mcp-remote@latest",
      "https://benehom.es/mcp",
      "--transport",
      "http-only",
      "--header",
      "Authorization: Bearer <PAT>"
    ],
    "enabled": true
  }
}
```

Con este recorrido OpenCode descubrió e invocó `consultar_datos_financieros` correctamente.

### Autenticación, revocación y rotación

1. PAT válido: OpenCode consultó correctamente datos financieros mediante el MCP.
2. PAT revocado: las consultas dejaron de funcionar.
3. Nuevo PAT: se actualizó la credencial, se reinició OpenCode para que el proceso MCP cargara el nuevo token y las consultas volvieron a funcionar.

Estas pruebas validaron autenticación mediante PAT, revocación efectiva, rotación de credenciales y consumo desde un cliente MCP real.

## 8. Incidencias

No registrar secretos, datos privados ni identificadores internos.

| Hora | Incidencia | Acción | Resultado |
| --- | --- | --- | --- |
| 07/10, 13:48 CEST | La release `2ced07a` superó CI, build y preparación, pero `/mcp` falló tres veces en el smoke sin diagnóstico de estado o cabecera. | Se relajaron comprobaciones no esenciales de cuerpo/caché y se aceptaron parámetros en el challenge Bearer. | El rollback restauró `dee2028`; el intento siguiente volvió a fallar, por lo que el ajuste no era la solución final. |
| 07/10, 16:17 CEST | La release `55f9039` volvió a fallar tres veces en `/mcp`; la causa seguía sin poder verificarse con el log disponible. | Se añadió diagnóstico de estado HTTP y `WWW-Authenticate` sin registrar cuerpos. | El rollback restauró `dee2028`; la siguiente ejecución pudo identificar el síntoma exacto. |
| 07/10, 17:21 CEST | La release `52d7978` recibió `404` sin `WWW-Authenticate` en los tres intentos de `/mcp`. | Se sustituyó la creación de peticiones de `nyholm/psr7-server` por la fábrica PSR-17 de `php-http/discovery`, se actualizó Composer y se añadieron pruebas de `Host` y middleware. | El rollback restauró `dee2028`; CI quedó verde y la release siguiente pasó el smoke MCP. |
| Validación manual posterior | En la versión/configuración utilizada durante esta validación, OpenCode directo devolvió `SSE error: Non-200 status code (405)` al intentar negociar SSE contra el endpoint Streamable HTTP. | Se configuró `mcp-remote` por stdio con `--transport http-only` y Bearer PAT. | OpenCode pudo descubrir e invocar la tool correctamente. |

## 9. Rollback

- [x] Release anterior `dee2028` disponible durante el despliegue.
- [x] Rollback automático ejecutado después de cada activación fallida.
- [x] Home, blog y CSS de la release restaurada verificados después de cada rollback.
- [x] La comprobación de una release anterior a MCP omite el smoke `/mcp`, evitando que el rollback falle por la ausencia esperada del endpoint.
- Procedimiento resumido: ante un smoke fallido, restaurar el symlink a la release estable anterior y validar sus endpoints públicos. La tabla de PAT es aditiva y la release anterior no la utiliza; no fue necesario revertir el schema durante los rollbacks observados.
- Un backup independiente de BD y su procedimiento de restauración no son verificables con la evidencia consultada.

## 10. Estado final

- La release `16b98a7` quedó publicada y activa en producción.
- El endpoint `https://benehom.es/mcp` quedó operativo mediante Streamable HTTP y HTTPS.
- La autenticación Bearer con PAT quedó validada con MCP Inspector y OpenCode.
- El handshake, `tools/list` y la exposición de `consultar_datos_financieros` quedaron confirmados.
- Una consulta financiera real desde OpenCode finalizó correctamente a través de `mcp-remote`.
- La revocación de un PAT impidió nuevas consultas y la rotación a un nuevo PAT restauró el acceso después de reiniciar OpenCode.
- Los tres despliegues rechazados conservaron el servicio mediante rollback automático a `dee2028`.
- El deployment se considera correcto y validado.
