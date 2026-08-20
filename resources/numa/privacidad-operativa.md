# Privacidad operativa de Gemini

Este documento es el control operativo de privacidad para Numa y registra las decisiones
de diseño y comprobaciones ya verificadas. No declara una validación ni una certificación
jurídica externa.

## Base jurídica elegida

El tratamiento realizado por Numa se basa en el artículo 6.1.b del RGPD: la ejecución
de la relación contractual y la prestación de la funcionalidad solicitada por la
persona usuaria. El tratamiento se inicia únicamente cuando decide usar Numa y formula
una consulta; BeneHom procesa y envía a Gemini solo los datos necesarios para responder
a esa solicitud concreta.

No se basa en un consentimiento específico para Gemini. La aceptación de la política
de privacidad informa sobre el tratamiento, pero no es la base que legitima
específicamente el uso de Gemini. Por este motivo, Numa no incorpora una casilla, modal
ni aceptación adicional antes de utilizar la funcionalidad.

## Datos y retención

- Gemini API se usa exclusivamente desde un proyecto de BeneHom con facturación activa
  y condiciones de servicios de pago aplicables.
- Solo se pueden enviar el mensaje validado, el contexto conversacional seleccionado por
  el servidor, documentación pública recuperada y el resultado financiero mínimo que sea
  imprescindible para responder. Nunca se envían correo, nombre, identificadores de
  usuario, SQL, tablas, columnas ni datos de otros usuarios.
- BeneHom no almacena preguntas, respuestas, prompts ni resultados de tools. El transcript
  vive solo en la sesión PHP y desaparece al finalizarla.
- La política de Gemini API indica que Google conserva prompts, contexto y resultados
  durante 55 días para supervisar y prevenir abuso, proteger la seguridad del servicio y
  cumplir obligaciones legales o regulatorias. Es un tratamiento separado de los logs
  opcionales del proyecto y debe figurar en la política de privacidad pública.

Fuentes de referencia:

- [Política de logs y datos de Gemini API](https://ai.google.dev/gemini-api/docs/logs-policy)
- [Políticas de uso y supervisión de abuso de Gemini API](https://ai.google.dev/gemini-api/docs/usage-policies)
- [Facturación y condiciones de servicio de pago de Gemini API](https://ai.google.dev/gemini-api/docs/billing)

## Comprobaciones verificadas

1. El proyecto de Gemini de Numa está en modalidad de pago, Nivel 1 / Prepago.
2. `NUMA_API_KEY` es la única clave de Gemini para generación y embeddings y está
   restringida a Gemini API.
3. En Google AI Studio están desactivados el almacenamiento de GenerateContent API y el
   almacenamiento de Interactions API. No existen datasets compartidos voluntariamente
   con Google para entrenamiento o mejora de modelos; la contribución voluntaria de datos
   y el logging opcional de prompts y respuestas permanecen desactivados.
4. El repositorio no envía `store: true` ni parámetros equivalentes que soliciten
   almacenamiento o logging por petición a Gemini.
5. La política de privacidad publicada informa de la finalidad, los datos mínimos, la
   base jurídica del artículo 6.1.b RGPD, la retención propia, la supervisión de abuso y
   los derechos aplicables.

La restricción por IP es un endurecimiento opcional para producción y no bloquea el
cierre de esta tarea. Solo se aplicará si el servidor dispone de una IP de salida estable.

## Rotación y revocación de claves

`NUMA_API_KEY` es la única clave de Gemini para generación y embeddings. No debe aparecer
en el repositorio, documentación, mensajes de error ni logs.

Rotación planificada:

1. Crear una clave de sustitución con la misma restricción mínima necesaria para ambos
   servicios de Gemini.
2. Sustituir `NUMA_API_KEY` en la configuración protegida del servidor.
3. Recargar el proceso PHP o desplegar la configuración y verificar `GET numa/status` sin
   enviar consultas reales ni datos de usuarios.
4. Revocar la clave anterior en Google cuando el cambio esté confirmado.

Ante sospecha de exposición:

1. Desactivar Numa mediante `NUMA_ENABLED=false` y, si procede, `NUMA_PUBLIC_ENABLED=false`.
2. Revocar de inmediato la clave afectada en Google; no esperar a completar la rotación.
3. Crear y restringir una nueva clave, actualizar `NUMA_API_KEY` y reiniciar el servicio.
4. Revisar repositorio, configuración desplegada y logs para localizar la exposición sin
   registrar ni copiar secretos. Evaluar y gestionar el incidente conforme a las
   obligaciones aplicables antes de reactivar Numa.
