<div align="center">
  <div style="width:64px;height:64px;border-radius:16px;background:#10b981;color:#fff;font-size:32px;font-weight:700;display:inline-flex;align-items:center;justify-content:center;margin-bottom:8px">A</div>
  <h1>Alejandro Lopez | Portal de Clientes</h1>
  <p><strong>SPA modular para gestión de clientes, proyectos y servicios</strong></p>
  <p>
    <img src="https://img.shields.io/badge/PHP-8.2-777BB4?logo=php" alt="PHP">
    <img src="https://img.shields.io/badge/MySQL-8.0-4479A1?logo=mysql" alt="MySQL">
    <img src="https://img.shields.io/badge/JavaScript-ES6-F7DF1E?logo=javascript" alt="JS">
    <img src="https://img.shields.io/badge/license-MIT-green" alt="License">
  </p>
</div>

---

## Características

- **Dos roles:** Administrador (panel completo) y Cliente (vista de sus proyectos)
- **Multi-proyecto:** Cada cliente puede tener múltiples proyectos con hitos personalizables
- **Servicios por proyecto:** Gestión de servicios con estados, fechas y precios
- **Identidad de marca:** Paleta de colores, logotipo y recursos visuales por proyecto
- **Registro de clientes:** Formulario con verificación por correo electrónico (código de 6 dígitos)
- **Persistencia:** Base de datos MySQL con API REST en PHP
- **Responsive:** Diseño adaptable a móvil, tablet y escritorio
- **Tema oscuro/claro:** Alternancia con persistencia en localStorage

## Tecnologías

| Capa | Tecnología |
|------|-----------|
| Frontend | JavaScript (ES6) + CSS3 — SPA con enrutamiento por hash |
| Backend | PHP 8.2 — API RESTful con PDO |
| Base de datos | PostgreSQL 17 (Supabase) — 6 tablas relacionales |
| Correo | PHPMailer con SMTP de Gmail |
| Servidor | Apache (XAMPP) / Supabase | 8.2 — API RESTful |
| Base de datos | MySQL 8.0 / MariaDB 10.4 |
| Correo | PHPMailer con SMTP de Gmail |
| Servidor | Apache (XAMPP) |

## Estructura del proyecto

```
portal-cliente/
├── index.html              # Shell HTML con loading spinner
├── js/
│   └── app.js              # SPA completa (~1200 líneas)
├── css/
│   └── styles.css          # Estilos con variables CSS + theme
├── api/
│   ├── config.php          # Conexión MySQL y CORS
│   ├── index.php           # Endpoints REST (init, get-all, save-clients,
│   │                       #   save-settings, login, register, verify, resend-code)
│   ├── mailer.php          # Envío de correos con PHPMailer
│   ├── mail_config.php     # Configuración SMTP
│   └── vendor/             # Dependencias (PHPMailer)
└── database/
    └── init.sql            # Esquema de base de datos
```

## Instalación local

### Requisitos

- [XAMPP](https://www.apachefriends.org/) (Apache + PHP + MySQL)
- [Composer](https://getcomposer.org/) (para PHPMailer)

### Pasos

1. Clonar el repositorio:
   ```bash
   git clone https://github.com/lopez-torres-alejandro/portal-cliente.git
   ```

2. Copiar a `C:\xampp\htdocs\portal-cliente\` (o la ruta de tu servidor web)

3. Instalar dependencias de PHP:
   ```bash
   cd api
   composer install
   ```

4. Iniciar Apache y MySQL desde el panel de XAMPP

5. Inicializar la base de datos desde el navegador:
   ```
   http://localhost/portal-cliente/api/index.php?action=init
   ```

6. Abrir el portal:
   ```
   http://localhost/portal-cliente/
   ```

### Configurar envío de correos

1. Activar verificación en dos pasos en tu cuenta de Google
2. Generar contraseña de aplicación en: https://myaccount.google.com/apppasswords
3. Editar `api/mail_config.php`:
   ```php
   define('SMTP_USER', 'tu-correo@gmail.com');
   define('SMTP_PASS', 'tu-contraseña-de-aplicación');
   ```

## API Endpoints

| Método | Acción | Descripción |
|--------|--------|-------------|
| GET | `?action=init` | Crear tablas y sembrar datos iniciales |
| GET | `?action=get-all` | Obtener toda la configuración y clientes |
| POST | `?action=save-clients` | Guardar clientes (`{clients: [...]}`) |
| POST | `?action=save-settings` | Guardar configuración (`{key, value}`) |
| POST | `?action=login` | Iniciar sesión (`{email, password}`) |
| POST | `?action=register` | Registrar cliente (`{company, email, password, ruc, phone}`) |
| POST | `?action=verify` | Verificar código (`{email, code}`) |
| POST | `?action=resend-code` | Reenviar código (`{email}`) |

## Credenciales demo

| Rol | Correo | Contraseña |
|-----|--------|------------|
| Admin | `admin@developer.com` | Cualquiera |
| Cliente demo | `contacto@virgomotosport.com` | `virmoto2026` |

## Seguridad

- Contraseñas hasheadas con bcrypt (`password_hash`)
- Códigos de verificación de 6 dígitos con expiración de 15 minutos
- Rate limiting: máximo 3 solicitudes de código por hora por email
- Validación de contraseña: mínimo 8 caracteres, 1 mayúscula, 1 número
- Sanitización de entradas con `filter_var`
- Conexiones parametrizadas con prepared statements (SQL injection prevention)

## Licencia

MIT © [Alejandro Lopez](https://github.com/lopez-torres-alejandro)