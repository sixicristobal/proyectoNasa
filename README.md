![Logo ProyectoNASA](https://assets.spaceappschallenge.org/media/images/logoterereJape.2e16d0ba.fill-300x250.png)

ProyectoNASA 🌱🛰️
Sistema web (Laravel + Bootstrap) para agricultura regenerativa basado en datos de la NASA: monitoreo de cultivos, campos, rotaciones, imágenes satelitales, recomendaciones y alertas.

📋 Tabla de Contenidos
Descripción

Características

Requisitos

Instalación

Configuración

Desarrollo

Estructura del Proyecto

Rutas Principales

Funcionalidades

Tecnologías Utilizadas

Contribución

Licencia

📖 Descripción
ProyectoNASA es una plataforma web que integra datos satelitales y climatológicos proporcionados por la NASA para promover prácticas de agricultura regenerativa. La aplicación permite a agricultores y gestores de campos monitorear el estado de sus cultivos, planificar rotaciones, recibir recomendaciones inteligentes y visualizar imágenes satelitales actualizadas.

El objetivo principal es facilitar la toma de decisiones basadas en datos científicos, mejorando la sostenibilidad del suelo, la productividad y la resiliencia ante el cambio climático.

✨ Características
🛰️ Integración con datos de la NASA — Información satelital y meteorológica actualizada.

🌾 Gestión de campos y cultivos — Registro y monitoreo centralizado.

🔄 Planificación de rotaciones — Optimización de la salud del suelo.

🗺️ Visualización de imágenes satelitales — Análisis geoespacial de parcelas.

🤖 Chat con IA — Asistente inteligente para consultas agrícolas.

💡 Recomendaciones personalizadas — Basadas en datos y mejores prácticas regenerativas.

🔔 Alertas — Notificaciones sobre eventos climáticos o estados críticos.

🌳 Módulo de reforestación — Seguimiento de iniciativas de recuperación vegetal.

📊 Dashboard interactivo — Métricas clave y visualizaciones en tiempo real.

⚙️ Requisitos
Antes de comenzar, asegúrate de tener instalado:

PHP 8.3+

Composer (descargar)

Node.js 18+ y npm (descargar)

Base de datos: MySQL 8+, MariaDB

Git (opcional, para clonar el repositorio)

🚀 Instalación
Clona el repositorio:

git clone https://github.com/tu-usuario/ProyectoNASA.git
cd ProyectoNASA

Instala las dependencias de PHP y JavaScript:
composer install
npm install

Configura el archivo de entorno:
cp .env.example .env
php artisan key:generate
Ejecuta las migraciones:
php artisan migrate

Opcional: puedes poblar la base de datos con datos de prueba usando php artisan db:seed.

Compila los assets del frontend:

npm run build

Configuración
Edita el archivo .env según tu entorno:
APP_NAME=ProyectoNASA
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=mariadb
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=proyecto_nasa
DB_USERNAME=root
DB_PASSWORD=

# API de la NASA
NASA_API_KEY=tu_api_key_aqui
Puedes obtener una API Key gratuita en api.nasa.gov.

💻 Desarrollo
Para iniciar el entorno de desarrollo local, ejecuta los siguientes comandos en dos terminales separadas:

Terminal 1 — Servidor de assets (Vite):
npm run dev

Terminal 2 — Servidor de Laravel:

php artisan serve

Accede a la aplicación en: http://localhost:8000

Comandos útiles

# Ejecutar pruebas
php artisan test

# Formatear código PHP
./vendor/bin/pint

# Limpiar cachés
php artisan optimize:clear

# Re-ejecutar migraciones (⚠️ borra datos)
php artisan migrate:fresh --seed
📂 Estructura del Proyecto
text
ProyectoNASA/
├── app/
│   ├── Http/Controllers/     # Controladores de la aplicación
│   ├── Models/               # Modelos Eloquent
│   └── Services/             # Servicios (API NASA, IA, etc.)
├── database/
│   ├── migrations/           # Migraciones de la base de datos
│   └── seeders/              # Datos iniciales
├── resources/
│   ├── css/                  # Estilos Tailwind
│   ├── js/                   # Scripts (Vite)
│   └── views/                # Plantillas Blade
├── routes/
│   └── web.php               # Rutas web
├── public/                   # Archivos públicos
├── .env.example              # Plantilla de variables de entorno
├── composer.json             # Dependencias PHP
└── package.json              # Dependencias JS

🗺️ Rutas Principales
Ruta	Descripción
/	Dashboard con datos agrícolas actualizados
/imagenes	Visualización de imágenes satelitales
/datos-avanzados	Análisis avanzado de datos climáticos y agronómicos
/chat	Chat interactivo con asistente de IA
/recomendaciones	Recomendaciones personalizadas por campo
/reforestacion	Seguimiento de proyectos de reforestación
/rotaciones	Gestión y planificación de rotaciones de cultivos
🚀 Funcionalidades Principales
📊 Dashboard (/)
Panel central con métricas actualizadas del estado de los cultivos, alertas activas y resumen general del sistema.

🛰️ Imágenes Satelitales (/imagenes)
Visualización de imágenes de la NASA vinculadas a coordenadas específicas de campos y parcelas.

📈 Datos Avanzados (/datos-avanzados)
Análisis profundo de variables climáticas, índices de vegetación (NDVI) y otros indicadores agronómicos.

🤖 Chat IA (/chat)
Asistente conversacional para resolver dudas sobre prácticas regenerativas y recibir orientación personalizada.

💡 Recomendaciones (/recomendaciones)
Sistema de sugerencias basado en datos satelitales y el estado actual del campo.

🌳 Reforestación (/reforestacion)
Módulo para planificar, registrar y monitorear acciones de reforestación.

🔄 Rotaciones (/rotaciones)
Herramienta para diseñar rotaciones de cultivos que mejoren la fertilidad del suelo y reduzcan plagas.

🛠️ Tecnologías Utilizadas
Backend
Laravel — Framework PHP

Composer — Gestor de dependencias PHP

MySQL / Mariadb — Base de datos

Frontend
Bootstrap — Framework de estilos

Vite — Bundler de assets

Alpine.js (si aplica) — Interactividad ligera

Integraciones
NASA APIs — Datos satelitales y climáticos

Servicios de IA para el chat y las recomendaciones

🤝 Contribución
¡Las contribuciones son bienvenidas! Si deseas colaborar:


📄 Licencia
Este proyecto está bajo la licencia MIT. Consulta el archivo LICENSE para más información.
