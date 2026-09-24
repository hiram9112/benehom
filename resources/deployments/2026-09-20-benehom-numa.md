# Registro operativo: primer despliegue de BeneHom + Numa

Este registro no debe contener secretos, credenciales, datos privados ni identificadores internos de producción.

## 1. Identificación

- Fecha: 2026-09-20
- Rama: `main`
- SHA desplegado: `8f6c6954b538293dfc8c3af70b95431cc34ac162`
- Estado CI: verde

## 2. Estado previo y backups

- [x] Backup pre-deploy del código disponible
- [x] Backup pre-deploy de la BD disponible
- [x] Versión V1 conservada
- [x] Rollback disponible

## 3. Servidor

- Proveedor: Hostinger
- Servidor web: LiteSpeed
- PHP: 8.3.33
- [x] OPcache activo
- [x] DocumentRoot verificado
- [x] `.env` fuera del DocumentRoot
- [x] HTTPS activo

## 4. Base de datos

- Motor/versión: MariaDB 11.8.9
- [x] BD nueva de producción preparada
- [x] Schema aplicado: `database/schema.sql` (14 tablas)
- [x] Seed no aplicado, cuando corresponda
- [x] Usuario de aplicación con privilegios mínimos (`SELECT`, `INSERT`, `UPDATE` y `DELETE`)
- [x] Conexión real validada

## 5. Servicios externos

- [x] Correo transaccional preparado
- [x] SPF, DKIM y DMARC verificados
- Proveedor de IA: Gemini
- Modelo: `gemini-3.1-flash-lite`
- Embeddings: `gemini-embedding-001` (768 dimensiones)
- Logging/datasets: desactivados en Gemini
- Presupuesto: Google Cloud, 5 €/mes; límite de gasto de AI Studio, 7 €/mes
- Alertas: 50 %, 75 %, 90 % y 100 % del presupuesto de Google Cloud
- Límites: 15 consultas por usuario y día; 200 llamadas globales al proveedor por día y 1000 por mes; bypass desactivado
- Estado inicial de Numa: desactivado

## 6. Pasos del deploy

- [x] Confirmar SHA final
- [x] Confirmar CI verde
- [x] Construir artefacto con `composer release:build`
- [x] Verificar checksum SHA-256 del artefacto
- [x] Subir el artefacto de release al servidor
- [x] Verificar checksum SHA-256 en el servidor
- [x] Extraer la release
- [x] Comprobar permisos
- [x] Confirmar carga del `.env`
- [x] Conectar la versión a la BD de producción
- [x] Poner la versión en servicio
- [x] Ejecutar la indexación RAG, cuando corresponda (`php bin/indexar-numa.php`)
- [x] Comprobar el índice RAG, cuando corresponda (97 fragmentos)
- [x] Activar Numa después de validar la aplicación
- [x] Activar Numa público solo mediante decisión expresa

## 7. Smoke test

- [x] Home pública
- [x] HTTPS
- [x] Redirección al dominio canónico
- [x] Registro
- [x] Email de verificación
- [x] Enlace de verificación bajo el dominio correcto
- [x] Login/logout
- [x] Recuperación de contraseña
- [x] Email de reset
- [x] Enlace de reset bajo el dominio correcto
- [x] Dashboard
- [x] Ingresos
- [x] Gastos
- [x] Metas
- [x] Proyecciones
- [x] Conexión con BD
- [x] Numa
- [x] Numa público, solo si se activa
- [x] RAG
- [x] Límites/cuotas

## 8. Incidencias

No registrar secretos, datos privados ni identificadores internos.

| Hora | Incidencia | Acción | Resultado |
| --- | --- | --- | --- |
| 24/09, hora no registrada | Se alcanzó el límite global diario de 100 llamadas tras la indexación RAG y la primera consulta de Numa. | Se verificó el consumo registrado y se elevó el límite global diario a 200, manteniendo el límite mensual de 1000 y el bypass desactivado. | Numa volvió a responder; el límite diario quedó en 200, se restablecerá a 100 pasadas 24h. |

## 9. Rollback

- [x] Backup de código disponible
- [x] Backup de BD disponible
- [x] Versión V1 disponible
- Procedimiento resumido: retirar el enlace público hacia V2, restaurar el directorio público de V1 y comprobar su funcionamiento; V1 conserva su configuración y BD. No fue necesario ejecutarlo.