# OpenJobs v14 funcional

OpenJobs es una plataforma web de transparencia laboral donde trabajadores, ex trabajadores y estudiantes pueden compartir y consultar experiencias reales sobre empresas. También incluye perfiles empresariales, publicación de vacantes, reputación laboral, gamificación, IA con DeepSeek y notificaciones tipo LinkedIn.

## Qué incluye
- Registro e inicio de sesión normal
- Inicio de sesión con Google OAuth
- Perfiles por rol: talento, empresa, administrador y soporte
- Perfil editable con foto y CV
- Publicación y gestión de vacantes
- Postulaciones y mensajería interna
- Reseñas con estrellas y moderación por IA
- Dashboard por rol con checklist y métricas
- AI Studio: recomendaciones de vacantes, análisis de CV, mejora de perfiles
- Notificaciones en tiempo real por polling AJAX
- Página de inicio con enfoque real de OpenJobs

## Requisitos
- XAMPP o entorno con PHP 8+
- MySQL / MariaDB
- Extensión cURL habilitada en PHP

## Instalación rápida
1. Copia la carpeta `openjobs` dentro de `htdocs`
2. Importa `database.sql` en phpMyAdmin en una base llamada `openjobs`
3. Revisa `config/config.php` y agrega tu API key de DeepSeek
4. Abre en el navegador: `http://localhost/openjobs/public/`

## Configuración de DeepSeek
1. Obtén una API key en https://platform.deepseek.com/api_keys
2. Edita `config/config.php` y cambia:
   ```php
   define('DEEPSEEK_API_KEY', 'tu-api-key-aqui');