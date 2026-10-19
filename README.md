<div align="center">
  <div style="width:64px;height:64px;border-radius:16px;background:#e8a45b;color:#111311;font-size:32px;font-weight:700;display:inline-flex;align-items:center;justify-content:center;margin-bottom:8px">A</div>
  <h1>Alejandro Lopez | Portal de Clientes</h1>
  <p><strong>SPA de gestión de clientes, proyectos y servicios con API modular</strong></p>
  <p>
    <img src="https://img.shields.io/badge/PHP-8.2%2B-777BB4?logo=php" alt="PHP">
    <img src="https://img.shields.io/badge/PostgreSQL-17-4169E1?logo=postgresql" alt="PostgreSQL">
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
- **Persistencia:** PostgreSQL con API REST en PHP/PDO
- **Responsive:** Diseño adaptable a móvil, tablet y escritorio
- **Tema oscuro/claro:** Alternancia con persistencia en localStorage

## Tecnologías

| Capa | Tecnología |
|------|-----------|
| Frontend | JavaScript (ES6) + CSS3 — SPA con enrutamiento por hash |
| Backend | PHP 8.2+ — API RESTful con PDO |
| Base de datos | PostgreSQL 17 (Supabase) — 6 tablas relacionales |
| Correo | PHPMailer con SMTP de Gmail |
| Servidor | Apache + PHP; PostgreSQL alojado localmente o en Supabase |

## Estructura del proyecto

```
portal-cliente/
├── index.html              # Shell HTML con loading spinner
├── js/
│   └── app.js              # SPA sin proceso de compilación
├── css/
│   └── styles.css          # Estilos con variables CSS + theme
├── api/
│   ├── index.php           # Router HTTP y control de acceso
│   ├── bootstrap.php       # Sesión, respuestas JSON y helpers comunes
│   ├── routes/             # Módulos de autenticación, clientes y registro
│   ├── setup.php           # Inicialización de consola; no es un endpoint web
│   ├── config.example.php  # Conexión PostgreSQL desde variables de entorno
│   ├── mailer.php          # Envío de correos con PHPMailer
│   └── vendor/             # Dependencias instaladas con Composer
└── database/
    └── init.sql            # Fuente única del esquema PostgreSQL
```

## Configuración obligatoria

1. Copia `api/config.example.php` como `api/config.php`.
2. Define estas variables en PHP-FPM o Apache. Para Supabase, copia el host y usuario que aparecen en **Connect** en el panel del proyecto:

```bash
PORTAL_DB_HOST=db.<project-ref>.supabase.co
PORTAL_DB_PORT=5432
PORTAL_DB_NAME=postgres
PORTAL_DB_USER=postgres.<project-ref>
PORTAL_DB_PASSWORD=<contraseña-de-la-base>
PORTAL_DB_SSLMODE=require
PORTAL_ADMIN_EMAIL=<correo-del-administrador>
PORTAL_ADMIN_PASSWORD=<contraseña-de-12-a-72-bytes>
```

3. Aplica `database/init.sql` una vez en el SQL Editor de Supabase o con `psql` usando la conexión de PostgreSQL. Después, desde la raíz del proyecto, ejecuta `php api/setup.php` para crear el administrador si aún no existe uno con contraseña. La inicialización ya no está disponible como endpoint HTTP.

En una base existente, antes de aplicar el esquema comprueba que no haya correos duplicados solo por mayúsculas/minúsculas; el índice nuevo los rechazará:

```sql
SELECT lower(email), count(*) FROM clients GROUP BY lower(email) HAVING count(*) > 1;
SELECT lower(email), count(*) FROM admin_credentials GROUP BY lower(email) HAVING count(*) > 1;
```

Si el servidor solo tiene IPv4, usa en las variables de conexión el host, usuario y puerto del **Session pooler** que entrega Supabase. Si TLS termina en un proxy y PHP no detecta HTTPS, define `PORTAL_COOKIE_SECURE=true`.

Para agregar clientes de muestra en una instalación vacía, ejecuta `php api/setup.php --seed-demo`. No ejecutes esa opción en producción.

En PostgreSQL local, configura `PORTAL_DB_SSLMODE=disable` si el servidor no ofrece SSL. No guardes contraseñas en el repositorio. Para registro por correo, crea `api/mail_config.php` desde `api/mail_config.example.php`, instala PHPMailer y configura el SMTP.

El registro requiere además `api/mail_config.php`, PHPMailer instalado y SMTP configurado. No se muestra el código de verificación en respuestas públicas.

## Instalación local

### Requisitos

- Apache o PHP-FPM; PHP 8.2 o superior con PDO_PGSQL habilitado
- PostgreSQL local o una base Supabase
- [Composer](https://getcomposer.org/) (para PHPMailer)

### Pasos

1. Clonar el repositorio:
   ```bash
   git clone https://github.com/lopez-torres-alejandro/portal-cliente.git
   ```

2. Copiar a la raíz del servidor web (ej: `htdocs/portal-cliente/`)

3. Crear `api/config.php` desde `api/config.example.php` y definir las variables de entorno de PostgreSQL y del administrador.

4. Instalar dependencias de PHP:
   ```bash
   composer install --working-dir=api
   ```

5. Aplicar el esquema en PostgreSQL (o en el SQL Editor de Supabase) y crear el primer administrador:
   ```bash
   psql "host=<host-de-Connect> port=<puerto> dbname=<base> user=<usuario> sslmode=require" -v ON_ERROR_STOP=1 -f database/init.sql
   php api/setup.php
   ```

6. Si activarás el registro, crea `api/mail_config.php` desde su plantilla. PHP debe poder escribir en su directorio temporal del sistema, que guarda las verificaciones fuera del directorio público.

7. Inicia Apache y abre el portal:
   ```
   http://localhost/portal-cliente/
   ```

### Configurar envío de correos

1. Activar verificación en dos pasos en tu cuenta de Google
2. Generar contraseña de aplicación en: https://myaccount.google.com/apppasswords
3. Define `PORTAL_SMTP_USER` y `PORTAL_SMTP_PASS` en PHP-FPM o Apache. Mantén las credenciales fuera del repositorio.

## Verificaciones locales

Con Node.js 20 o superior, ejecuta las comprobaciones del frontend y los contratos de arquitectura:

```bash
node --check js/app.js
node tests/architecture.test.js
node tests/frontend-smoke.test.js
find api -name '*.php' -print0 | xargs -0 -n1 php -l
```

La API y PostgreSQL también deben comprobarse con PHP 8.2+, PDO_PGSQL y una base configurada. Esas verificaciones requieren el entorno de ejecución y credenciales del proyecto.

## API Endpoints

| Método | Acción | Descripción |
|--------|--------|-------------|
| GET | `?action=session` | Validar la sesión actual |
| GET | `?action=get-all` | Obtener datos del cliente actual o, para admin, de todos los clientes |
| POST | `?action=save-clients` | Guardar clientes (solo admin; conserva hashes de contraseñas) |
| POST | `?action=login` | Iniciar sesión (`{email, password}`) |
| POST | `?action=logout` | Cerrar sesión |
| POST | `?action=register` | Registrar cliente (`{company, email, password, ruc, phone}`) |
| POST | `?action=verify` | Verificar código (`{email, code}`) |
| POST | `?action=resend-code` | Reenviar código (`{email}`) |

## Acceso inicial

| Rol | Correo | Contraseña |
|-----|--------|------------|
| Administrador | `PORTAL_ADMIN_EMAIL` | `PORTAL_ADMIN_PASSWORD` (solo se usa al crear la primera cuenta) |
| Cliente | Correo o RUC verificado | La contraseña creada durante el registro |

## Seguridad

- Contraseñas hasheadas con `password_hash`
- Códigos de verificación de 6 dígitos con expiración de 15 minutos
- Rate limiting: máximo 3 solicitudes de código por hora por email
- Validación de contraseña: 8 a 72 bytes, 1 mayúscula y 1 número
- Sesiones del lado del servidor con cookies `HttpOnly` y `SameSite=Strict`
- Conexiones parametrizadas con prepared statements (SQL injection prevention)
- Las tablas tienen RLS habilitado y no exponen políticas para el acceso directo desde el Data API; el portal autoriza usuarios en la API PHP.
- La función Supabase `public.rls_auto_enable()` pierde ejecución para `PUBLIC`, `anon` y `authenticated` al aplicar el esquema.

## Licencia

MIT © [Alejandro Lopez](https://github.com/lopez-torres-alejandro)
