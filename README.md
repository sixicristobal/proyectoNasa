![Logo ProyectoNASA](https://assets.spaceappschallenge.org/media/images/logoterereJape.2e16d0ba.fill-300x250.png)

# ProyectoNASA

Sistema web desarrollado con Laravel y Bootstrap para la gestión de agricultura regenerativa, basado en datos satelitales y climáticos proporcionados por la NASA. Permite el monitoreo de cultivos y campos, la planificación de rotaciones, la visualización de imágenes satelitales, así como la generación de recomendaciones y alertas.


## Autores

Este proyecto fue desarrollado por alumnos de la **Universidad Evangélica del Paraguay (UEP)**:

- Sixinio Cristobal
- Bruno Duran
- Ever Vega
- Juan Gutierrez
- Dahiana Cardozo
- Elias Vera


## Tabla de Contenidos

- [Descripción](#descripción)
- [Características](#características)
- [Requisitos](#requisitos)
- [Instalación](#instalación)
- [Configuración](#configuración)
- [Desarrollo](#desarrollo)
- [Estructura del Proyecto](#estructura-del-proyecto)
- [Rutas Principales](#rutas-principales)
- [Funcionalidades](#funcionalidades)
- [Tecnologías Utilizadas](#tecnologías-utilizadas)
- [Contribución](#contribución)
- [Licencia](#licencia)

## Descripción

ProyectoNASA es una plataforma web que integra datos satelitales y climatológicos proporcionados por la NASA para promover prácticas de agricultura regenerativa. La aplicación permite a agricultores y gestores de campos monitorear el estado de sus cultivos, planificar rotaciones, recibir recomendaciones inteligentes y visualizar imágenes satelitales actualizadas.

El objetivo principal es facilitar la toma de decisiones basada en datos científicos, mejorando la sostenibilidad del suelo, la productividad y la resiliencia ante el cambio climático.

## Características

- Integración con datos de la NASA: información satelital y meteorológica actualizada.
- Gestión de campos y cultivos: registro y monitoreo centralizado.
- Planificación de rotaciones: optimización de la salud del suelo.
- Visualización de imágenes satelitales: análisis geoespacial de parcelas.
- Chat con IA: asistente inteligente para consultas agrícolas.
- Recomendaciones personalizadas: basadas en datos y mejores prácticas regenerativas.
- Alertas: notificaciones sobre eventos climáticos o estados críticos.
- Módulo de reforestación: seguimiento de iniciativas de recuperación vegetal.
- Dashboard interactivo: métricas clave y visualizaciones en tiempo real.

## Requisitos

Antes de comenzar, asegúrate de tener instalado:

- PHP 8.3 o superior
- Composer
- Node.js 18 o superior y npm
- Base de datos: MySQL 8 o superior / MariaDB
- Git (opcional, para clonar el repositorio)

## Instalación

1. Clona el repositorio:

```bash
git clone https://github.com/sixicristobal/proyectoNasa.git
cd ProyectoNASA

2- Instala las dependencias de PHP y JavaScript:
composer install
npm install

3- Configura el archivo de entorno:
cp .env.example .env
php artisan key:generate

4- Ejecuta las migraciones:
php artisan migrate

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

Estructura del Proyecto
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

Rutas Principales
Ruta 	                    Descripción 
/	                        Dashboard con datos agrícolas actualizados
/imagenes	                Visualización de imágenes satelitales
/datos-avanzados	        Análisis avanzado de datos climáticos y agronómicos
/chat	                    Chat interactivo con asistente de IA
/recomendaciones	        Recomendaciones personalizadas por campo
/reforestacion	            Seguimiento de proyectos de reforestación
/rotaciones	                Gestión y planificación de rotaciones de cultivos
Funcionalidades             Principales
Dashboard (/)               Panel central con métricas actualizadas del estado de los                         cultivos, alertas activas y resumen general del sistema.
Imágenes Satelitales (/imagenes)    Visualización de imágenes de la NASA vinculadas a                           coordenadas específicas de campos y parcelas.
Datos Avanzados (/datos-avanzados) Análisis profundo de variables climáticas, índices de                          vegetación (NDVI) y otros indicadores agronómicos.
Chat IA (/chat)             Asistente conversacional para resolver dudas sobre prácticas                   regenerativas y recibir orientación personalizada.
Recomendaciones (/recomendaciones)
Sistema de sugerencias basado en datos satelitales y el estado actual del campo.
Reforestación (/reforestacion)
Módulo para planificar, registrar y monitorear acciones de reforestación.
Rotaciones (/rotaciones)
Herramienta para diseñar rotaciones de cultivos que mejoren la fertilidad del suelo y reduzcan plagas.

Tecnologías Utilizadas
Backend
Laravel — Framework PHP
Composer — Gestor de dependencias PHP
MySQL / MariaDB — Base de datos

Frontend
Bootstrap — Framework de estilos
Vite — Bundler de assets
Alpine.js (si aplica) — Interactividad ligera

Integraciones
NASA APIs — Datos satelitales y climáticos
Servicios de IA para el chat y las recomendaciones
