<?php

return [
    'obligatorio' => [
        'vivienda' => [
            'label' => 'Vivienda',
            'help' => 'Pagos relacionados con mantener tu vivienda principal.',
            'items' => [
                'alquiler_hipoteca' => 'Alquiler o hipoteca',
                'comunidad' => 'Comunidad',
                'ibi_tasas_hogar' => 'IBI, tasas y tributos del hogar',
                'seguro_hogar' => 'Seguro de hogar',
                'mantenimiento_esencial_hogar' => 'Mantenimiento esencial del hogar',
            ],
        ],
        'suministros' => [
            'label' => 'Suministros',
            'help' => 'Servicios habituales para que el hogar funcione cada mes.',
            'items' => [
                'agua' => 'Agua',
                'electricidad' => 'Electricidad',
                'gas' => 'Gas',
                'internet_telefonia_basica' => 'Internet y telefonía básica',
            ],
        ],
        'alimentacion_hogar' => [
            'label' => 'Alimentación y hogar',
            'help' => 'Compras necesarias para cubrir la vida diaria del hogar.',
            'items' => [
                'compra_basica' => 'Compra básica de alimentación',
                'limpieza_higiene_hogar' => 'Limpieza e higiene del hogar',
                'equipamiento_basico_hogar' => 'Equipamiento básico del hogar',
            ],
        ],
        'salud_cuidados' => [
            'label' => 'Salud y cuidados',
            'help' => 'Gastos necesarios para salud, farmacia y cuidados básicos.',
            'items' => [
                'salud_farmacia' => 'Salud y farmacia',
                'seguro_medico' => 'Seguro médico',
                'mascotas_alimentacion_salud' => 'Alimentación y salud de mascotas',
            ],
        ],
        'familia_educacion' => [
            'label' => 'Familia y educación',
            'help' => 'Pagos relacionados con cuidado, educación y apoyo familiar.',
            'items' => [
                'cuidado_infantil' => 'Cuidado infantil',
                'alimentacion_higiene_infantil' => 'Alimentación e higiene infantil',
                'educacion_material_escolar' => 'Educación y material escolar',
                'ayuda_familiar_cuidados' => 'Ayuda familiar o cuidados',
            ],
        ],
        'transporte_necesario' => [
            'label' => 'Transporte necesario',
            'help' => 'Desplazamientos necesarios para trabajar, estudiar o mantener obligaciones familiares.',
            'items' => [
                'transporte_publico' => 'Transporte público',
                'combustible_trabajo_estudios' => 'Combustible por trabajo o estudios',
                'mantenimiento_necesario_vehiculo' => 'Mantenimiento necesario del vehículo',
                'seguro_vehiculo' => 'Seguro del vehículo',
            ],
        ],
        'trabajo_obligaciones' => [
            'label' => 'Trabajo y obligaciones',
            'help' => 'Pagos necesarios para trabajar o cumplir compromisos formales.',
            'items' => [
                'gastos_trabajo' => 'Gastos necesarios de trabajo',
                'cuota_autonomo' => 'Cuota de autónomo o actividad profesional',
                'impuestos_tasas' => 'Impuestos y tasas',
                'comisiones_bancarias_necesarias' => 'Comisiones bancarias necesarias',
                'pagos_familiares_obligatorios' => 'Pagos familiares obligatorios',
            ],
        ],
        'urgencias' => [
            'label' => 'Urgencias',
            'help' => 'Situaciones puntuales necesarias para mantener la estabilidad del hogar.',
            'items' => [
                'urgencias_esenciales_hogar' => 'Urgencias esenciales del hogar',
                'otro_gasto_base' => 'Otro gasto base',
            ],
        ],
    ],
    'voluntario' => [
        'ocio' => [
            'label' => 'Ocio',
            'help' => 'Planes y actividades que forman parte del tiempo libre.',
            'items' => [
                'ocio_entretenimiento' => 'Ocio y entretenimiento',
                'eventos_planes_sociales' => 'Eventos y planes sociales',
                'juegos_loteria_apuestas' => 'Juegos, lotería o apuestas',
            ],
        ],
        'restauracion' => [
            'label' => 'Restauración',
            'help' => 'Consumo de comida o bebida fuera de la compra básica del hogar.',
            'items' => [
                'restaurantes_bares_cafeterias' => 'Restaurantes, bares y cafeterías',
                'comida_domicilio' => 'Comida a domicilio',
            ],
        ],
        'suscripciones_bienestar' => [
            'label' => 'Suscripciones y bienestar',
            'help' => 'Servicios recurrentes que puedes revisar si necesitas ganar margen.',
            'items' => [
                'streaming_contenido_digital' => 'Streaming y contenido digital',
                'apps_software_servicios' => 'Apps, software y servicios online',
                'gimnasio_deporte_bienestar' => 'Gimnasio, deporte y bienestar',
            ],
        ],
        'compras' => [
            'label' => 'Compras',
            'help' => 'Compras personales o del hogar que no forman parte de la base mensual.',
            'items' => [
                'ropa_calzado' => 'Ropa y calzado',
                'cuidado_personal_estetica' => 'Cuidado personal y estética',
                'tecnologia_electronica' => 'Tecnología y electrónica',
                'decoracion_hogar_no_esencial' => 'Decoración y mejoras no esenciales del hogar',
                'compras_online_marketplace' => 'Compras online y marketplace',
                'regalos' => 'Regalos',
            ],
        ],
        'viajes' => [
            'label' => 'Viajes',
            'help' => 'Desplazamientos y estancias asociados a ocio o descanso.',
            'items' => [
                'viajes_escapadas' => 'Viajes y escapadas',
                'vacaciones' => 'Vacaciones',
            ],
        ],
        'movilidad_personal' => [
            'label' => 'Movilidad personal',
            'help' => 'Desplazamientos de uso personal o no imprescindibles.',
            'items' => [
                'combustible_personal' => 'Combustible de uso personal',
                'taxi_vtc_movilidad' => 'Taxi, VTC y movilidad ocasional',
            ],
        ],
        'financiacion' => [
            'label' => 'Financiación',
            'help' => 'Pagos mensuales derivados de decisiones de consumo ya adquiridas.',
            'items' => [
                'compras_financiadas' => 'Compras financiadas',
                'prestamo_consumo' => 'Préstamo personal de consumo',
                'tecnologia_financiada' => 'Móvil o tecnología financiada',
                'renting_financiacion_vehiculo' => 'Renting o financiación de vehículo',
            ],
        ],
        'aportaciones_otros' => [
            'label' => 'Aportaciones y otros',
            'help' => 'Aportaciones o consumos que no encajan en los grupos anteriores.',
            'items' => [
                'donaciones_aportaciones' => 'Donaciones y aportaciones voluntarias',
                'consumos_personales' => 'Consumos personales',
                'otro_gasto_ajustable' => 'Otro gasto ajustable',
            ],
        ],
    ],
];
