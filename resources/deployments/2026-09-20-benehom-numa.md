# Registro operativo: primer despliegue de BeneHom + Numa

Este registro no debe contener secretos, credenciales, datos privados ni identificadores internos de producción.

## 1. Identificación

- Fecha: 2026-09-20
- Rama: `main`
- SHA desplegado: pendiente de confirmar
- Estado CI: pendiente de confirmar

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
- [x] `database/schema.sql` aplicado
- [x] 14 tablas creadas
- [x] `database/seed.sql` no aplicado
- [x] Usuario de aplicación limitado a `SELECT`, `INSERT`, `UPDATE` y `DELETE`
- [ ] Conexión real de V2 con la BD nueva validada

## 5. Servicios externos

- [x] Correo transaccional del dominio preparado
- [x] SPF, DKIM y DMARC verificados
- Proveedor de IA: Gemini
- [x] Gemini API configurada
- Modelo: `gemini-3.1-flash-lite`
- Embeddings: `gemini-embedding-001`
- Logging/datasets: desactivados
- Presupuesto de Google Cloud: 5 €
- Alertas: 50 %, 75 %, 90 % y 100 %
- Límite de gasto de AI Studio: 7 €
- Estado inicial de Numa: desactivado

## 6. Pasos del deploy

- [ ] Confirmar SHA final
- [ ] Confirmar CI verde
- [ ] Publicar código
- [ ] Instalar dependencias de producción
- [ ] Ejecutar build
- [ ] Comprobar permisos
- [ ] Confirmar carga del `.env`
- [ ] Conectar la versión a la BD de producción
- [ ] Poner la versión en servicio
- [ ] Ejecutar `composer numa:index`
- [ ] Comprobar índice RAG
- [ ] Activar Numa después de validar BeneHom
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

- [x] Backup de código disponible
- [x] Backup de BD disponible
- [x] Versión V1 disponible
- Procedimiento resumido: ____________________
- Resultado, si se ejecuta: ____________________
