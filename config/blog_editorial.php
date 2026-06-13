<?php

return [
    'linea_editorial' => [
        'proposito' => 'Cada artículo explica un concepto financiero cotidiano con lenguaje de hogar y termina con una aplicación práctica dentro de BeneHom.',
        'tono' => [
            'cercano',
            'claro',
            'calmado',
            'profesional sin rigidez',
            'aplicable a decisiones domésticas',
        ],
        'evitar' => [
            'tecnicismos innecesarios',
            'tono bancario',
            'alarmismo',
            'promesas de rentabilidad',
            'recomendaciones de productos concretos',
        ],
        'cierre' => 'El campo conexion es requerido y debe vincular el tema con una funcionalidad real: dashboard, metas, proyecciones o gráficos.',
    ],
    'categorias_oficiales' => [
        'ahorro' => 'Ahorro',
        'gastos' => 'Gastos',
        'metas' => 'Metas',
        'proyecciones' => 'Proyecciones',
        'habitos' => 'Hábitos',
        'inflacion' => 'Inflación',
        'hipotecas' => 'Hipotecas',
        'conceptos_basicos' => 'Conceptos básicos',
        'activos_financieros' => 'Activos financieros',
    ],
    'estructura_articulo' => [
        'secciones' => 3,
        'parrafos_por_seccion' => 2,
        'lectura_min' => [
            'minimo' => 4,
            'maximo' => 6,
        ],
        'resumen' => 'Una frase que anticipe el aprendizaje práctico del artículo.',
        'campos_requeridos' => [
            'slug',
            'categoria',
            'titulo',
            'resumen',
            'fecha',
            'estado',
            'lectura_min',
            'destacado',
            'icono',
            'contenido',
            'conexion',
        ],
    ],
    'auditoria_articulos_existentes' => [
        'inflacion-y-presupuesto-del-hogar' => [
            'terminologia' => 'Usa gastos esenciales, gastos flexibles y ahorro real de forma alineada con el producto.',
            'tono' => 'Explica la inflación sin alarmismo y conecta la lectura con decisiones mensuales del hogar.',
            'estructura' => 'Cumple 3 secciones, 2 párrafos por sección, resumen de una frase, lectura de 4 minutos y conexion requerida.',
            'conexion' => 'Dashboard: comparación de ahorro real y gastos flexibles entre meses.',
        ],
        'hipotecas-cuota-y-decision-familiar' => [
            'terminologia' => 'Usa gastos esenciales y ahorro real como referencias funcionales del producto.',
            'tono' => 'Mantiene una orientación calmada y práctica sin inducir decisiones hipotecarias concretas.',
            'estructura' => 'Cumple 3 secciones, 2 párrafos por sección, resumen de una frase, lectura de 5 minutos y conexion requerida.',
            'conexion' => 'Dashboard: registro de gastos esenciales y revisión del ahorro real antes de simular una cuota.',
        ],
        'activos-financieros-basicos' => [
            'terminologia' => 'Usa ahorro real y gastos esenciales antes de hablar de proyecciones o inversión.',
            'tono' => 'Es educativo y prudente: no promete rentabilidad ni recomienda productos concretos.',
            'estructura' => 'Cumple 3 secciones, 2 párrafos por sección, resumen de una frase, lectura de 5 minutos y conexion requerida.',
            'conexion' => 'Proyecciones: revisión previa del ahorro real antes de estimar escenarios educativos.',
        ],
    ],
];
