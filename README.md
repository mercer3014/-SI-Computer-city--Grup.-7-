# Computer City — Grupo 7

Sistema de información para la tienda de periféricos Computer City: inventario, ventas, compras y reportes.

## Cómo ejecutar para probar

Hace falta **PHP 8.3+**, **Composer**, **Node**, **pnpm** y **PostgreSQL** con la base `tiendaDB` (o la que pongas en `.env`).

### Primera vez

```bash
cd tienda-de-perifericos
cp .env.example .env
composer install
pnpm install
php artisan key:generate
```

En `.env` dejá:

- `APP_URL=http://localhost:8000`
- `DB_CONNECTION=pgsql`
- `SESSION_DRIVER=database`
- `CACHE_STORE=file`
- `MAIL_MAILER=log`

Si faltan tablas de Laravel (`sessions`, `password_reset_tokens`):

```bash
php artisan migrate
```

### Probar

**1. Backend** — este es el que se abre en el navegador:

```bash
php artisan serve
```

**2. Frontend** — recarga CSS/Vue en caliente:

```bash
pnpm install
pnpm dev
```

Entrá siempre a **[http://127.0.0.1:8000/login](http://127.0.0.1:8000/login)**.


Opcional, para ver logs en vivo:

```bash
php artisan pail
```

Si no corrés `pnpm dev`, compilá una vez y usá solo Artisan:

```bash
pnpm build
php artisan serve
```

### Qué probar en auth

| Flujo | URL | Qué esperar |
| --- | --- | --- |
| Login | `/login` | Correo + clave; ojito para ver/ocultar |
| Registro | `/register` | 3 pasos (datos → correo/OTP → clave) y confeti al final |
| Recuperar | `/forgot-password` | Correo → código de 6 dígitos + nueva clave → confeti |

El OTP de **registro** y **recuperar** se escribe en `storage/logs/laravel.log` (líneas `OTP registro` / `OTP recuperar cuenta`). En Pail evitá `-v`: el correo HTML es enorme; el código útil es el de 6 dígitos, no el hash de `password_reset_tokens`.

```bash
grep "OTP " storage/logs/laravel.log | tail
```

## Estructura

```
tienda-de-perifericos/
├── app/                    Backend PHP
│   ├── Actions/Fortify/    Alta de usuario y reset de clave (tabla usuario)
│   ├── Auth/               Broker OTP de 6 dígitos (no token hex)
│   ├── Http/
│   │   ├── Controllers/    OTP de registro y settings
│   │   └── Responses/      A dónde ir al terminar login/registro/recuperar
│   ├── Models/             User, Personal, Rol… (BD de la tienda)
│   ├── Providers/          Fortify + App (vistas Inertia y OTP)
│   └── Services/           Envío/verificación del OTP de registro
├── routes/
│   ├── web.php             Home, OTP registro, pantallas de éxito
│   └── settings.php        Perfil / seguridad
├── resources/
│   ├── css/app.css         Marca Computer City (auth, campos, confeti)
│   ├── js/
│   │   ├── pages/          Pantallas Inertia (auth/, Dashboard, settings/)
│   │   ├── components/     Piezas Vue (AuthConfetti, AuthPasswordControl…)
│   │   ├── composables/    Textos del panel auth, tema, superficies
│   │   ├── layouts/        AuthLayout y layout del POS
│   │   └── routes/         Rutas tipadas (Wayfinder; no editar a mano)
│   └── views/app.blade.php Hoja que monta Inertia
├── database/migrations/    Esquema de la tienda + sessions / reset tokens
├── tests/Feature/Auth/     Pruebas de login, registro y recuperar
├── public/                 Entrada HTTP; no abrir :5173 para “ver la app”
├── storage/logs/           laravel.log = OTPs cuando MAIL_MAILER=log
├── .env                    Config local (no commitear)
└── vite.config.ts          Vite en 127.0.0.1:5173, abre el login de :8000
```

### Por qué está partido así

- **`app/` y `routes/`** deciden qué hace el servidor (OTP, guardar clave, redirecciones).
- **`resources/js/` y `resources/css/`** deciden cómo se ve (pasos, ojito, confeti).
- **`database/`** es el modelo de la tienda (`usuario`, ventas, stock) más tablas que Laravel necesita (`sessions`, `password_reset_tokens`).
- **`tests/`** cubre los flujos de auth para no romper el redirect al confeti.

## Stack

- Laravel 13, Fortify, Inertia Vue 3
- Vite + Tailwind
- PostgreSQL
- Auth: login, registro con OTP.
