# EsportHire – Esports Recruitment & Community Platform

## Overview

This project was developed as part of the PIDEV – 3rd Year Engineering Program at **Esprit School of Engineering** (Academic Year 2025–2026).

EsportHire is an advanced esports web platform designed to connect gamers with professional teams. It provides a complete ecosystem including player recruitment, community interaction, event management, and intelligent matchmaking powered by AI technologies.

## Features

* 🎮 Player recruitment system for esports teams
* 🤖 AI-powered matching between players and teams
* 💬 Forum section for community discussions
* 📅 Event management and participation system
* 📢 Job offers and team opportunities
* 🔍 Advanced search and filtering system
* 🧠 AI-based analytics and recommendations

## Tech Stack

### Frontend

* Bootstrap

### Backend

* Symfony (PHP)

### Database

* MySQL

### AI & Additional Technologies

* Python (AI processing, recommendation systems)

## Architecture

The platform follows a client-server architecture with a modular backend structure using Symfony.
AI components developed in Python are integrated to provide smart recommendations and analytics features.

## Contributors

* Iyed Trabelsi
* Aziz Ben Amor
* Ghassen Barbouch
* Yasser Chebbi
* Achref Reguai

## Academic Context

Developed at **Esprit School of Engineering – Tunisia**
PIDEV – 3A | 2025–2026

## Getting Started

### Prerequisites

* PHP >= 8.x
* Composer
* MySQL
* Python 3.x

### Installation

```bash
git clone https://github.com/your-username/your-repository
cd your-repository
composer install
```

### Database Setup

* Create a MySQL database
* Configure `.env` file with your database credentials

```bash
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate
```

### Run the Project

```bash
symfony server:start
```

### Run AI Services (Python)

```bash
python app.py
```

## Acknowledgments

We would like to thank our professors and mentors at Esprit School of Engineering for their guidance and support throughout this project.

Special thanks to the open-source community for the tools and technologies used in this project.
