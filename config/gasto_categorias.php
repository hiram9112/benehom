<?php

return [
    'esencial' => [
        'vivienda' => [
            'label' => 'Vivienda',
            'help' => 'Pagos relacionados con mantener tu vivienda principal.',
            'items' => [
                'alquiler_hipoteca' => 'Alquiler o hipoteca',
                'comunidad' => 'Comunidad',
                'ibi_tasas_hogar' => 'IBI, tasas y tributos del hogar',
                'seguro_hogar' => 'Seguro de hogar',
                'mantenimiento_esencial_hogar' => 'Mantenimiento esencial del hogar',
                'otros_gastos_vivienda' => 'Otros gastos de vivienda',
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
                'otros_gastos_suministros' => 'Otros gastos de suministros',
            ],
        ],
        'alimentacion_hogar' => [
            'label' => 'Alimentación y hogar',
            'help' => 'Compras necesarias para cubrir la vida diaria del hogar.',
            'items' => [
                'compra_basica' => 'Compra básica de alimentación',
                'limpieza_higiene_hogar' => 'Limpieza e higiene del hogar',
                'equipamiento_basico_hogar' => 'Equipamiento básico del hogar',
                'otros_gastos_alimentacion_hogar' => 'Otros gastos de alimentación y hogar',
            ],
        ],
        'salud_cuidados' => [
            'label' => 'Salud y cuidados',
            'help' => 'Gastos necesarios para salud, farmacia y cuidados básicos.',
            'items' => [
                'salud_farmacia' => 'Salud y farmacia',
                'seguro_medico' => 'Seguro médico',
                'mascotas_alimentacion_salud' => 'Alimentación y salud de mascotas',
                'otros_gastos_salud_cuidados' => 'Otros gastos de salud y cuidados',
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
                'otros_gastos_familia_educacion' => 'Otros gastos de familia y educación',
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
                'otros_gastos_transporte_necesario' => 'Otros gastos de transporte necesario',
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
                'pagos_familiares_esenciales' => 'Pagos familiares esenciales',
                'otros_gastos_trabajo_obligaciones' => 'Otros gastos de trabajo y obligaciones',
            ],
        ],
        'urgencias' => [
            'label' => 'Urgencias',
            'help' => 'Situaciones puntuales necesarias para mantener la estabilidad del hogar.',
            'items' => [
                'urgencias_esenciales_hogar' => 'Urgencias esenciales del hogar',
                'otro_gasto_esencial' => 'Otro gasto esencial',
                'otros_gastos_urgencias' => 'Otros gastos de urgencias',
            ],
        ],
    ],
    'flexible' => [
        'ocio' => [
            'label' => 'Ocio',
            'help' => 'Planes y actividades que forman parte del tiempo libre.',
            'items' => [
                'ocio_entretenimiento' => 'Ocio y entretenimiento',
                'eventos_planes_sociales' => 'Eventos y planes sociales',
                'juegos_loteria_apuestas' => 'Juegos, lotería o apuestas',
                'otros_gastos_ocio' => 'Otros gastos de ocio',
            ],
        ],
        'restauracion' => [
            'label' => 'Restauración',
            'help' => 'Consumo de comida o bebida fuera de la compra básica del hogar.',
            'items' => [
                'restaurantes_bares_cafeterias' => 'Bares y restaurantes',
                'comida_domicilio' => 'Comida a domicilio',
                'otros_gastos_restauracion' => 'Otros gastos de restauración',
            ],
        ],
        'suscripciones_bienestar' => [
            'label' => 'Suscripciones y bienestar',
            'help' => 'Servicios recurrentes que puedes revisar si necesitas ganar margen.',
            'items' => [
                'streaming_contenido_digital' => 'Streaming y contenido digital',
                'apps_software_servicios' => 'Apps, software y servicios online',
                'gimnasio_deporte_bienestar' => 'Gimnasio, deporte y bienestar',
                'otros_gastos_suscripciones_bienestar' => 'Otros gastos de suscripciones y bienestar',
            ],
        ],
        'compras' => [
            'label' => 'Compras',
            'help' => 'Compras personales o del hogar que no forman parte de los gastos esenciales mensuales.',
            'items' => [
                'ropa_calzado' => 'Ropa y calzado',
                'cuidado_personal_estetica' => 'Cuidado personal y estética',
                'tecnologia_electronica' => 'Tecnología y electrónica',
                'decoracion_hogar_no_esencial' => 'Decoración y mejoras no esenciales del hogar',
                'compras_online_marketplace' => 'Compras online y marketplace',
                'regalos' => 'Regalos',
                'otros_gastos_compras' => 'Otros gastos de compras',
            ],
        ],
        'viajes' => [
            'label' => 'Viajes',
            'help' => 'Desplazamientos y estancias asociados a ocio o descanso.',
            'items' => [
                'viajes_escapadas' => 'Viajes y escapadas',
                'vacaciones' => 'Vacaciones',
                'otros_gastos_viajes' => 'Otros gastos de viajes',
            ],
        ],
        'movilidad_personal' => [
            'label' => 'Movilidad personal',
            'help' => 'Desplazamientos de uso personal o no imprescindibles.',
            'items' => [
                'combustible_personal' => 'Combustible de uso personal',
                'taxi_vtc_movilidad' => 'Taxi, VTC y movilidad ocasional',
                'otros_gastos_movilidad_personal' => 'Otros gastos de movilidad personal',
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
                'otros_gastos_financiacion' => 'Otros gastos de financiación',
            ],
        ],
        'aportaciones_otros' => [
            'label' => 'Aportaciones y otros',
            'help' => 'Aportaciones o consumos que no encajan en los grupos anteriores.',
            'items' => [
                'donaciones_aportaciones' => 'Donaciones y aportaciones libres',
                'consumos_personales' => 'Consumos personales',
                'otro_gasto_flexible' => 'Otro gasto flexible',
                'otros_gastos_aportaciones_otros' => 'Otros gastos de aportaciones y otros',
            ],
        ],
    ],
];
