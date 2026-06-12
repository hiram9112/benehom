/* =========================================================
   USUARIO DEMO
   ========================================================= */

/* email: demo@benehom.local / password: Demo1234 */

INSERT INTO usuarios (usuario, email, password)
VALUES (
  'Demo',
  'demo@benehom.local',
  '$2y$10$pT2ZldVudYp4asxUIkxGcOcEODMttIN/kumHzkuKjuwzy.E8rpzu6'
);


/* =========================================================
   INGRESOS - Usuario Demo (id = 1)
   ========================================================= */

INSERT INTO ingresos (usuario_id, categoria, cantidad, fecha) VALUES
-- Diciembre 2025
(1, 'salario', 1820.00, '2025-12-01'),
(1, 'actividad_propia', 240.00, '2025-12-01'),
(1, 'aportaciones_regalos', 180.00, '2025-12-01'),

-- Enero 2026
(1, 'salario', 1820.00, '2026-01-01'),
(1, 'ventas_segunda_mano', 95.00, '2026-01-01'),

-- Febrero 2026
(1, 'salario', 1820.00, '2026-02-01'),
(1, 'inversiones', 38.50, '2026-02-01'),

-- Marzo 2026
(1, 'salario', 1860.00, '2026-03-01'),
(1, 'actividad_propia', 160.00, '2026-03-01'),

-- Abril 2026
(1, 'salario', 1860.00, '2026-04-01'),
(1, 'alquileres', 320.00, '2026-04-01'),

-- Mayo 2026
(1, 'salario', 1860.00, '2026-05-01'),
(1, 'prestaciones_ayudas', 120.00, '2026-05-01'),
(1, 'otros', 75.00, '2026-05-01'),

-- Junio 2026
(1, 'salario', 1860.00, '2026-06-01'),
(1, 'actividad_propia', 210.00, '2026-06-01'),
(1, 'inversiones', 42.75, '2026-06-01');


/* =========================================================
   GASTOS ESENCIALES - Usuario Demo (id = 1)
   ========================================================= */

INSERT INTO gastos (usuario_id, tipo, categoria, cantidad, fecha) VALUES
-- Diciembre 2025
(1, 'esencial', 'alquiler_hipoteca', 720.00, '2025-12-01'),
(1, 'esencial', 'electricidad', 76.50, '2025-12-01'),
(1, 'esencial', 'agua', 31.20, '2025-12-01'),
(1, 'esencial', 'gas', 68.40, '2025-12-01'),
(1, 'esencial', 'internet_telefonia_basica', 44.90, '2025-12-01'),
(1, 'esencial', 'compra_basica', 385.00, '2025-12-01'),
(1, 'esencial', 'seguro_hogar', 22.00, '2025-12-01'),
(1, 'esencial', 'transporte_publico', 42.00, '2025-12-01'),

-- Enero 2026
(1, 'esencial', 'alquiler_hipoteca', 720.00, '2026-01-01'),
(1, 'esencial', 'electricidad', 69.80, '2026-01-01'),
(1, 'esencial', 'agua', 29.60, '2026-01-01'),
(1, 'esencial', 'gas', 72.10, '2026-01-01'),
(1, 'esencial', 'internet_telefonia_basica', 44.90, '2026-01-01'),
(1, 'esencial', 'compra_basica', 342.50, '2026-01-01'),
(1, 'esencial', 'salud_farmacia', 28.90, '2026-01-01'),
(1, 'esencial', 'transporte_publico', 42.00, '2026-01-01'),

-- Febrero 2026
(1, 'esencial', 'alquiler_hipoteca', 720.00, '2026-02-01'),
(1, 'esencial', 'electricidad', 64.20, '2026-02-01'),
(1, 'esencial', 'agua', 30.10, '2026-02-01'),
(1, 'esencial', 'gas', 61.50, '2026-02-01'),
(1, 'esencial', 'internet_telefonia_basica', 44.90, '2026-02-01'),
(1, 'esencial', 'compra_basica', 351.40, '2026-02-01'),
(1, 'esencial', 'mantenimiento_necesario_vehiculo', 84.00, '2026-02-01'),
(1, 'esencial', 'salud_farmacia', 19.70, '2026-02-01'),

-- Marzo 2026
(1, 'esencial', 'alquiler_hipoteca', 720.00, '2026-03-01'),
(1, 'esencial', 'electricidad', 58.70, '2026-03-01'),
(1, 'esencial', 'agua', 30.80, '2026-03-01'),
(1, 'esencial', 'gas', 49.90, '2026-03-01'),
(1, 'esencial', 'internet_telefonia_basica', 44.90, '2026-03-01'),
(1, 'esencial', 'compra_basica', 363.20, '2026-03-01'),
(1, 'esencial', 'seguro_vehiculo', 36.00, '2026-03-01'),
(1, 'esencial', 'educacion_material_escolar', 42.50, '2026-03-01'),

-- Abril 2026
(1, 'esencial', 'alquiler_hipoteca', 720.00, '2026-04-01'),
(1, 'esencial', 'electricidad', 54.30, '2026-04-01'),
(1, 'esencial', 'agua', 32.00, '2026-04-01'),
(1, 'esencial', 'gas', 38.60, '2026-04-01'),
(1, 'esencial', 'internet_telefonia_basica', 44.90, '2026-04-01'),
(1, 'esencial', 'compra_basica', 372.10, '2026-04-01'),
(1, 'esencial', 'comunidad', 54.00, '2026-04-01'),
(1, 'esencial', 'salud_farmacia', 24.30, '2026-04-01'),

-- Mayo 2026
(1, 'esencial', 'alquiler_hipoteca', 720.00, '2026-05-01'),
(1, 'esencial', 'electricidad', 57.80, '2026-05-01'),
(1, 'esencial', 'agua', 31.40, '2026-05-01'),
(1, 'esencial', 'gas', 34.20, '2026-05-01'),
(1, 'esencial', 'internet_telefonia_basica', 44.90, '2026-05-01'),
(1, 'esencial', 'compra_basica', 368.90, '2026-05-01'),
(1, 'esencial', 'seguro_medico', 59.90, '2026-05-01'),
(1, 'esencial', 'combustible_trabajo_estudios', 86.40, '2026-05-01'),

-- Junio 2026
(1, 'esencial', 'alquiler_hipoteca', 720.00, '2026-06-01'),
(1, 'esencial', 'electricidad', 62.90, '2026-06-01'),
(1, 'esencial', 'agua', 33.10, '2026-06-01'),
(1, 'esencial', 'gas', 29.80, '2026-06-01'),
(1, 'esencial', 'internet_telefonia_basica', 44.90, '2026-06-01'),
(1, 'esencial', 'compra_basica', 381.60, '2026-06-01'),
(1, 'esencial', 'transporte_publico', 42.00, '2026-06-01'),
(1, 'esencial', 'urgencias_esenciales_hogar', 96.00, '2026-06-01');


/* =========================================================
   GASTOS FLEXIBLES - Usuario Demo (id = 1)
   ========================================================= */

INSERT INTO gastos (usuario_id, tipo, categoria, cantidad, fecha) VALUES
-- Diciembre 2025
(1, 'flexible', 'restaurantes_bares_cafeterias', 186.00, '2025-12-01'),
(1, 'flexible', 'comida_domicilio', 74.50, '2025-12-01'),
(1, 'flexible', 'ocio_entretenimiento', 132.00, '2025-12-01'),
(1, 'flexible', 'regalos', 255.00, '2025-12-01'),
(1, 'flexible', 'streaming_contenido_digital', 39.98, '2025-12-01'),
(1, 'flexible', 'viajes_escapadas', 210.00, '2025-12-01'),
(1, 'flexible', 'combustible_personal', 64.20, '2025-12-01'),

-- Enero 2026
(1, 'flexible', 'restaurantes_bares_cafeterias', 74.00, '2026-01-01'),
(1, 'flexible', 'comida_domicilio', 28.90, '2026-01-01'),
(1, 'flexible', 'ocio_entretenimiento', 46.00, '2026-01-01'),
(1, 'flexible', 'ropa_calzado', 58.40, '2026-01-01'),
(1, 'flexible', 'streaming_contenido_digital', 29.98, '2026-01-01'),
(1, 'flexible', 'combustible_personal', 41.50, '2026-01-01'),

-- Febrero 2026
(1, 'flexible', 'restaurantes_bares_cafeterias', 92.60, '2026-02-01'),
(1, 'flexible', 'comida_domicilio', 35.20, '2026-02-01'),
(1, 'flexible', 'ocio_entretenimiento', 68.00, '2026-02-01'),
(1, 'flexible', 'cuidado_personal_estetica', 44.00, '2026-02-01'),
(1, 'flexible', 'streaming_contenido_digital', 29.98, '2026-02-01'),
(1, 'flexible', 'combustible_personal', 53.70, '2026-02-01'),
(1, 'flexible', 'compras_online_marketplace', 39.90, '2026-02-01'),

-- Marzo 2026
(1, 'flexible', 'restaurantes_bares_cafeterias', 118.30, '2026-03-01'),
(1, 'flexible', 'comida_domicilio', 42.50, '2026-03-01'),
(1, 'flexible', 'ocio_entretenimiento', 83.00, '2026-03-01'),
(1, 'flexible', 'gimnasio_deporte_bienestar', 35.00, '2026-03-01'),
(1, 'flexible', 'streaming_contenido_digital', 29.98, '2026-03-01'),
(1, 'flexible', 'combustible_personal', 57.10, '2026-03-01'),
(1, 'flexible', 'eventos_planes_sociales', 48.00, '2026-03-01'),

-- Abril 2026
(1, 'flexible', 'restaurantes_bares_cafeterias', 126.40, '2026-04-01'),
(1, 'flexible', 'comida_domicilio', 46.80, '2026-04-01'),
(1, 'flexible', 'ocio_entretenimiento', 76.00, '2026-04-01'),
(1, 'flexible', 'tecnologia_electronica', 145.00, '2026-04-01'),
(1, 'flexible', 'streaming_contenido_digital', 29.98, '2026-04-01'),
(1, 'flexible', 'viajes_escapadas', 118.00, '2026-04-01'),
(1, 'flexible', 'combustible_personal', 60.40, '2026-04-01'),

-- Mayo 2026
(1, 'flexible', 'restaurantes_bares_cafeterias', 109.70, '2026-05-01'),
(1, 'flexible', 'comida_domicilio', 39.60, '2026-05-01'),
(1, 'flexible', 'ocio_entretenimiento', 88.00, '2026-05-01'),
(1, 'flexible', 'ropa_calzado', 96.50, '2026-05-01'),
(1, 'flexible', 'streaming_contenido_digital', 29.98, '2026-05-01'),
(1, 'flexible', 'combustible_personal', 62.30, '2026-05-01'),
(1, 'flexible', 'donaciones_aportaciones', 25.00, '2026-05-01'),

-- Junio 2026
(1, 'flexible', 'restaurantes_bares_cafeterias', 138.20, '2026-06-01'),
(1, 'flexible', 'comida_domicilio', 52.40, '2026-06-01'),
(1, 'flexible', 'ocio_entretenimiento', 94.00, '2026-06-01'),
(1, 'flexible', 'vacaciones', 340.00, '2026-06-01'),
(1, 'flexible', 'streaming_contenido_digital', 29.98, '2026-06-01'),
(1, 'flexible', 'combustible_personal', 67.80, '2026-06-01'),
(1, 'flexible', 'otros_gastos_restauracion', 31.50, '2026-06-01');
