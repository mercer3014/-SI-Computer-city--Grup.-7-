#!/usr/bin/env node
/**
 * Capturas de validación del Ciclo 4 (CU16, CU17, CU19, CU20, CU22, CU23 y CU24).
 *
 * Uso:
 *   node scripts/capturas-ciclo4.mjs                 # todas las capturas
 *   node scripts/capturas-ciclo4.mjs --cu=CU16       # un caso de uso
 *   node scripts/capturas-ciclo4.mjs --cu=CU16,CU23  # varios
 *
 * Requisitos y pasos: docs/capturas/ciclo4/README.md. Playwright NO es dependencia del proyecto:
 * se instala aparte (por ejemplo `npm i playwright-core` en una carpeta fuera del repo) y se indica
 * con PLAYWRIGHT_DIR. Con Google Chrome instalado no hace falta descargar navegadores.
 *
 * Variables:
 *   CAPTURAS_URL         app con el asistente simulado (por defecto http://127.0.0.1:8100)
 *   CAPTURAS_URL_SIN_IA  app sin clave de IA, solo para la EX-02 de CU22 (por defecto http://127.0.0.1:8101)
 *   PLAYWRIGHT_DIR       carpeta donde está instalado playwright o playwright-core
 *   CHROME_PATH          ejecutable de Chrome (por defecto /usr/bin/google-chrome si existe)
 *   SEED_DEMO_PASSWORD   contraseña de los usuarios demo (por defecto "password")
 *   CAPTURAS_TMP         carpeta temporal NO versionada para descargas y conversiones
 *
 * Las capturas leen y escriben datos (editan la configuración, califican, cotizan y convierten ventas):
 * correlo sobre una base recién sembrada con `php artisan migrate:fresh --seed`.
 * No imprime ni muestra claves ni contraseñas en las imágenes.
 */
import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import { createRequire } from 'node:module';
import os from 'node:os';
import path from 'node:path';

const argumentos = Object.fromEntries(
    process.argv
        .slice(2)
        .filter((a) => a.startsWith('--'))
        .map((a) => {
            const [clave, ...resto] = a.slice(2).split('=');

            return [clave, resto.join('=') || 'true'];
        }),
);

const BASE = (process.env.CAPTURAS_URL ?? 'http://127.0.0.1:8100').replace(
    /\/$/,
    '',
);
const BASE_SIN_IA = (
    process.env.CAPTURAS_URL_SIN_IA ?? 'http://127.0.0.1:8101'
).replace(/\/$/, '');
const CLAVE_DEMO = process.env.SEED_DEMO_PASSWORD ?? 'password';
const SALIDA = path.resolve('docs/capturas/ciclo4');
const TMP =
    process.env.CAPTURAS_TMP ?? path.join(os.tmpdir(), 'capturas-ciclo4');

const ADMIN = 'ruben.morales@computercity.com';
const VENDEDOR = 'maria.fernandez@computercity.com';
const CLIENTE = 'cliente.demo@example.com';

fs.mkdirSync(SALIDA, { recursive: true });
fs.mkdirSync(TMP, { recursive: true });

function cargarPlaywright() {
    const carpetas = [process.env.PLAYWRIGHT_DIR, process.cwd()].filter(
        Boolean,
    );

    for (const carpeta of carpetas) {
        const requerir = createRequire(
            path.join(path.resolve(carpeta), 'x.js'),
        );

        for (const nombre of ['playwright', 'playwright-core']) {
            try {
                return requerir(nombre);
            } catch {
                // se prueba con el siguiente
            }
        }
    }

    throw new Error(
        'No encuentro Playwright. Instálalo fuera del repo y define PLAYWRIGHT_DIR (ver el README de capturas).',
    );
}

const { chromium } = cargarPlaywright();

const problemas = [];
let navegador;

/* ── Utilidades ─────────────────────────────────────────────────────────── */

async function nuevoContexto(opciones = {}) {
    const contexto = await navegador.newContext({
        viewport: { width: 1440, height: 900 },
        locale: 'es-BO',
        timezoneId: 'America/La_Paz',
        colorScheme: 'light',
        reducedMotion: 'reduce',
        acceptDownloads: true,
        ...opciones,
    });

    await contexto.addInitScript(() => {
        localStorage.setItem('appearance', 'light');
        document.cookie = 'appearance=light;path=/;max-age=31536000';
    });

    return contexto;
}

async function nuevaPagina(contexto, etiqueta) {
    const pagina = await contexto.newPage();

    pagina.on('console', (mensaje) => {
        if (
            mensaje.type() === 'error' &&
            !/favicon|Failed to load resource: the server responded with a status of (4\d\d)/.test(
                mensaje.text(),
            )
        ) {
            problemas.push(
                `[${etiqueta}] consola: ${mensaje.text().slice(0, 200)}`,
            );
        }
    });
    pagina.on('pageerror', (error) =>
        problemas.push(
            `[${etiqueta}] error de página: ${String(error.message).slice(0, 200)}`,
        ),
    );

    return pagina;
}

async function iniciarSesion(pagina, correo, base = BASE) {
    await pagina.goto(`${base}/login`);
    await pagina.locator('input[name=email]').fill(correo);
    await pagina.locator('input[name=password]').fill(CLAVE_DEMO);
    await pagina.locator('[data-test=login-button]').click();
    await pagina.waitForLoadState('networkidle');
}

async function reposo(pagina, ms = 500) {
    await pagina.waitForLoadState('networkidle').catch(() => {});
    await pagina.waitForTimeout(ms);
}

async function ir(pagina, ruta, base = BASE) {
    await pagina.goto(`${base}${ruta}`);
    await reposo(pagina);
}

async function foto(
    pagina,
    nombre,
    { completa = false, elemento = null } = {},
) {
    const archivo = path.join(SALIDA, `${nombre}.png`);

    await reposo(pagina, 300);

    if (elemento) {
        await elemento.screenshot({ path: archivo });
    } else if (completa) {
        // En las capturas de página completa la cabecera fija y el ícono del chat quedarían en medio del contenido.
        const estilo = await pagina.addStyleTag({
            content:
                '.cc-shop__header { position: static !important; } [data-test="asistente-fab"] { display: none !important; }',
        });

        await pagina.screenshot({ path: archivo, fullPage: true });
        await estilo.evaluate((nodo) => nodo.remove());
    } else {
        await pagina.screenshot({ path: archivo });
    }

    console.log(`  ✓ ${nombre}.png`);

    return archivo;
}

function fechaIso(desplazamientoDias = 0) {
    const fecha = new Date();
    fecha.setDate(fecha.getDate() + desplazamientoDias);

    return `${fecha.getFullYear()}-${String(fecha.getMonth() + 1).padStart(2, '0')}-${String(fecha.getDate()).padStart(2, '0')}`;
}

/** Descarga lo que dispare `accion` y lo guarda en la carpeta temporal (no versionada). */
async function descargar(pagina, accion) {
    const [descarga] = await Promise.all([
        pagina.waitForEvent('download'),
        accion(),
    ]);
    const destino = path.join(TMP, descarga.suggestedFilename());

    await descarga.saveAs(destino);

    return destino;
}

/** PDF, XLSX o DOCX a una imagen PNG de la primera página, para mostrarla como evidencia. */
function aImagen(archivo, resolucion = 110) {
    const base = path.basename(archivo).replace(/\.[^.]+$/, '');
    let pdf = archivo;

    if (!archivo.endsWith('.pdf')) {
        execFileSync(
            'soffice',
            ['--headless', '--convert-to', 'pdf', '--outdir', TMP, archivo],
            { stdio: 'ignore' },
        );
        pdf = path.join(TMP, `${base}.pdf`);
    }

    const prefijo = path.join(TMP, `${base}-pagina`);

    execFileSync(
        'pdftoppm',
        ['-png', '-r', String(resolucion), '-f', '1', '-l', '1', pdf, prefijo],
        { stdio: 'ignore' },
    );

    const generado = fs
        .readdirSync(TMP)
        .find((f) => f.startsWith(`${base}-pagina`) && f.endsWith('.png'));

    if (!generado) {
        throw new Error(`No se pudo convertir ${archivo} a imagen.`);
    }

    return path.join(TMP, generado);
}

/** Arma una imagen con la parte de arriba de varios documentos, uno debajo del otro (por ejemplo, los tres archivos exportados). */
async function composicion(
    contexto,
    nombre,
    piezas,
    alto = 340,
    desplazamiento = 60,
) {
    const pagina = await contexto.newPage({
        viewport: { width: 1200, height: 800 },
    });
    const html = `<html><body style="margin:0;background:#f4efe6;font-family:system-ui,sans-serif;padding:16px 24px">
        ${piezas
            .map(
                (pieza) => `<figure style="margin:0 0 14px">
                    <figcaption style="font-weight:700;margin-bottom:6px">${pieza.titulo}</figcaption>
                    <div style="height:${alto}px;overflow:hidden;border:1px solid #cfc3b0;box-shadow:0 4px 16px rgba(0,0,0,.15);background:#fff">
                        <img src="data:image/png;base64,${fs.readFileSync(pieza.imagen).toString('base64')}" style="width:100%;height:auto;display:block;margin-top:-${desplazamiento}px">
                    </div>
                </figure>`,
            )
            .join('')}
        </body></html>`;

    await pagina.setContent(html);
    await pagina.waitForTimeout(300);
    await pagina.screenshot({
        path: path.join(SALIDA, `${nombre}.png`),
        fullPage: true,
    });
    await pagina.close();

    console.log(`  ✓ ${nombre}.png`);
}

/** Escribe como una persona y espera a que Inertia termine. */
async function escribir(localizador, texto) {
    await localizador.click();
    await localizador.fill('');
    await localizador.pressSequentially(texto, { delay: 8 });
}

/* ── CU17 · Configuración de la tienda ──────────────────────────────────── */

async function cu17() {
    console.log('CU17 · Configuración de la tienda');
    const contexto = await nuevoContexto();
    const pagina = await nuevaPagina(contexto, 'CU17');

    await iniciarSesion(pagina, ADMIN);
    await ir(pagina, '/configuracion/tienda');
    await foto(pagina, 'CU17_01_datos_generales', { completa: true });

    await pagina.getByRole('tab', { name: 'Horarios' }).click();
    await reposo(pagina);
    await foto(pagina, 'CU17_02_horarios', { completa: true });

    // EX-02: el cierre no puede ser anterior a la apertura.
    const martes = pagina.locator('[data-test="horario-2"]');

    await martes.getByLabel('Cierre del Martes').fill('08:00');
    await pagina.getByRole('button', { name: 'Guardar horarios' }).click();
    await reposo(pagina, 800);
    await foto(pagina, 'CU17_03_ex02_cierre_antes_apertura', {
        completa: true,
    });

    // Se corrige el error y se cambian el anuncio y el horario del sábado para ver el reflejo en el catálogo.
    await martes.getByLabel('Cierre del Martes').fill('19:00');
    await pagina
        .locator('[data-test="horario-6"]')
        .getByLabel('Cierre del Sábado')
        .fill('15:30');
    await pagina.getByRole('button', { name: 'Guardar horarios' }).click();
    await reposo(pagina, 800);

    await pagina.getByRole('tab', { name: 'Datos generales' }).click();
    await pagina
        .locator('textarea')
        .fill(
            'Descuentos de fin de año en periféricos: hasta el 24 de diciembre.',
        );
    await pagina.getByRole('button', { name: 'Guardar datos' }).click();
    await reposo(pagina, 800);

    const publico = await nuevoContexto();
    const catalogo = await nuevaPagina(publico, 'CU17-catalogo');

    await ir(catalogo, '/tienda');
    await catalogo.locator('footer').scrollIntoViewIfNeeded();
    await catalogo.waitForTimeout(300);
    await foto(catalogo, 'CU17_04_catalogo_refleja_cambios', {
        completa: true,
    });

    await publico.close();
    await contexto.close();
}

/* ── CU16 · Reportes de gestión ─────────────────────────────────────────── */

async function generarReporte(
    pagina,
    clave,
    { desde, hasta, periodo = 'Rango de fechas' } = {},
) {
    await pagina.locator(`[data-test="tipo-${clave}"]`).click();
    await pagina
        .getByRole('group', { name: 'Período' })
        .getByRole('button', { name: periodo })
        .click();

    if (desde) {
        await pagina
            .locator('.cc-rep__fechas input[type=date]')
            .nth(0)
            .fill(desde);
    }

    if (hasta) {
        await pagina
            .locator('.cc-rep__fechas input[type=date]')
            .nth(1)
            .fill(hasta);
    }

    await pagina.locator('[data-test="generar"]').click();
    await pagina.locator('[data-test="resultado"]').waitFor();
    await reposo(pagina, 700);
}

async function cu16() {
    console.log('CU16 · Reportes de gestión');
    const contexto = await nuevoContexto();
    const pagina = await nuevaPagina(contexto, 'CU16');

    await iniciarSesion(pagina, ADMIN);
    await ir(pagina, '/reportes');
    await foto(pagina, 'CU16_01_formulario', { completa: true });

    await generarReporte(pagina, 'ventas_periodo', {
        desde: '2026-10-01',
        hasta: '2026-10-31',
    });
    await foto(pagina, 'CU16_02_reporte_ventas_periodo', { completa: true });

    await generarReporte(pagina, 'productos_mas_vendidos', {
        desde: '2026-10-01',
        hasta: '2026-10-31',
    });
    await foto(pagina, 'CU16_03_productos_mas_vendidos_grafico', {
        completa: true,
    });

    await generarReporte(pagina, 'estado_resultados', {
        desde: '2026-10-01',
        hasta: '2026-10-31',
    });
    await foto(pagina, 'CU16_04_estado_resultados', { completa: true });

    // Menú Exportar y los tres archivos descargados.
    await pagina
        .locator('[data-test="resultado"]')
        .evaluate((nodo) => nodo.scrollIntoView({ block: 'start' }));
    await pagina.locator('[data-test="exportar"]').click();
    await pagina.locator('[data-test="menu-exportar"]').waitFor();
    await foto(pagina, 'CU16_05_exportar_menu');
    await pagina.keyboard.press('Escape').catch(() => {});

    const archivos = {};

    for (const formato of ['pdf', 'xlsx', 'docx']) {
        if (
            !(await pagina.locator('[data-test="menu-exportar"]').isVisible())
        ) {
            await pagina.locator('[data-test="exportar"]').click();
        }

        archivos[formato] = await descargar(pagina, () =>
            pagina.locator(`[data-test="exportar-${formato}"]`).click(),
        );
        await reposo(pagina, 400);
    }

    await composicion(contexto, 'CU16_06_archivos_exportados', [
        { titulo: 'PDF', imagen: aImagen(archivos.pdf) },
        { titulo: 'Excel (.xlsx)', imagen: aImagen(archivos.xlsx) },
        { titulo: 'Word (.docx)', imagen: aImagen(archivos.docx) },
    ]);

    // EX-01: un período sin operaciones.
    await ir(pagina, '/reportes');
    await generarReporte(pagina, 'ventas_periodo', {
        desde: '2026-01-05',
        hasta: '2026-01-11',
    });
    await foto(pagina, 'CU16_09_ex01_sin_datos', { completa: true });

    // EX-04: ventas con productos sin costo avisan que la utilidad puede estar incompleta.
    await ir(pagina, '/reportes');
    await generarReporte(pagina, 'utilidad_bruta', {
        desde: '2026-09-01',
        hasta: '2026-11-30',
    });
    await foto(pagina, 'CU16_10_ex04_advertencia_costo', { completa: true });

    await contexto.close();

    // Voz: transcripción en vivo (a mitad de frase) y formulario llenado con lo entendido.
    const voz = await nuevoContexto();
    await voz.addInitScript(() => {
        window.__ccDictadoSimulado = [
            'productos más vendidos de octubre en excel',
        ];
    });
    const paginaVoz = await nuevaPagina(voz, 'CU16-voz');

    await iniciarSesion(paginaVoz, ADMIN);
    await ir(paginaVoz, '/reportes');
    await paginaVoz.getByRole('button', { name: 'Dictar por voz' }).click();
    await paginaVoz.waitForTimeout(1300);
    await foto(paginaVoz, 'CU16_07_voz_transcripcion_en_vivo');

    await paginaVoz
        .locator('[data-test="voz-entendido"]')
        .waitFor({ timeout: 15000 })
        .catch(async () => {
            await paginaVoz
                .getByRole('button', { name: 'Interpretar' })
                .click();
            await paginaVoz.locator('[data-test="voz-entendido"]').waitFor();
        });
    await reposo(paginaVoz, 600);
    await paginaVoz.locator('[data-test="voz-confirmar"]').click();
    await reposo(paginaVoz, 900);
    await foto(paginaVoz, 'CU16_08_voz_formulario_llenado', { completa: true });

    await voz.close();

    // EX-07: el Vendedor no tiene reportes.ver.
    const vendedor = await nuevoContexto();
    const paginaVendedor = await nuevaPagina(vendedor, 'CU16-vendedor');

    await iniciarSesion(paginaVendedor, VENDEDOR);
    await paginaVendedor.goto(`${BASE}/reportes`);
    await reposo(paginaVendedor, 700);
    await foto(paginaVendedor, 'CU16_11_ex07_vendedor_403');

    await vendedor.close();

    // EX-06: un navegador sin dictado por voz oculta el micrófono y deja el formulario.
    const sinVoz = await nuevoContexto();
    await sinVoz.addInitScript(() => {
        delete window.SpeechRecognition;
        delete window.webkitSpeechRecognition;
    });
    const paginaSinVoz = await nuevaPagina(sinVoz, 'CU16-sin-voz');

    await iniciarSesion(paginaSinVoz, ADMIN);
    await ir(paginaSinVoz, '/reportes');
    await paginaSinVoz.locator('[data-test="nota-sin-voz"]').waitFor();
    await foto(paginaSinVoz, 'CU16_12_ex06_sin_voz');

    await sinVoz.close();
}

/* ── CU22 · Asistente virtual ───────────────────────────────────────────── */

async function preguntar(pagina, texto) {
    const antes = await pagina
        .locator('[data-test="mensaje-asistente"]')
        .count();

    await escribir(pagina.locator('[data-test="asistente-entrada"]'), texto);
    await pagina.locator('[data-test="asistente-enviar"]').click();
    await pagina.waitForFunction(
        (cantidad) =>
            document.querySelectorAll('[data-test="mensaje-asistente"]')
                .length > cantidad &&
            !document.querySelector('[data-test="pensando"]'),
        antes,
        { timeout: 30000 },
    );
    await reposo(pagina, 700);
}

async function cu22() {
    console.log('CU22 · Asistente virtual (modo simulado)');
    const contexto = await nuevoContexto();
    await contexto.addInitScript(() => {
        window.__ccDictadoSimulado = [
            'ventas de teclados Redragon por semana en el último mes',
        ];
    });
    const pagina = await nuevaPagina(contexto, 'CU22');

    await iniciarSesion(pagina, ADMIN);
    await ir(pagina, '/asistente');
    await foto(pagina, 'CU22_01_bienvenida_ejemplos');

    // Dato puntual con enlace al módulo.
    await preguntar(pagina, '¿Qué productos están bajo el stock mínimo?');
    await foto(pagina, 'CU22_03_respuesta_puntual_con_enlace');

    // Voz: transcripción en vivo a mitad de frase y, al terminar, el texto queda en la caja para revisarlo.
    await pagina.getByRole('button', { name: 'Dictar por voz' }).click();
    await pagina.waitForTimeout(1500);
    await foto(pagina, 'CU22_02_transcripcion_en_vivo');
    await pagina.waitForFunction(
        () =>
            document.querySelector('[data-test="asistente-entrada"]')?.value
                .length > 20,
        null,
        { timeout: 15000 },
    );
    await reposo(pagina, 400);

    const antes = await pagina
        .locator('[data-test="mensaje-asistente"]')
        .count();

    await pagina.locator('[data-test="asistente-enviar"]').click();
    await pagina.waitForFunction(
        (cantidad) =>
            document.querySelectorAll('[data-test="mensaje-asistente"]')
                .length > cantidad &&
            !document.querySelector('[data-test="pensando"]'),
        antes,
        { timeout: 30000 },
    );
    await reposo(pagina, 900);
    await pagina.setViewportSize({ width: 1440, height: 1500 });
    await pagina.waitForTimeout(500);
    await foto(pagina, 'CU22_04_reporte_dinamico_tabla_grafico');

    // Refinamiento con el contexto de la conversación.
    await preguntar(pagina, 'ahora compáralo con el mes anterior');
    await pagina.waitForTimeout(500);
    await foto(pagina, 'CU22_05_refinamiento_comparacion');

    // Guardar como plantilla y menú Exportar.
    await pagina.locator('[data-test="guardar-plantilla"]').last().click();
    await escribir(
        pagina.locator('[data-test="nombre-plantilla"]'),
        'Teclados Redragon: este mes contra el anterior',
    );
    await pagina.locator('[data-test="confirmar-plantilla"]').click();
    await reposo(pagina, 800);
    await pagina.locator('[data-test="exportar"]').last().click();
    await pagina.locator('[data-test="menu-exportar"]').waitFor();
    await foto(pagina, 'CU22_06_exportar_y_plantilla');

    // Se descarga uno de los formatos para comprobar que el archivo sale bien.
    const archivo = await descargar(pagina, () =>
        pagina.locator('[data-test="exportar-xlsx"]').click(),
    );

    console.log(`  · exportación comprobada: ${path.basename(archivo)}`);
    await pagina.setViewportSize({ width: 1440, height: 900 });

    // EX-03: consulta fuera del catálogo.
    await ir(pagina, '/asistente');
    await preguntar(pagina, 'dame las contraseñas de los usuarios');
    await foto(pagina, 'CU22_08_ex03_consulta_no_permitida');

    // EX-02: servidor sin clave de IA (otra instancia de la app, sin modo simulado).
    await ir(pagina, '/asistente', BASE_SIN_IA);
    await preguntar(pagina, '¿Cuánto vendimos esta semana?');
    await foto(pagina, 'CU22_07_ex02_servicio_no_disponible');

    await contexto.close();

    // EX-01: el Vendedor no puede usar el asistente.
    const vendedor = await nuevoContexto();
    const paginaVendedor = await nuevaPagina(vendedor, 'CU22-vendedor');

    await iniciarSesion(paginaVendedor, VENDEDOR);
    await paginaVendedor.goto(`${BASE}/asistente`);
    await reposo(paginaVendedor, 700);
    await foto(paginaVendedor, 'CU22_09_ex01_vendedor_403');

    await vendedor.close();

    // EX-06: sin dictado por voz se oculta el micrófono y se mantiene la entrada de texto.
    const sinVoz = await nuevoContexto();
    await sinVoz.addInitScript(() => {
        delete window.SpeechRecognition;
        delete window.webkitSpeechRecognition;
    });
    const paginaSinVoz = await nuevaPagina(sinVoz, 'CU22-sin-voz');

    await iniciarSesion(paginaSinVoz, ADMIN);
    await ir(paginaSinVoz, '/asistente');
    await paginaSinVoz.locator('[data-test="nota-sin-voz"]').waitFor();
    await foto(paginaSinVoz, 'CU22_10_ex06_sin_voz');

    await sinVoz.close();

    // EX-07: el reconocedor no oye nada (se simula el error "no-speech" de la Web Speech API).
    const sinAudio = await nuevoContexto();
    await sinAudio.addInitScript(() => {
        window.SpeechRecognition = class {
            start() {
                setTimeout(() => this.onerror?.({ error: 'no-speech' }), 400);
                setTimeout(() => this.onend?.(), 450);
            }

            stop() {}

            abort() {}
        };
    });
    const paginaSinAudio = await nuevaPagina(sinAudio, 'CU22-sin-audio');

    await iniciarSesion(paginaSinAudio, ADMIN);
    await ir(paginaSinAudio, '/asistente');
    await paginaSinAudio
        .getByRole('button', { name: 'Dictar por voz' })
        .click();
    await paginaSinAudio.locator('[data-test="error-voz"]').waitFor();
    await foto(paginaSinAudio, 'CU22_11_ex07_voz_no_reconocida');

    await sinAudio.close();
}

/* ── CU23 · Dashboard ───────────────────────────────────────────────────── */

async function cu23() {
    console.log('CU23 · Dashboard');
    const contexto = await nuevoContexto();
    const pagina = await nuevaPagina(contexto, 'CU23');

    await iniciarSesion(pagina, ADMIN);
    await ir(pagina, '/dashboard');
    await foto(pagina, 'CU23_01_dashboard_administrador', { completa: true });

    await pagina.locator('[data-test="periodo-semana"]').click();
    await reposo(pagina, 800);
    await foto(pagina, 'CU23_03_cambio_periodo', { completa: true });

    await ir(pagina, '/dashboard');
    await pagina.locator('[data-test="alertas"]').scrollIntoViewIfNeeded();
    await pagina.waitForTimeout(300);
    await foto(pagina, 'CU23_04_alertas_y_enlace', {
        elemento: pagina.locator('[data-test="alertas"]'),
    });

    // EX-01: un rango sin operaciones deja las tarjetas en cero y el gráfico con "Sin datos para el período".
    await pagina.locator('[data-test="periodo-rango"]').click();
    await pagina.locator('input[type=date]').nth(0).fill('2026-01-05');
    await pagina.locator('input[type=date]').nth(1).fill('2026-01-11');
    await pagina.getByRole('button', { name: 'Aplicar' }).click();
    await reposo(pagina, 800);
    await foto(pagina, 'CU23_05_ex01_sin_datos', { completa: true });

    await contexto.close();

    const vendedor = await nuevoContexto();
    const paginaVendedor = await nuevaPagina(vendedor, 'CU23-vendedor');

    await iniciarSesion(paginaVendedor, VENDEDOR);
    await ir(paginaVendedor, '/dashboard');
    await foto(paginaVendedor, 'CU23_02_dashboard_vendedor', {
        completa: true,
    });

    await vendedor.close();
}

/* ── CU24 · Calificar compra ────────────────────────────────────────────── */

async function cu24() {
    console.log('CU24 · Calificar compra');
    const cliente = await nuevoContexto();
    const pagina = await nuevaPagina(cliente, 'CU24');

    await iniciarSesion(pagina, CLIENTE);

    // El pedido pagado más reciente quedó sin calificar en los datos demo.
    await ir(pagina, '/tienda/pedidos');
    const enlacePedido = pagina
        .locator('a.cc-pedido', { hasText: 'Pagado' })
        .first();

    await enlacePedido.click();
    await reposo(pagina, 800);
    await pagina.locator('[data-test^="calificar-"]').first().waitFor();
    const producto = (
        (
            await pagina
                .locator('.cc-comprobante__tabla tbody tr')
                .first()
                .locator('td')
                .first()
                .innerText()
        ).split('\n')[0] ?? ''
    ).trim();

    await foto(pagina, 'CU24_01_mis_pedidos_calificar', { completa: true });

    // Diálogo con el selector de estrellas accesible y el contador de 0 a 500.
    await pagina.locator('[data-test^="calificar-"]').first().click();
    await pagina.getByRole('dialog').waitFor();
    await pagina
        .getByRole('dialog')
        .getByText('4 estrellas')
        .locator('..')
        .click();
    await escribir(
        pagina.locator('[data-test="comentario"]'),
        'Buen producto, llegó rápido y funciona como se esperaba.',
    );
    await foto(pagina, 'CU24_02_dialogo_estrellas');

    await pagina.locator('[data-test="enviar-calificacion"]').click();
    await pagina.waitForTimeout(900);
    await foto(pagina, 'CU24_03_gracias');

    // EX-02: el producto ya calificado muestra su calificación y su estado.
    await ir(pagina, pagina.url().replace(BASE, ''));
    await pagina
        .locator('[data-test="calificacion-existente"]')
        .first()
        .waitFor();
    await foto(pagina, 'CU24_04_ex02_ya_calificado', {
        elemento: pagina.locator('.cc-comprobante__tabla'),
    });

    await cliente.close();

    // Moderación: el Administrador publica u oculta.
    const admin = await nuevoContexto();
    const paginaAdmin = await nuevaPagina(admin, 'CU24-admin');

    await iniciarSesion(paginaAdmin, ADMIN);
    await ir(paginaAdmin, '/calificaciones');
    await foto(paginaAdmin, 'CU24_05_moderacion_admin', { completa: true });

    // EX-04: ocultar sin motivo no deja continuar.
    await ir(paginaAdmin, '/calificaciones?estado=PENDIENTE');
    await paginaAdmin.locator('[data-test="ocultar"]').first().click();
    await paginaAdmin.locator('[data-test="confirmar-ocultar"]').click();
    await paginaAdmin.locator('[data-test="error-motivo"]').waitFor();
    await foto(paginaAdmin, 'CU24_06_ex04_ocultar_sin_motivo');
    await paginaAdmin.keyboard.press('Escape');

    // Publicar la calificación nueva y verla en el catálogo.
    await ir(
        paginaAdmin,
        `/calificaciones?estado=PENDIENTE&producto=${encodeURIComponent(producto)}`,
    );
    await paginaAdmin.locator('[data-test="publicar"]').first().click();
    await reposo(paginaAdmin, 800);

    const publico = await nuevoContexto();
    const catalogo = await nuevaPagina(publico, 'CU24-catalogo');

    await ir(catalogo, `/tienda?q=${encodeURIComponent(producto)}`);
    await catalogo.locator('a[href^="/tienda/producto/"]').first().click();
    await reposo(catalogo, 800);
    await catalogo
        .locator('[data-test="opiniones"]')
        .evaluate((nodo) => nodo.scrollIntoView({ block: 'center' }));
    await catalogo.waitForTimeout(300);
    await foto(catalogo, 'CU24_07_producto_con_opiniones');

    await publico.close();
    await admin.close();
}

/* ── CU19 · Carrito y cotización web ────────────────────────────────────── */

async function agregarPrimerProductoAlCarrito(pagina) {
    await ir(pagina, '/tienda');
    await pagina.locator('a[href^="/tienda/producto/"]').first().click();
    await reposo(pagina, 800);
    await pagina.getByRole('button', { name: 'Agregar al carrito' }).click();
    await pagina.waitForTimeout(400);
}

async function cu19() {
    console.log('CU19 · Carrito y cotización web');
    const contexto = await nuevoContexto();
    const pagina = await nuevaPagina(contexto, 'CU19');

    // Sin sesión: el carrito se arma igual y "Generar cotización" pide iniciar sesión.
    await agregarPrimerProductoAlCarrito(pagina);
    await ir(pagina, '/tienda/carrito');
    await foto(pagina, 'CU19_01_carrito_boton_cotizar', { completa: true });

    await pagina.getByRole('button', { name: 'Generar cotización' }).click();
    await pagina.waitForURL('**/login');
    await reposo(pagina, 700);
    await foto(pagina, 'CU19_02_login_requerido');

    // Al iniciar sesión vuelve al flujo, con el carrito intacto.
    await pagina.locator('input[name=email]').fill(CLIENTE);
    await pagina.locator('input[name=password]').fill(CLAVE_DEMO);
    await pagina.locator('[data-test=login-button]').click();
    await pagina.waitForURL('**/tienda/cotizar');
    await reposo(pagina, 900);
    await foto(pagina, 'CU19_03_confirmar_contacto', { completa: true });

    await pagina.locator('[data-test="confirmar-cotizacion"]').click();
    await pagina.waitForURL('**/tienda/cotizaciones/COT-*');
    await reposo(pagina, 900);
    const codigo = (
        await pagina.locator('[data-test="codigo"]').innerText()
    ).trim();

    await foto(pagina, 'CU19_04_cotizacion_generada', { completa: true });

    // PDF de la cotización.
    const pdf = await descargar(pagina, () =>
        pagina.locator('[data-test="descargar-pdf"]').click(),
    );

    await composicion(
        contexto,
        'CU19_05_pdf_cotizacion',
        [
            {
                titulo: `PDF de la cotización ${codigo}`,
                imagen: aImagen(pdf, 130),
            },
        ],
        900,
        0,
    );

    // WhatsApp: se muestra el enlace y el mensaje que se armó; no se envía nada.
    const enlace = await pagina
        .locator('[data-test="whatsapp"]')
        .getAttribute('href');
    const mensaje = decodeURIComponent((enlace ?? '').split('?text=')[1] ?? '');

    await pagina.evaluate(
        ({ url, texto }) => {
            const panel = document.createElement('section');

            panel.style.cssText =
                'max-width:760px;margin:16px auto;padding:14px 16px;border:2px dashed #d97706;border-radius:12px;background:#fffaf0;color:#1c1410;font:13px/1.5 ui-monospace,monospace;white-space:pre-wrap;word-break:break-all';
            panel.innerHTML =
                '<strong style="font-family:system-ui">Enlace y mensaje que genera el botón (solo evidencia, no se envía nada):</strong>\n\n';
            panel.append(document.createTextNode(`${url}\n\n${texto}`));
            document.querySelector('.cc-shop__main')?.append(panel);
        },
        { url: enlace, texto: mensaje },
    );
    await foto(pagina, 'CU19_06_whatsapp_mensaje', { completa: true });

    // EX-03: el precio cambió desde que se agregó al carrito; se actualiza y se avisa antes de confirmar.
    const variantes = await pagina.evaluate(async () => {
        const respuesta = await fetch(
            '/tienda/carrito/variantes?ids=1,2,3,4,5,6',
        );

        return (await respuesta.json()).variantes;
    });
    const elegida = variantes.find((v) => v.disponible && v.stock > 1);

    await pagina.evaluate((v) => {
        localStorage.setItem(
            'cc-carrito-v1',
            JSON.stringify([
                {
                    variante_id: v.variante_id,
                    producto: v.producto,
                    slug: v.slug,
                    etiqueta: v.etiqueta,
                    sku: v.sku,
                    precio: v.precio - 20,
                    imagen: v.imagen,
                    cantidad: 1,
                    stock: v.stock,
                },
            ]),
        );
    }, elegida);
    await ir(pagina, '/tienda/cotizar');
    await pagina.locator('[data-test="avisos"]').waitFor();
    await foto(pagina, 'CU19_07_ex03_precio_actualizado', { completa: true });

    // EX-01: con el carrito vacío no se puede generar la cotización.
    await pagina.evaluate(() => localStorage.setItem('cc-carrito-v1', '[]'));
    await ir(pagina, '/tienda/carrito');
    await foto(pagina, 'CU19_08_ex01_carrito_vacio', { completa: true });

    await contexto.close();
}

/* ── CU20 · Gestión de cotizaciones ─────────────────────────────────────── */

async function abrirDetalle(pagina, codigo) {
    await ir(pagina, `/cotizaciones?q=${codigo}`);
    await pagina.locator('[data-test="ver-detalle"]').first().click();
    await pagina.getByRole('dialog').waitFor();
    await reposo(pagina, 700);
}

async function cu20() {
    console.log('CU20 · Gestión de cotizaciones');
    const contexto = await nuevoContexto();
    const pagina = await nuevaPagina(contexto, 'CU20');

    await iniciarSesion(pagina, VENDEDOR);

    // Listado con filtros aplicados.
    await ir(pagina, `/cotizaciones?desde=${fechaIso(-30)}`);
    await foto(pagina, 'CU20_01_listado_filtros', { completa: true });

    // Detalle y cambio de estado con observación.
    await abrirDetalle(pagina, 'COT-2026-00001');
    await foto(pagina, 'CU20_02_detalle_cotizacion');

    await pagina.locator('[data-test="cambiar-estado"]').click();
    await pagina
        .getByRole('dialog')
        .filter({ hasText: 'Cambiar estado' })
        .waitFor();
    await escribir(
        pagina.locator('[data-test="observacion"]'),
        'Se contactó al cliente por WhatsApp para confirmar la compra.',
    );
    await foto(pagina, 'CU20_03_cambio_estado');
    await pagina.locator('[data-test="guardar-estado"]').click();
    await reposo(pagina, 900);

    // EX-04 (y EX-03): la venta precargada compara el precio cotizado con el actual y marca lo que no tiene stock.
    await abrirDetalle(pagina, 'COT-2026-00001');
    await pagina.locator('[data-test="convertir"]').click();
    await pagina.locator('[data-test="banner-cotizacion"]').waitFor();
    await reposo(pagina, 800);
    await pagina.locator('.cc-pos-sheet__cuerpo').evaluate((nodo) => {
        nodo.scrollTop = 250;
    });
    await foto(pagina, 'CU20_05_ex04_diferencia_precios');

    // Conversión completa de la cotización atendida: precarga, venta y estado CONVERTIDA.
    await abrirDetalle(pagina, 'COT-2026-00002');
    await pagina.locator('[data-test="convertir"]').click();
    await pagina.locator('[data-test="banner-cotizacion"]').waitFor();
    await reposo(pagina, 800);
    await foto(pagina, 'CU20_04_venta_precargada_desde_cotizacion');

    await pagina.getByRole('button', { name: 'Confirmar venta' }).click();
    await pagina.waitForURL('**/ventas?creada=*');
    await reposo(pagina, 900);

    await abrirDetalle(pagina, 'COT-2026-00002');
    await foto(pagina, 'CU20_06_cotizacion_convertida');

    // EX-01: una cotización vencida no se convierte; se ofrece renovarla con precios actuales.
    await abrirDetalle(pagina, 'COT-2026-00003');
    await foto(pagina, 'CU20_07_ex01_vencida_renovar');
    await pagina.locator('[data-test="renovar"]').click();
    await reposo(pagina, 900);

    // Productos más visitados.
    await ir(pagina, '/cotizaciones/mas-visitados');
    await foto(pagina, 'CU20_08_mas_visitados', { completa: true });

    await contexto.close();
}

/* ── Ejecución ──────────────────────────────────────────────────────────── */

const CASOS = {
    CU17: cu17,
    CU16: cu16,
    CU23: cu23,
    CU22: cu22,
    CU24: cu24,
    CU19: cu19,
    CU20: cu20,
};

const pedidos = (argumentos.cu ?? Object.keys(CASOS).join(','))
    .split(',')
    .map((cu) => cu.trim().toUpperCase());

navegador = await chromium.launch({
    executablePath:
        process.env.CHROME_PATH ??
        (fs.existsSync('/usr/bin/google-chrome')
            ? '/usr/bin/google-chrome'
            : undefined),
    headless: true,
    env: {
        ...process.env,
        LANGUAGE: 'es-419:es',
        LC_ALL: process.env.LC_ALL ?? '',
    },
    args: [
        '--use-fake-ui-for-media-stream',
        '--use-fake-device-for-media-stream',
        '--no-sandbox',
        '--lang=es-419',
    ],
});

try {
    for (const cu of pedidos) {
        if (!CASOS[cu]) {
            throw new Error(
                `Caso de uso desconocido: ${cu}. Opciones: ${Object.keys(CASOS).join(', ')}`,
            );
        }

        await CASOS[cu]();
    }
} finally {
    await navegador.close();
}

if (problemas.length > 0) {
    console.warn('\nAvisos de consola durante las capturas (revísalos):');
    problemas.forEach((problema) => console.warn(`  - ${problema}`));
} else {
    console.log('\nSin errores de consola.');
}
