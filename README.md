![Logo ProyectoNASA](https://assets.spaceappschallenge.org/media/images/logoterereJape.2e16d0ba.fill-300x250.png)

# Field Shift: Adapting Farms with NASA Data

**"Empowering local farmers in Paraguay to fight climate change through regenerative agriculture, powered by NASA's Earth observation data."**

## Authors

This project was developed by students from **Universidad Evangélica del Paraguay (UEP)**:

- Sixinio Cristóbal
- Bruno Duran
- Ever Vega
- Juan Gutierrez
- Dahiana Cardozo
- Elias Vera

## Table of Contents

- [The Problem](#the-problem)
- [The Solution](#the-solution)
- [How We Used NASA Data](#how-we-used-nasa-data)
- [AI Integration](#ai-integration)
- [Features](#features)
- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
- [Project Structure](#project-structure)
- [Main Routes](#main-routes)
- [Technologies Used](#technologies-used)
- [Our Hackathon Journey](#our-hackathon-journey)
- [License](#license)

## The Problem: Why Field Shift?

In regions like Paraguay, small and medium-scale farmers face unpredictable climate shifts, soil degradation, and water scarcity. Traditional farming relies on historical intuition, which is no longer effective against rapid climate change. 

We discovered that farmers lack access to real-time, actionable environmental data. While NASA provides incredible satellite observations, the raw data is too complex for an average farmer to interpret. They need a bridge between high-level satellite data and daily field decisions.

## The Solution

Field Shift is a Laravel-based web platform that acts as an intelligent translator. It fetches raw satellite data (precipitation, soil moisture, surface temperature) and processes it into a simple, actionable dashboard for regenerative agriculture.

### Who Benefits?
1. **Local Farmers:** They receive daily alerts, crop rotation plans, and AI-driven recommendations tailored to their specific plots without needing a degree in data science.
2. **Agricultural Consultants (Agroecologists):** They can monitor multiple fields using advanced vegetation indices (NDVI) and satellite imagery to provide better guidance.
3. **The Environment:** By promoting regenerative practices (like optimized crop rotation and reforestation), the system helps restore soil health and sequester carbon.

## How We Used NASA Data

Our project heavily relies on NASA's Earth observation APIs. We built dedicated backend services to fetch and process this data:

*   **NASA POWER (Prediction of Worldwide Energy Resources):** Used to gather daily meteorological data and historical climate patterns for specific farm coordinates.
*   **GPM (Global Precipitation Measurement):** We extract near real-time precipitation data to alert farmers about drought risks or heavy rainfall.
*   **SMAP (Soil Moisture Active Passive):** Critical for understanding soil moisture levels, allowing our system to recommend optimal planting or irrigation times.
*   **Landsat & MODIS:** Used to calculate vegetation indices (like NDVI) to monitor crop health and identify stress areas in the fields.
*   **ECOSTRESS & SRTM:** Integrated for surface temperature monitoring and topographical context.

All NASA data is parsed, processed, and stored directly as JSON in our MariaDB database for rapid querying and analysis.

## AI Integration for Actionable Insights

We integrated an AI assistant that reads the NASA data stored in our MariaDB. Instead of showing a farmer a raw temperature graph, the AI analyzes the data and provides conversational advice, such as: *"Based on the SMAP soil moisture data from the last 3 days, wait until Thursday to plant your cover crops."* 

## Features

- **NASA Data Integration:** Up-to-date satellite and meteorological information.
- **Farm and Crop Management:** Centralized registration and monitoring.
- **Crop Rotation Planning:** Optimizing soil health.
- **Satellite Imagery Visualization:** Geospatial analysis of plots.
- **AI Chat:** Intelligent assistant for agricultural queries.
- **Personalized Recommendations:** Based on data and regenerative best practices.
- **Alerts:** Notifications about climatic events or critical states.
- **Reforestation Module:** Tracking plant recovery initiatives.
- **Interactive Dashboard:** Key metrics and real-time visualizations.

## Requirements

Before starting, ensure you have the following installed:

- PHP 8.3 or higher
- Composer
- Node.js 18 or higher and npm
- Database: MariaDB (required for native JSON storage) / MySQL 8+
- Git (optional, to clone the repository)

## Installation

1. Clone the repository:

```bash
git clone [https://github.com/sixicristobal/proyectoNasa.git](https://github.com/sixicristobal/proyectoNasa.git)
cd ProyectoNASA