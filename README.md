# BeneHom – Gestor de Economía Familiar

🌍 Aplicación en producción:
https://benehom.es

BeneHom es una aplicación web para la gestión de la economía familiar, desarrollada como proyecto individual dentro del CFGS de **Desarrollo de Aplicaciones Web (DAW)** y evolucionada hacia una aplicación estructurada y preparada para despliegue real.

El objetivo principal no es únicamente registrar ingresos y gastos, sino ayudar al usuario a comprender su comportamiento financiero y tomar decisiones más conscientes a partir de datos claros y visuales.

---

## 🎯 Objetivo del proyecto

BeneHom adopta un enfoque educativo y minimalista:

- Registro sencillo de ingresos y gastos.
- Separación clara entre gastos obligatorios y gastos voluntarios.
- Cálculo automático de:
  - Capacidad teórica de ahorro.
  - Ahorro real.
- Visualización gráfica de la evolución financiera.

La aplicación está diseñada para que el usuario entienda qué podría ahorrar frente a lo que realmente ahorra.

---

## 🧠 Enfoque funcional

La estructura financiera se divide en:

### 1️⃣ Ingresos

- Salario  
- Inversiones  
- Otros ingresos  

### 2️⃣ Gastos obligatorios

Costes necesarios para mantener el hogar:

- Vivienda  
- Suministros  
- Seguros  
- Alimentación básica  
- Transporte esencial  
- Impuestos  

### 3️⃣ Gastos voluntarios

Gastos no imprescindibles:

- Ocio  
- Suscripciones  
- Viajes  
- Restauración  
- Compras personales  

A partir de esta clasificación, BeneHom calcula automáticamente:

- Totales dinámicos mensuales.
- Capacidad de ahorro.
- Ahorro real.
- Evolución histórica (últimos meses).
- Gráficos comparativos y de tendencia.

---

## 🛠️ Tecnologías utilizadas

### Backend

- PHP 8.x  
- MySQL  
- Arquitectura MVC (implementación manual)  
- Protección CSRF  
- Gestión segura de sesiones  
- Configuración mediante variables de entorno (.env)  

### Frontend

- HTML5  
- CSS3 (estilos personalizados)  
- Bootstrap 5  
- JavaScript (Fetch API / AJAX)  
- Chart.js  
- Flatpickr (selector de mes)  

---

## 🏗️ Arquitectura

El proyecto sigue una arquitectura MVC estructurada manualmente:

```
/app
    /controllers
    /models
    /views
/config
/public
```

### Características técnicas relevantes:

- Separación clara entre lógica de negocio y presentación.
- Validaciones en frontend y backend.
- Manejo de errores preparado para entorno producción.
- Configuración desacoplada mediante variables de entorno.
- Protección contra CSRF en todos los formularios.

---

## 🔐 Seguridad implementada

- Protección CSRF en formularios.
- Sanitización de datos con htmlspecialchars.
- Gestión reforzada de sesiones.
- Control de acceso por autenticación.
- Uso de consultas preparadas con PDO (prepared statements) para prevenir inyecciones SQL.
- Variables sensibles fuera del repositorio (.env).

---

## 📱 Responsive

- Menú lateral adaptado a dispositivos móviles mediante Bootstrap Offcanvas.
- Diseño optimizado para evitar desbordamientos horizontales.
- Estructura fluida basada en grid de Bootstrap.

---

## 📦 Estado actual

- ✔ Aplicación completamente funcional.
- ✔ Sistema de autenticación operativo.
- ✔ Panel dinámico con gráficos y cálculos en tiempo real.
- ✔ Diseño responsive estable.
- ✔ Desplegada en entorno real (https://benehom.es).
- 🚧 Secciones futuras: Metas y Blog en desarrollo.

---

## 💻 Instalación en entorno local

### Requisitos

- PHP 8.x  
- MySQL  
- Servidor local (XAMPP, MAMP, Apache, etc.)  

### Configuración

1. Clonar el repositorio.
2. Crear archivo `.env` a partir de `.env.example`.
3. Configurar variables de entorno.
4. Importar la base de datos incluida en el proyecto (estructura + datos seed).

La aplicación estará disponible en entorno local tras configurar el servidor.

---

## 📈 Evolución futura

- Sistema de metas de ahorro.
- Panel comparativo anual.
- Exportación de datos.
- Mejora progresiva de seguridad en producción.
- Optimización continua de experiencia móvil.

---

## 👨‍💻 Autor

**Hiram González González**  
Desarrollador Web – CFGS Desarrollo de Aplicaciones Web (DAW)

Proyecto desarrollado de forma individual como aplicación real orientada a producción y portfolio profesional.
