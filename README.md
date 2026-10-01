# Computer City — Grupo 7

Sistema de información para la tienda de periféricos Computer City: inventario, ventas, compras y reportes.

No hay registro público. Las cuentas las da de alta un administrador (módulo de usuarios) con el correo real de cada persona. El primer ingreso pide una clave nueva si `primer_login` está en `true`.

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

En `.env` local dejá:

- `APP_URL=http://localhost:8000`
- `DB_CONNECTION=pgsql` (Postgres de tu máquina)
- `SESSION_DRIVER=database`
- `CACHE_STORE=file`

Producción usa la conexión `supabase` del mismo `.env` (`DB_CONNECTION=supabase`). En esta PC no la actives.

Si faltan tablas:

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



## SMTP en local (recuperar cuenta)

El OTP de **recuperar cuenta** sale por correo. En local podés:

1. **Log** (sin Gmail): `MAIL_MAILER=log`. El código queda en `storage/logs/laravel.log`.
2. **SMTP real** (Gmail), para que el código llegue a la bandeja.



### Activar Gmail SMTP

1. En la cuenta de Google: verificación en 2 pasos.
2. Generá una [App Password](https://myaccount.google.com/apppasswords) de 16 caracteres (no uses la clave normal de Gmail).
3. En `.env`:

```env
MAIL_MAILER=smtp
MAIL_SCHEME=null
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=tu.correo@gmail.com
MAIL_PASSWORD="xxxx xxxx xxxx xxxx"
MAIL_FROM_ADDRESS="tu.correo@gmail.com"
MAIL_FROM_NAME="Computer City"
```

`MAIL_FROM_ADDRESS` tiene que ser **el mismo** Gmail que `MAIL_USERNAME`. Si la App Password viene con espacios, dejala entre comillas.

### Producción: Brevo (Render)

Gmail SMTP desde Render se cuelga. En producción el mailer es la **API HTTPS de Brevo**.

1. Cuenta en [Brevo](https://www.brevo.com/).
2. Senders → verificá el Gmail que va a figurar como remitente (`MAIL_FROM_ADDRESS`).
3. SMTP & API → API keys → creá una clave. Eso es `BREVO_KEY` (empieza con `xkeysib-`).
4. En Render, Environment:

```env
MAIL_MAILER=brevo
MAIL_FROM_ADDRESS=tu.correo@gmail.com
MAIL_FROM_NAME=Computer City
BREVO_KEY=xkeysib-...
```

Podés borrar `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME` y `MAIL_PASSWORD` de Render: ya no se usan. Local sigue con Gmail SMTP. El OTP también se escribe en Logs (`OTP recuperar cuenta`).

1. Recargá config y el serve:

```bash
php artisan config:clear
```

Reiniciá `php artisan serve` si estaba corriendo.

### Qué probar en auth

| Flujo | URL | Qué esperar |
| --- | --- | --- |
| Login | `/login` | Correo y clave del alta de usuarios |
| Primer ingreso | `/primer-ingreso` | Si `primer_login` es true |
| Recuperar | `/forgot-password` | OTP al correo (Gmail local / Brevo en Render) |

## Estructura

```
tienda-de-perifericos/
├── app/                    Backend PHP
│   ├── Actions/Fortify/    Reset de clave (tabla usuario)
│   ├── Auth/               Broker OTP de 6 dígitos
│   ├── Http/
│   │   ├── Controllers/    Primer ingreso y settings
│   │   └── Responses/      Login → dashboard o /primer-ingreso
│   ├── Models/             User, Personal, Rol… (BD de la tienda)
│   ├── Providers/          Fortify + App
│   └── Services/           Bitácora
├── routes/
│   ├── web.php             Home, primer ingreso, dashboard
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
├── tests/Feature/Auth/     Pruebas de login, primer ingreso y recuperar
├── public/                 Entrada HTTP; no abrir :5173 para “ver la app”
├── storage/logs/           laravel.log = OTPs cuando MAIL_MAILER=log
├── .env                    Config local (no commitear)
└── vite.config.ts          Vite en 127.0.0.1:5173, abre el login de :8000
```



### Por qué está partido así

- `app/` **y** `routes/` deciden qué hace el servidor (OTP, guardar clave, redirecciones).
- `resources/js/` **y** `resources/css/` deciden cómo se ve (pasos, ojito, confeti).
- `database/` es el modelo de la tienda (`usuario`, ventas, stock) más tablas que Laravel necesita (`sessions`, `password_reset_tokens`).
- `tests/` cubre los flujos de auth.



## Stack

- Laravel 13, Fortify, Inertia Vue 3
- Vite + Tailwind
- PostgreSQL local; Supabase (también Postgres) para producción
- Auth: login, primer ingreso, recuperar cuenta con OTP

## Deploy en Render

Render no corre PHP nativo: el servicio es **Docker**. La imagen usa **PHP 8.4** (el `composer.lock` pide ≥ 8.4.1). La base de producción es **Supabase** (pooler IPv4). El plan free se duerme; el primer hit tarda.

### En el dashboard

1. New → Web Service → el repo. Runtime: **Docker**.
2. Si el repo no es esta carpeta, Root Directory: `tienda-de-perifericos`.
3. Health check: `/up`.
4. Variables (Environment):

| Variable | Valor |
| --- | --- |
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `APP_KEY` | salida de `php artisan key:generate --show` en tu PC |
| `APP_URL` | `https://TU-SERVICIO.onrender.com` (la URL real de Render) |
| `LOG_CHANNEL` | `stderr` |
| `SESSION_DRIVER` | `database` |
| `SESSION_SECURE_COOKIE` | `true` |
| `CACHE_STORE` | `file` |
| `QUEUE_CONNECTION` | `sync` |
| `DB_CONNECTION` | `supabase` |
| `SUPABASE_DB_HOST` | `aws-0-us-east-2.pooler.supabase.com` |
| `SUPABASE_DB_PORT` | `5432` (session pooler, no 6543) |
| `SUPABASE_DB_DATABASE` | `postgres` |
| `SUPABASE_DB_USERNAME` | `postgres.tlrnckckioghlgufnnak` |
| `SUPABASE_DB_PASSWORD` | Database password del proyecto Supabase |
| `SUPABASE_DB_SSLMODE` | `require` |
| `MAIL_MAILER` | `brevo` |
| `MAIL_FROM_ADDRESS` | el Gmail **verificado** como sender en Brevo |
| `MAIL_FROM_NAME` | `Computer City` |
| `BREVO_KEY` | API key de Brevo (`xkeysib-...`) |

En Supabase: Database → Connect → **Session pooler**. Si hay Network restrictions, permití `0.0.0.0/0` para la demo.

Hay un `render.yaml` con los valores fijos; **APP_KEY**, **APP_URL**, password y mail los cargás a mano.

Al arrancar corre `migrate --force` (no borra datos) y sirve en `$PORT`.

No commitees `.env`.

