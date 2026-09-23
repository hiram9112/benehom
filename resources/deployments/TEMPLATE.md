# Plantilla de registro operativo de despliegue

Este registro no debe contener secretos, credenciales, datos privados ni identificadores internos de producción.

## 1. Identificación

- Fecha: ____________________
- Rama: ____________________
- SHA desplegado: ____________________
- Estado CI: ____________________

## 2. Estado previo y backups

- [ ] Backup pre-deploy del código disponible
- [ ] Backup pre-deploy de la BD disponible
- [ ] Versión anterior conservada
- [ ] Rollback disponible

## 3. Servidor

- Proveedor: ____________________
- Servidor web: ____________________
- PHP: ____________________
- [ ] OPcache activo
- [ ] DocumentRoot verificado
- [ ] `.env` fuera del DocumentRoot
- [ ] HTTPS activo

## 4. Base de datos

- Motor/versión: ____________________
- [ ] BD de producción preparada
- [ ] Schema aplicado: ____________________
- [ ] Seed no aplicado, cuando corresponda
- [ ] Usuario de aplicación con privilegios mínimos
- [ ] Conexión real validada

## 5. Servicios externos

- [ ] Correo transaccional preparado
- [ ] SPF, DKIM y DMARC verificados

- Proveedor de IA: ____________________
- Modelo: ____________________
- Embeddings: ____________________
- Logging/datasets: ____________________
- Presupuesto: ____________________
- Alertas: ____________________
- Límites: ____________________
- Estado inicial de Numa: ____________________

## 6. Pasos del deploy

- [ ] Confirmar SHA final
- [ ] Confirmar CI verde
- [ ] Construir artefacto con `composer release:build`
- [ ] Verificar checksum SHA-256 del artefacto
- [ ] Subir el artefacto de release al servidor
- [ ] Verificar checksum SHA-256 en el servidor
- [ ] Extraer la release
- [ ] Comprobar permisos
- [ ] Confirmar carga del `.env`
- [ ] Conectar la versión a la BD de producción
- [ ] Poner la versión en servicio
- [ ] Ejecutar la indexación RAG, cuando corresponda
- [ ] Comprobar el índice RAG, cuando corresponda
- [ ] Activar Numa después de validar la aplicación
- [ ] Activar Numa público solo mediante decisión expresa

## 7. Smoke test

- [ ] Home pública
- [ ] HTTPS
- [ ] Redirección al dominio canónico
- [ ] Registro
- [ ] Email de verificación
- [ ] Enlace de verificación bajo el dominio correcto
- [ ] Login/logout
- [ ] Recuperación de contraseña
- [ ] Email de reset
- [ ] Enlace de reset bajo el dominio correcto
- [ ] Dashboard
- [ ] Ingresos
- [ ] Gastos
- [ ] Metas
- [ ] Proyecciones
- [ ] Conexión con BD
- [ ] Numa
- [ ] Numa público, solo si se activa
- [ ] RAG
- [ ] Límites/cuotas

## 8. Incidencias

No registrar secretos, datos privados ni identificadores internos.

| Hora | Incidencia | Acción | Resultado |
| --- | --- | --- | --- |
|  |  |  |  |

## 9. Rollback

- [ ] Backup de código disponible
- [ ] Backup de BD disponible
- [ ] Versión anterior disponible
- Procedimiento resumido: ____________________