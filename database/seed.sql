/* =========================================================
   USUARIO DEMO
   ========================================================= */

/*email:demo@benehom.local//////password:Demo1234*/   

INSERT INTO usuarios (usuario, email, password)
VALUES (
  'Demo',
  'demo@benehom.local',
  '$2y$10$pT2ZldVudYp4asxUIkxGcOcEODMttIN/kumHzkuKjuwzy.E8rpzu6'
);



/* =========================================================
   INGRESOS – Usuario Demo (id = 1)
   ========================================================= */
INSERT INTO ingresos (usuario_id, categoria, cantidad, fecha) VALUES
-- Abril 2025
(1, 'salario', 1500, '2025-04-01'),
(1, 'otros',    200, '2025-04-01'),

-- Mayo 2025
(1, 'salario', 1500, '2025-05-01'),
(1, 'otros',    180, '2025-05-01'),

-- Junio 2025
(1, 'salario', 1500, '2025-06-01'),
(1, 'otros',    250, '2025-06-01'),

-- Julio 2025
(1, 'salario', 1500, '2025-07-01'),
(1, 'otros',    210, '2025-07-01'),

-- Agosto 2025
(1, 'salario', 1500, '2025-08-01'),
(1, 'otros',    170, '2025-08-01'),

-- Septiembre 2025
(1, 'salario', 1600, '2025-09-01'),
(1, 'otros',    220, '2025-09-01');



/* =========================================================
   GASTOS OBLIGATORIOS – Usuario Demo
   ========================================================= */
INSERT INTO gastos (usuario_id, tipo, categoria, cantidad, fecha) VALUES
-- Abril 2025
(1, 'obligatorio', 'vivienda',       650, '2025-04-01'),
(1, 'obligatorio', 'luz',              55, '2025-04-01'),
(1, 'obligatorio', 'agua',             28, '2025-04-01'),
(1, 'obligatorio', 'gas',               40, '2025-04-01'),
(1, 'obligatorio', 'internet',          30, '2025-04-01'),
(1, 'obligatorio', 'supermercado',     290, '2025-04-01'),

-- Mayo 2025
(1, 'obligatorio', 'vivienda',       650, '2025-05-01'),
(1, 'obligatorio', 'luz',              60, '2025-05-01'),
(1, 'obligatorio', 'agua',             30, '2025-05-01'),
(1, 'obligatorio', 'gas',               42, '2025-05-01'),
(1, 'obligatorio', 'internet',          30, '2025-05-01'),
(1, 'obligatorio', 'supermercado',     305, '2025-05-01'),

-- Junio 2025
(1, 'obligatorio', 'vivienda',       650, '2025-06-01'),
(1, 'obligatorio', 'luz',              65, '2025-06-01'),
(1, 'obligatorio', 'agua',             35, '2025-06-01'),
(1, 'obligatorio', 'gas',               38, '2025-06-01'),
(1, 'obligatorio', 'internet',          30, '2025-06-01'),
(1, 'obligatorio', 'supermercado',     315, '2025-06-01'),

-- Julio 2025
(1, 'obligatorio', 'vivienda',       650, '2025-07-01'),
(1, 'obligatorio', 'luz',              70, '2025-07-01'),
(1, 'obligatorio', 'agua',             30, '2025-07-01'),
(1, 'obligatorio', 'gas',               35, '2025-07-01'),
(1, 'obligatorio', 'internet',          30, '2025-07-01'),
(1, 'obligatorio', 'supermercado',     310, '2025-07-01'),

-- Agosto 2025
(1, 'obligatorio', 'vivienda',       650, '2025-08-01'),
(1, 'obligatorio', 'luz',              60, '2025-08-01'),
(1, 'obligatorio', 'agua',             28, '2025-08-01'),
(1, 'obligatorio', 'gas',               42, '2025-08-01'),
(1, 'obligatorio', 'internet',          30, '2025-08-01'),
(1, 'obligatorio', 'supermercado',     300, '2025-08-01'),

-- Septiembre 2025
(1, 'obligatorio', 'vivienda',       650, '2025-09-01'),
(1, 'obligatorio', 'luz',              60, '2025-09-01'),
(1, 'obligatorio', 'agua',             30, '2025-09-01'),
(1, 'obligatorio', 'gas',               40, '2025-09-01'),
(1, 'obligatorio', 'internet',          30, '2025-09-01'),
(1, 'obligatorio', 'supermercado',     310, '2025-09-01');



/* =========================================================
   GASTOS VOLUNTARIOS – Usuario Demo
   ========================================================= */
INSERT INTO gastos (usuario_id, tipo, categoria, cantidad, fecha) VALUES
-- Abril 2025
(1, 'voluntario', 'comidas_fuera',      55, '2025-04-01'),
(1, 'voluntario', 'pedir_comida',       35, '2025-04-01'),
(1, 'voluntario', 'ocio',               60, '2025-04-01'),
(1, 'voluntario', 'combustible',        85, '2025-04-01'),

-- Mayo 2025
(1, 'voluntario', 'comidas_fuera',      60, '2025-05-01'),
(1, 'voluntario', 'pedir_comida',       40, '2025-05-01'),
(1, 'voluntario', 'ocio',               65, '2025-05-01'),
(1, 'voluntario', 'combustible',        90, '2025-05-01'),

-- Junio 2025
(1, 'voluntario', 'comidas_fuera',     200, '2025-06-01'),
(1, 'voluntario', 'pedir_comida',      135, '2025-06-01'),
(1, 'voluntario', 'ocio',              180, '2025-06-01'),
(1, 'voluntario', 'combustible',        90, '2025-06-01'),

-- Julio 2025
(1, 'voluntario', 'comidas_fuera',      65, '2025-07-01'),
(1, 'voluntario', 'pedir_comida',       40, '2025-07-01'),
(1, 'voluntario', 'ocio',               75, '2025-07-01'),
(1, 'voluntario', 'combustible',        95, '2025-07-01'),

-- Agosto 2025
(1, 'voluntario', 'comidas_fuera',      60, '2025-08-01'),
(1, 'voluntario', 'pedir_comida',       35, '2025-08-01'),
(1, 'voluntario', 'ocio',               70, '2025-08-01'),
(1, 'voluntario', 'combustible',        90, '2025-08-01'),

-- Septiembre 2025
(1, 'voluntario', 'comidas_fuera',     260, '2025-09-01'),
(1, 'voluntario', 'pedir_comida',       40, '2025-09-01'),
(1, 'voluntario', 'ocio',               75, '2025-09-01'),
(1, 'voluntario', 'combustible',       105, '2025-09-01');
