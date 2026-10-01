<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Bitácora de seguridad: cambios en usuario/personal/rol/permisos
 * y contexto de sesión (app.usuario_id / app.direccion_ip) para triggers.
 *
 * Login/logout se registran desde Laravel; los triggers cubren el SQL.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION public.fn_app_usuario_id() RETURNS integer
    LANGUAGE plpgsql STABLE
AS $$
DECLARE
    v text;
BEGIN
    v := nullif(current_setting('app.usuario_id', true), '');
    IF v IS NULL THEN
        RETURN NULL;
    END IF;
    RETURN v::integer;
EXCEPTION
    WHEN others THEN
        RETURN NULL;
END;
$$;

CREATE OR REPLACE FUNCTION public.fn_app_direccion_ip() RETURNS character varying
    LANGUAGE plpgsql STABLE
AS $$
BEGIN
    RETURN nullif(current_setting('app.direccion_ip', true), '');
EXCEPTION
    WHEN others THEN
        RETURN NULL;
END;
$$;

CREATE OR REPLACE FUNCTION public.fn_bitacora_insert(
    p_modulo character varying,
    p_accion character varying,
    p_tipo_entidad character varying,
    p_entidad_id bigint,
    p_valor_anterior jsonb,
    p_valor_nuevo jsonb,
    p_motivo text
) RETURNS void
    LANGUAGE plpgsql
AS $$
BEGIN
    INSERT INTO public.bitacora_auditoria (
        usuario_id, modulo, accion, tipo_entidad, entidad_id,
        valor_anterior, valor_nuevo, motivo, direccion_ip, fecha_hora
    ) VALUES (
        public.fn_app_usuario_id(),
        p_modulo,
        p_accion,
        p_tipo_entidad,
        p_entidad_id,
        p_valor_anterior,
        p_valor_nuevo,
        p_motivo,
        public.fn_app_direccion_ip(),
        clock_timestamp()
    );
END;
$$;

CREATE OR REPLACE FUNCTION public.fn_trg_auditoria_cambio_precio() RETURNS trigger
    LANGUAGE plpgsql
AS $$
BEGIN
    IF NEW.precio_venta IS DISTINCT FROM OLD.precio_venta THEN
        PERFORM public.fn_bitacora_insert(
            'Inventario',
            'ACTUALIZAR_PRECIO',
            'variante_producto',
            NEW.id,
            jsonb_build_object('precio_venta', OLD.precio_venta),
            jsonb_build_object('precio_venta', NEW.precio_venta),
            'Actualización de precio de venta'
        );
    END IF;
    RETURN NEW;
END;
$$;

CREATE OR REPLACE FUNCTION public.fn_json_usuario(r public.usuario) RETURNS jsonb
    LANGUAGE sql IMMUTABLE
AS $$
    SELECT jsonb_build_object(
        'id', r.id,
        'personal_id', r.personal_id,
        'rol_id', r.rol_id,
        'email', r.email,
        'estado', r.estado,
        'intentos_fallidos', r.intentos_fallidos,
        'bloqueado', r.bloqueado,
        'primer_login', r.primer_login,
        'fecha_ultimo_login', r.fecha_ultimo_login,
        'fecha_bloqueo', r.fecha_bloqueo,
        'nivel_bloqueo', r.nivel_bloqueo,
        'password_cambiada', false
    );
$$;

CREATE OR REPLACE FUNCTION public.fn_trg_bitacora_usuario() RETURNS trigger
    LANGUAGE plpgsql
AS $$
DECLARE
    v_old jsonb;
    v_new jsonb;
    v_accion text;
    v_motivo text;
BEGIN
    IF TG_OP = 'INSERT' THEN
        v_new := public.fn_json_usuario(NEW);
        PERFORM public.fn_bitacora_insert(
            'Seguridad', 'CREAR_USUARIO', 'usuario', NEW.id,
            NULL, v_new, 'Alta de usuario'
        );
        RETURN NEW;
    END IF;

    IF TG_OP = 'DELETE' THEN
        v_old := public.fn_json_usuario(OLD);
        PERFORM public.fn_bitacora_insert(
            'Seguridad', 'ELIMINAR_USUARIO', 'usuario', OLD.id,
            v_old, NULL, 'Baja de usuario'
        );
        RETURN OLD;
    END IF;

    -- UPDATE: ignorar ruido de login (solo fecha_ultimo_login / intentos)
    IF NEW.personal_id IS NOT DISTINCT FROM OLD.personal_id
       AND NEW.rol_id IS NOT DISTINCT FROM OLD.rol_id
       AND NEW.email IS NOT DISTINCT FROM OLD.email
       AND NEW.password_hash IS NOT DISTINCT FROM OLD.password_hash
       AND NEW.estado IS NOT DISTINCT FROM OLD.estado
       AND NEW.bloqueado IS NOT DISTINCT FROM OLD.bloqueado
       AND NEW.primer_login IS NOT DISTINCT FROM OLD.primer_login
       AND NEW.fecha_bloqueo IS NOT DISTINCT FROM OLD.fecha_bloqueo
       AND NEW.nivel_bloqueo IS NOT DISTINCT FROM OLD.nivel_bloqueo
       AND (
            NEW.fecha_ultimo_login IS DISTINCT FROM OLD.fecha_ultimo_login
            OR NEW.intentos_fallidos IS DISTINCT FROM OLD.intentos_fallidos
       ) THEN
        RETURN NEW;
    END IF;

    v_old := public.fn_json_usuario(OLD);
    v_new := public.fn_json_usuario(NEW);

    IF NEW.password_hash IS DISTINCT FROM OLD.password_hash THEN
        v_new := v_new || jsonb_build_object('password_cambiada', true);
        v_old := v_old || jsonb_build_object('password_cambiada', false);
    END IF;

    v_accion := 'ACTUALIZAR_USUARIO';
    v_motivo := 'Actualización de usuario';

    IF NEW.rol_id IS DISTINCT FROM OLD.rol_id THEN
        v_accion := 'CAMBIAR_ROL';
        v_motivo := 'Cambio de rol de usuario';
    ELSIF NEW.bloqueado IS DISTINCT FROM OLD.bloqueado AND NEW.bloqueado IS TRUE THEN
        v_accion := 'BLOQUEAR_USUARIO';
        v_motivo := 'Usuario bloqueado';
    ELSIF NEW.bloqueado IS DISTINCT FROM OLD.bloqueado AND COALESCE(NEW.bloqueado, false) IS FALSE THEN
        v_accion := 'DESBLOQUEAR_USUARIO';
        v_motivo := 'Usuario desbloqueado';
    ELSIF NEW.estado IS DISTINCT FROM OLD.estado THEN
        v_accion := 'CAMBIAR_ESTADO_USUARIO';
        v_motivo := 'Cambio de estado de usuario';
    ELSIF NEW.password_hash IS DISTINCT FROM OLD.password_hash THEN
        v_accion := 'CAMBIAR_CLAVE';
        v_motivo := 'Cambio de contraseña';
    END IF;

    PERFORM public.fn_bitacora_insert(
        'Seguridad', v_accion, 'usuario', NEW.id,
        v_old, v_new, v_motivo
    );
    RETURN NEW;
END;
$$;

CREATE OR REPLACE FUNCTION public.fn_trg_bitacora_personal() RETURNS trigger
    LANGUAGE plpgsql
AS $$
DECLARE
    v_old jsonb;
    v_new jsonb;
BEGIN
    IF TG_OP = 'INSERT' THEN
        v_new := to_jsonb(NEW);
        PERFORM public.fn_bitacora_insert(
            'Seguridad', 'CREAR_PERSONAL', 'personal', NEW.id,
            NULL, v_new, 'Alta de personal'
        );
        RETURN NEW;
    ELSIF TG_OP = 'DELETE' THEN
        v_old := to_jsonb(OLD);
        PERFORM public.fn_bitacora_insert(
            'Seguridad', 'ELIMINAR_PERSONAL', 'personal', OLD.id,
            v_old, NULL, 'Baja de personal'
        );
        RETURN OLD;
    END IF;

    IF to_jsonb(NEW) = to_jsonb(OLD) THEN
        RETURN NEW;
    END IF;

    v_old := to_jsonb(OLD);
    v_new := to_jsonb(NEW);
    PERFORM public.fn_bitacora_insert(
        'Seguridad', 'ACTUALIZAR_PERSONAL', 'personal', NEW.id,
        v_old, v_new, 'Actualización de personal'
    );
    RETURN NEW;
END;
$$;

CREATE OR REPLACE FUNCTION public.fn_trg_bitacora_rol() RETURNS trigger
    LANGUAGE plpgsql
AS $$
DECLARE
    v_old jsonb;
    v_new jsonb;
BEGIN
    IF TG_OP = 'INSERT' THEN
        v_new := to_jsonb(NEW);
        PERFORM public.fn_bitacora_insert(
            'Seguridad', 'CREAR_ROL', 'rol', NEW.id,
            NULL, v_new, 'Alta de rol'
        );
        RETURN NEW;
    ELSIF TG_OP = 'DELETE' THEN
        v_old := to_jsonb(OLD);
        PERFORM public.fn_bitacora_insert(
            'Seguridad', 'ELIMINAR_ROL', 'rol', OLD.id,
            v_old, NULL, 'Baja de rol'
        );
        RETURN OLD;
    END IF;

    IF to_jsonb(NEW) = to_jsonb(OLD) THEN
        RETURN NEW;
    END IF;

    v_old := to_jsonb(OLD);
    v_new := to_jsonb(NEW);
    PERFORM public.fn_bitacora_insert(
        'Seguridad', 'ACTUALIZAR_ROL', 'rol', NEW.id,
        v_old, v_new, 'Actualización de rol'
    );
    RETURN NEW;
END;
$$;

CREATE OR REPLACE FUNCTION public.fn_trg_bitacora_rol_permiso() RETURNS trigger
    LANGUAGE plpgsql
AS $$
BEGIN
    IF TG_OP = 'INSERT' THEN
        PERFORM public.fn_bitacora_insert(
            'Seguridad', 'ASIGNAR_PERMISO', 'rol_permiso', NEW.id,
            NULL,
            jsonb_build_object('rol_id', NEW.rol_id, 'permiso_id', NEW.permiso_id),
            'Permiso asignado a rol'
        );
        RETURN NEW;
    ELSIF TG_OP = 'DELETE' THEN
        PERFORM public.fn_bitacora_insert(
            'Seguridad', 'QUITAR_PERMISO', 'rol_permiso', OLD.id,
            jsonb_build_object('rol_id', OLD.rol_id, 'permiso_id', OLD.permiso_id),
            NULL,
            'Permiso quitado de rol'
        );
        RETURN OLD;
    END IF;

    IF NEW.rol_id IS NOT DISTINCT FROM OLD.rol_id
       AND NEW.permiso_id IS NOT DISTINCT FROM OLD.permiso_id THEN
        RETURN NEW;
    END IF;

    PERFORM public.fn_bitacora_insert(
        'Seguridad', 'ACTUALIZAR_ROL_PERMISO', 'rol_permiso', NEW.id,
        jsonb_build_object('rol_id', OLD.rol_id, 'permiso_id', OLD.permiso_id),
        jsonb_build_object('rol_id', NEW.rol_id, 'permiso_id', NEW.permiso_id),
        'Cambio en asignación rol-permiso'
    );
    RETURN NEW;
END;
$$;

CREATE OR REPLACE FUNCTION public.fn_trg_bitacora_permiso() RETURNS trigger
    LANGUAGE plpgsql
AS $$
DECLARE
    v_old jsonb;
    v_new jsonb;
BEGIN
    IF TG_OP = 'INSERT' THEN
        v_new := to_jsonb(NEW);
        PERFORM public.fn_bitacora_insert(
            'Seguridad', 'CREAR_PERMISO', 'permiso', NEW.id,
            NULL, v_new, 'Alta de permiso'
        );
        RETURN NEW;
    ELSIF TG_OP = 'DELETE' THEN
        v_old := to_jsonb(OLD);
        PERFORM public.fn_bitacora_insert(
            'Seguridad', 'ELIMINAR_PERMISO', 'permiso', OLD.id,
            v_old, NULL, 'Baja de permiso'
        );
        RETURN OLD;
    END IF;

    IF to_jsonb(NEW) = to_jsonb(OLD) THEN
        RETURN NEW;
    END IF;

    v_old := to_jsonb(OLD);
    v_new := to_jsonb(NEW);
    PERFORM public.fn_bitacora_insert(
        'Seguridad', 'ACTUALIZAR_PERMISO', 'permiso', NEW.id,
        v_old, v_new, 'Actualización de permiso'
    );
    RETURN NEW;
END;
$$;

DROP TRIGGER IF EXISTS trg_bitacora_usuario ON public.usuario;
CREATE TRIGGER trg_bitacora_usuario
    AFTER INSERT OR UPDATE OR DELETE ON public.usuario
    FOR EACH ROW EXECUTE FUNCTION public.fn_trg_bitacora_usuario();

DROP TRIGGER IF EXISTS trg_bitacora_personal ON public.personal;
CREATE TRIGGER trg_bitacora_personal
    AFTER INSERT OR UPDATE OR DELETE ON public.personal
    FOR EACH ROW EXECUTE FUNCTION public.fn_trg_bitacora_personal();

DROP TRIGGER IF EXISTS trg_bitacora_rol ON public.rol;
CREATE TRIGGER trg_bitacora_rol
    AFTER INSERT OR UPDATE OR DELETE ON public.rol
    FOR EACH ROW EXECUTE FUNCTION public.fn_trg_bitacora_rol();

DROP TRIGGER IF EXISTS trg_bitacora_rol_permiso ON public.rol_permiso;
CREATE TRIGGER trg_bitacora_rol_permiso
    AFTER INSERT OR UPDATE OR DELETE ON public.rol_permiso
    FOR EACH ROW EXECUTE FUNCTION public.fn_trg_bitacora_rol_permiso();

DROP TRIGGER IF EXISTS trg_bitacora_permiso ON public.permiso;
CREATE TRIGGER trg_bitacora_permiso
    AFTER INSERT OR UPDATE OR DELETE ON public.permiso
    FOR EACH ROW EXECUTE FUNCTION public.fn_trg_bitacora_permiso();
SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
DROP TRIGGER IF EXISTS trg_bitacora_permiso ON public.permiso;
DROP TRIGGER IF EXISTS trg_bitacora_rol_permiso ON public.rol_permiso;
DROP TRIGGER IF EXISTS trg_bitacora_rol ON public.rol;
DROP TRIGGER IF EXISTS trg_bitacora_personal ON public.personal;
DROP TRIGGER IF EXISTS trg_bitacora_usuario ON public.usuario;

DROP FUNCTION IF EXISTS public.fn_trg_bitacora_permiso();
DROP FUNCTION IF EXISTS public.fn_trg_bitacora_rol_permiso();
DROP FUNCTION IF EXISTS public.fn_trg_bitacora_rol();
DROP FUNCTION IF EXISTS public.fn_trg_bitacora_personal();
DROP FUNCTION IF EXISTS public.fn_trg_bitacora_usuario();
DROP FUNCTION IF EXISTS public.fn_json_usuario(public.usuario);
DROP FUNCTION IF EXISTS public.fn_bitacora_insert(character varying, character varying, character varying, bigint, jsonb, jsonb, text);
DROP FUNCTION IF EXISTS public.fn_app_direccion_ip();
DROP FUNCTION IF EXISTS public.fn_app_usuario_id();

-- Restaura el trigger de precio sin contexto de sesión.
CREATE OR REPLACE FUNCTION public.fn_trg_auditoria_cambio_precio() RETURNS trigger
    LANGUAGE plpgsql
AS $$
BEGIN
    IF NEW.precio_venta IS DISTINCT FROM OLD.precio_venta THEN
        INSERT INTO bitacora_auditoria (modulo, accion, tipo_entidad, entidad_id,
                                         valor_anterior, valor_nuevo, motivo)
        VALUES ('Inventario', 'ACTUALIZAR_PRECIO', 'variante_producto', NEW.id,
                jsonb_build_object('precio_venta', OLD.precio_venta),
                jsonb_build_object('precio_venta', NEW.precio_venta),
                'Actualización de precio de venta');
    END IF;
    RETURN NEW;
END;
$$;
SQL);
    }
};
