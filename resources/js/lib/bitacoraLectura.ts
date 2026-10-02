export type EventoBitacora = {
    id: number;
    fecha: string;
    hora: string;
    usuario: string;
    modulo: string;
    accion: string;
    direccion_ip: string | null;
    descripcion: string;
    tipo_entidad: string | null;
    entidad_id: number | null;
    objetivo?: string | null;
    valor_anterior: unknown;
    valor_nuevo: unknown;
};

export type CambioBitacora = {
    clave: string;
    etiqueta: string;
    antes: string | null;
    ahora: string | null;
};

const OCULTOS = new Set([
    'id',
    'password_hash',
    'password_cambiada',
    'fecha_ultimo_login',
    'intentos_fallidos',
    'fecha_creacion',
    'fecha_actualizacion',
    'fecha_bloqueo',
    'nivel_bloqueo',
    'personal_id',
    'primer_login',
]);

const ETIQUETAS: Record<string, string> = {
    email: 'Correo',
    nombre_completo: 'Nombre',
    nombre: 'Nombre',
    precio_venta: 'Precio de venta',
    precio_compra: 'Precio de compra',
    estado: 'Estado',
    bloqueado: 'Bloqueado',
    telefono: 'Teléfono',
    ci: 'CI',
    cargo: 'Cargo',
    descripcion: 'Descripción',
    rol_id: 'Rol',
    sku: 'SKU',
    stock_actual: 'Stock',
    stock_minimo: 'Stock mínimo',
    modulo: 'Módulo',
    clave: 'Permiso',
    motivo: 'Motivo',
};

const ENTIDADES: Record<string, string> = {
    usuario: 'usuario',
    personal: 'personal',
    rol: 'rol',
    permiso: 'permiso',
    rol_permiso: 'permiso del rol',
    variante_producto: 'producto',
    producto: 'producto',
};

const TIPOS: Record<string, string> = {
    usuario: 'Usuario',
    personal: 'Personal',
    rol: 'Rol',
    permiso: 'Permiso',
    rol_permiso: 'Permiso del rol',
    variante_producto: 'Producto',
    producto: 'Producto',
};

const ACCIONES: Record<string, string> = {
    LOGIN: 'Ingreso',
    LOGOUT: 'Salida',
    LOGIN_FALLIDO: 'Ingreso fallido',
    LOGIN_BLOQUEADO: 'Ingreso bloqueado',
    CREAR_USUARIO: 'Alta de usuario',
    ACTUALIZAR_USUARIO: 'Usuario actualizado',
    ELIMINAR_USUARIO: 'Baja de usuario',
    BLOQUEAR_USUARIO: 'Usuario bloqueado',
    DESBLOQUEAR_USUARIO: 'Usuario habilitado',
    CAMBIAR_ESTADO_USUARIO: 'Estado de usuario',
    CAMBIAR_ROL: 'Cambio de rol',
    CAMBIAR_CLAVE: 'Cambio de clave',
    CREAR_PERSONAL: 'Alta de personal',
    ACTUALIZAR_PERSONAL: 'Personal actualizado',
    ELIMINAR_PERSONAL: 'Baja de personal',
    CREAR_ROL: 'Alta de rol',
    ACTUALIZAR_ROL: 'Rol actualizado',
    ELIMINAR_ROL: 'Baja de rol',
    ACTUALIZAR_PRECIO: 'Cambio de precio',
};

function esRecord(valor: unknown): valor is Record<string, unknown> {
    return typeof valor === 'object' && valor !== null && !Array.isArray(valor);
}

export function etiquetaCampo(clave: string): string {
    return ETIQUETAS[clave] ?? clave.replaceAll('_', ' ');
}

export function valorLegible(valor: unknown): string {
    if (valor === null || valor === undefined || valor === '') {
        return '—';
    }

    if (typeof valor === 'boolean') {
        return valor ? 'Sí' : 'No';
    }

    if (typeof valor === 'number') {
        return Number.isInteger(valor)
            ? String(valor)
            : new Intl.NumberFormat('es-BO', { minimumFractionDigits: 2 }).format(
                  valor,
              );
    }

    if (typeof valor === 'string') {
        if (/^\d{4}-\d{2}-\d{2}/.test(valor)) {
            const fecha = new Date(valor);

            if (!Number.isNaN(fecha.getTime())) {
                return fecha.toLocaleString('es-BO', {
                    dateStyle: 'short',
                    timeStyle: 'short',
                });
            }
        }

        return valor;
    }

    if (Array.isArray(valor)) {
        return valor.map(valorLegible).join(', ');
    }

    return String(valor);
}

export function cambiosDe(
    anterior: unknown,
    nuevo: unknown,
): CambioBitacora[] {
    const viejo = esRecord(anterior) ? anterior : null;
    const actual = esRecord(nuevo) ? nuevo : null;

    if (!viejo && !actual) {
        if (anterior == null && nuevo == null) {
            return [];
        }

        return [
            {
                clave: 'valor',
                etiqueta: 'Valor',
                antes: anterior == null ? null : valorLegible(anterior),
                ahora: nuevo == null ? null : valorLegible(nuevo),
            },
        ];
    }

    const claves = [
        ...new Set([
            ...Object.keys(viejo ?? {}).filter((clave) => !OCULTOS.has(clave)),
            ...Object.keys(actual ?? {}).filter((clave) => !OCULTOS.has(clave)),
        ]),
    ];

    return claves.flatMap((clave) => {
        const antesCrudo = viejo?.[clave];
        const ahoraCrudo = actual?.[clave];

        if (JSON.stringify(antesCrudo) === JSON.stringify(ahoraCrudo)) {
            return [];
        }

        return [
            {
                clave,
                etiqueta: etiquetaCampo(clave),
                antes:
                    viejo && Object.prototype.hasOwnProperty.call(viejo, clave)
                        ? valorLegible(antesCrudo)
                        : null,
                ahora:
                    actual && Object.prototype.hasOwnProperty.call(actual, clave)
                        ? valorLegible(ahoraCrudo)
                        : null,
            },
        ];
    });
}

export function etiquetaAccion(accion: string): string {
    return ACCIONES[accion] ?? accion.replaceAll('_', ' ').toLowerCase();
}

export function nombreEntidad(tipo: string | null): string {
    if (!tipo) {
        return 'registro';
    }

    return ENTIDADES[tipo] ?? tipo.replaceAll('_', ' ');
}

export function etiquetaTipo(tipo: string | null): string {
    if (!tipo) {
        return 'Registro';
    }

    return TIPOS[tipo] ?? tipo.replaceAll('_', ' ');
}

function deObjetivo(evento: EventoBitacora): string {
    const nombre = evento.objetivo?.trim();

    if (nombre) {
        return `de ${nombre}`;
    }

    return `del ${nombreEntidad(evento.tipo_entidad)}`;
}

function elObjetivo(evento: EventoBitacora): string {
    const nombre = evento.objetivo?.trim();

    if (nombre) {
        return nombre;
    }

    return `un ${nombreEntidad(evento.tipo_entidad)}`;
}

export function fraseEvento(evento: EventoBitacora): string {
    const quien = evento.usuario || 'Alguien';
    const modulo = evento.modulo || 'el sistema';
    const diffs = cambiosDe(evento.valor_anterior, evento.valor_nuevo);
    const campo = diffs[0]?.etiqueta.toLowerCase();

    if (evento.accion === 'LOGIN') {
        return `${quien} ingresó al sistema`;
    }

    if (evento.accion === 'LOGOUT') {
        return `${quien} cerró sesión`;
    }

    if (evento.accion === 'LOGIN_FALLIDO') {
        const correo =
            esRecord(evento.valor_nuevo) && typeof evento.valor_nuevo.email === 'string'
                ? evento.valor_nuevo.email
                : null;

        return correo
            ? `Intento fallido de ingreso con ${correo}`
            : 'Intento fallido de ingreso';
    }

    if (evento.accion.startsWith('CREAR')) {
        return `${quien} creó ${elObjetivo(evento)} en ${modulo}`;
    }

    if (evento.accion.startsWith('ELIMIN')) {
        return `${quien} eliminó ${elObjetivo(evento)} en ${modulo}`;
    }

    if (evento.accion === 'BLOQUEAR_USUARIO') {
        return `${quien} bloqueó ${elObjetivo(evento)} en ${modulo}`;
    }

    if (evento.accion === 'DESBLOQUEAR_USUARIO') {
        return `${quien} habilitó ${elObjetivo(evento)} en ${modulo}`;
    }

    if (evento.accion === 'CAMBIAR_CLAVE') {
        return `${quien} cambió la clave ${deObjetivo(evento)} en ${modulo}`;
    }

    if (diffs.length === 1 && campo) {
        return `${quien} cambió el ${campo} ${deObjetivo(evento)} en ${modulo}`;
    }

    if (diffs.length > 1) {
        return `${quien} cambió ${diffs.length} datos ${deObjetivo(evento)} en ${modulo}`;
    }

    return `${quien} ${etiquetaAccion(evento.accion).toLowerCase()} en ${modulo}`;
}

export function tituloCambios(cambios: CambioBitacora[]): string {
    const hayAntes = cambios.some((item) => item.antes !== null);
    const hayAhora = cambios.some((item) => item.ahora !== null);

    if (hayAhora && !hayAntes) {
        return 'Valor nuevo';
    }

    if (hayAntes && !hayAhora) {
        return 'Valor anterior';
    }

    return 'Qué cambió';
}

export function iniciales(nombre: string): string {
    return nombre
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((parte) => parte[0]?.toUpperCase() ?? '')
        .join('');
}

export function tonoAccion(accion: string): string {
    if (
        accion.includes('FALLID') ||
        accion.startsWith('ELIMIN') ||
        accion.startsWith('BLOQUEAR')
    ) {
        return 'error';
    }

    if (
        accion.startsWith('DESHABIL') ||
        accion.startsWith('ANUL') ||
        accion === 'LOGOUT'
    ) {
        return 'alerta';
    }

    if (accion.startsWith('CREAR') || accion.startsWith('APROB') || accion === 'LOGIN') {
        return 'crear';
    }

    if (accion.startsWith('REGIST')) {
        return 'registrar';
    }

    if (accion.includes('AJUST')) {
        return 'ajustar';
    }

    if (accion.startsWith('ACTUAL') || accion.startsWith('CAMBI')) {
        return 'actualizar';
    }

    return 'neutro';
}
