<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * CU08: integridad de precios en variante_producto y trazabilidad del cambio.
 *
 * - CHECK precio_venta > 0 como última barrera ante escrituras fuera de Laravel.
 * - Índice en detalle_venta(variante_id) para la consulta de variantes sin rotación.
 * - El trigger de precio guarda SKU, precio anterior y el motivo enviado por la
 *   transacción en app.motivo_precio (set_config local a la transacción).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
ALTER TABLE public.variante_producto
    ADD CONSTRAINT variante_producto_precio_venta_check CHECK (precio_venta > 0);

CREATE INDEX IF NOT EXISTS idx_detalle_venta_variante_id
    ON public.detalle_venta (variante_id);

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
            jsonb_build_object('sku', OLD.sku, 'precio_venta', OLD.precio_venta),
            jsonb_build_object('sku', NEW.sku, 'precio_venta', NEW.precio_venta),
            coalesce(
                nullif(current_setting('app.motivo_precio', true), ''),
                'Actualización de precio de venta'
            )
        );
    END IF;
    RETURN NEW;
END;
$$;
SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
ALTER TABLE public.variante_producto
    DROP CONSTRAINT IF EXISTS variante_producto_precio_venta_check;

DROP INDEX IF EXISTS public.idx_detalle_venta_variante_id;

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
SQL);
    }
};
