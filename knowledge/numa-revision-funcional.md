# Revision funcional del conocimiento de Numa

Fecha de revision: 2026-08-12

Este registro interno aprueba el corpus documental para la futura indexacion de produccion. No forma parte de `knowledge/numa/` para no convertirse en un fragmento recuperable.

## Documentos de producto aprobados

| Documento | Estado | Revision funcional |
| --- | --- | --- |
| `introduccion.md` | Aprobado | Describe el ambito de Numa, la sesion y la privacidad sin prometer analisis fuera de ingresos, gastos y movimientos. |
| `dashboard.md` | Aprobado | Refleja el selector mensual, la cascada, los indicadores y los graficos disponibles. |
| `movimientos.md` | Aprobado | Refleja el alta, la correccion y la eliminacion manual de movimientos, sin atribuir esas acciones a Numa. |
| `gastos.md` | Aprobado | Distingue gastos esenciales y flexibles, y describe el top de categorias y sus simulaciones educativas. |
| `ahorro.md` | Aprobado tras correccion | El ahorro real negativo no se atribuye a una fuente de financiacion ni se usa para diagnosticar una causa que BeneHom no calcula. |
| `metas.md` | Aprobado | Describe metas y ahorro mensual como simulaciones que no modifican los movimientos reales. |
| `proyecciones.md` | Aprobado | Describe escenarios educativos, inflacion e hipoteca conforme a los calculos implementados y sus limites. |
| `cuenta.md` | Aprobado | Refleja cambio de contrasena, exportacion, enlaces legales y eliminacion manual de la cuenta. |
| `preguntas-frecuentes.md` | Aprobado | Mantiene los contratos vigentes de Numa, periodos mensuales, fuentes internas y acciones no permitidas. |

## Articulos del blog aprobados

La seleccion se reviso a partir de `ArticuloBlog::publicados()`. Cada articulo aprobado declara en `config/blog_articulos.php` `rag_pertinente = true` y `rag_aprobado = true`; `ArticuloBlog::publicadosParaRag()` exige esas dos marcas ademas de `estado = publicado`. Un articulo nuevo publicado queda fuera del RAG hasta que la revision funcional/editorial active ambas marcas.

| Slug | Alcance aprobado |
| --- | --- |
| `como-hacer-un-presupuesto-familiar` | Presupuesto familiar y uso del Dashboard. |
| `regla-50-30-20` | Referencia educativa para ingresos, esenciales, flexibles y ahorro. |
| `como-ahorrar-dinero-cada-mes` | Habitos de ahorro y diferencia entre ahorro posible y real. |
| `vivir-por-debajo-de-tus-posibilidades` | Margen presupuestario y relacion con Dashboard y Proyecciones. |
| `cuanto-puedes-ahorrar-cada-mes` | Explicacion educativa de ahorro posible y ahorro real. |
| `gastos-fijos-y-variables` | Diferencia educativa entre gastos fijos, variables, esenciales y flexibles. |
| `metas-de-ahorro-como-cumplirlas` | Planificacion de metas sin recomendar una aportacion concreta. |
| `que-es-el-interes-compuesto` | Interes compuesto y escenarios educativos sin prometer rentabilidad. |
| `gastos-hormiga` | Habitos de gasto flexible y lectura del top de categorias. |
| `fondo-de-emergencia` | Educacion financiera general vinculada a metas y ahorro real. |
| `que-es-la-inflacion` | Inflacion y lectura mensual de gastos esenciales. |
| `cuanta-hipoteca-puedes-pagar` | Uso educativo de la calculadora de hipoteca, sin asesoramiento personalizado. |
| `como-empezar-a-invertir-desde-cero` | Educacion general y advertencias; no habilita recomendaciones de inversion. |
| `tipos-de-hipoteca-fija-variable-mixta` | Educacion general y uso de la calculadora de hipoteca, sin recomendar una opcion. |

## Solapamientos y prioridad

- Los nueve Markdown son la fuente funcional para explicar el comportamiento de BeneHom.
- Los articulos del blog complementan conceptos de economia familiar y enlaces con el producto; no sustituyen la documentacion funcional cuando exista solapamiento.
- Los articulos sobre inversion e hipoteca son educativos. Numa debe conservar sus rechazos a recomendaciones de inversion, productos financieros o decisiones personalizadas.
- No se copia contenido del blog a `knowledge/numa/`. `config/blog_articulos.php` sigue siendo la fuente canonica de sus textos y de su estado de publicacion.
- El selector para RAG expone solo slug, titulo, resumen, intencion de busqueda, secciones y conexion con BeneHom. Excluye estado, iconos, fecha, lectura, destacado y demas campos de presentacion.
