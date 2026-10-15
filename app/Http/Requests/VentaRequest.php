<?php

namespace App\Http\Requests;

use App\Models\Cliente;
use App\Models\User;
use App\Models\VarianteProducto;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class VentaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->tienePermiso('ventas.crear');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'cliente_id' => [
                'nullable',
                'integer',
                'required_without:cliente_nuevo',
                function (string $atributo, mixed $valor, Closure $fail): void {
                    if ($valor === null || $valor === '') {
                        return;
                    }

                    $cliente = Cliente::query()->find($valor);

                    if ($cliente === null) {
                        $fail('El cliente indicado no existe.');

                        return;
                    }

                    if ($cliente->estado === 'INACTIVO') {
                        $fail('El cliente está INACTIVO. Reactivalo o elegí otro.');
                    }
                },
            ],
            'cliente_nuevo' => ['nullable', 'array', 'required_without:cliente_id'],
            'cliente_nuevo.tipo_documento' => ['required_with:cliente_nuevo', Rule::in(['CI', 'NIT'])],
            'cliente_nuevo.numero_documento' => [
                'required_with:cliente_nuevo',
                'string',
                'max:50',
                function (string $atributo, mixed $valor, Closure $fail): void {
                    if (! is_string($valor) || trim($valor) === '') {
                        return;
                    }

                    $existe = DB::table('cliente')
                        ->whereRaw('lower(numero_documento) = ?', [mb_strtolower(trim($valor))])
                        ->exists();

                    if ($existe) {
                        $fail('Ya existe un cliente registrado con el documento indicado.');
                    }
                },
            ],
            'cliente_nuevo.nombre' => ['required_with:cliente_nuevo', 'string', 'max:200'],
            'cliente_nuevo.telefono' => ['nullable', 'string', 'max:50', 'regex:/^[0-9+\-\s]{7,15}$/'],
            'cliente_nuevo.email' => ['nullable', 'string', 'max:150', 'email'],
            'descuento_global' => ['nullable', 'numeric', 'min:0'],
            'observacion' => ['nullable', 'string', 'max:1000'],
            'enviar_email' => ['sometimes', 'boolean'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.variante_id' => ['required', 'integer', 'exists:variante_producto,id'],
            'items.*.cantidad' => ['required', 'integer', 'min:1', 'max:100000'],
            'items.*.descuento' => ['nullable', 'numeric', 'min:0'],
            'items.*.numero_serie' => ['nullable', 'string', 'max:100'],
            'items.*.meses_garantia' => ['nullable', 'integer', 'min:0', 'max:60'],
            'pagos' => ['required', 'array', 'min:1'],
            'pagos.*.metodo_pago_id' => ['required', 'integer', 'exists:metodo_pago,id'],
            'pagos.*.monto' => ['required', 'numeric', 'gt:0'],
            'pagos.*.referencia' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * @return array<int, Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $items = $this->input('items', []);

                if (! is_array($items) || $items === []) {
                    $validator->errors()->add('items', 'La venta debe incluir al menos un producto.');

                    return;
                }

                $ids = collect($items)->pluck('variante_id')->map(fn ($id) => (int) $id)->unique()->all();
                $variantes = VarianteProducto::query()
                    ->with('producto:id,nombre,estado')
                    ->whereIn('id', $ids)
                    ->get()
                    ->keyBy('id');

                $total = 0.0;
                $pedida = [];

                foreach ($items as $indice => $item) {
                    if (! is_array($item)) {
                        continue;
                    }

                    $variante = $variantes->get((int) ($item['variante_id'] ?? 0));
                    $cantidad = (int) ($item['cantidad'] ?? 0);
                    $descuento = (float) ($item['descuento'] ?? 0);

                    if ($variante === null || $variante->estado !== 'ACTIVO' || $variante->producto?->estado !== 'ACTIVO') {
                        $validator->errors()->add("items.$indice.variante_id", 'La variante no está activa para la venta.');

                        continue;
                    }

                    $lineal = ($cantidad * (float) $variante->precio_venta) - $descuento;

                    if ($descuento > $cantidad * (float) $variante->precio_venta) {
                        $validator->errors()->add("items.$indice.descuento", 'El descuento no puede superar el subtotal de la línea.');
                    }

                    $total += $lineal;
                    $pedida[$variante->id] = ($pedida[$variante->id] ?? 0) + $cantidad;

                    if ($pedida[$variante->id] > $variante->stock_actual) {
                        $validator->errors()->add(
                            "items.$indice.cantidad",
                            'Stock insuficiente para '.$variante->sku.' (disponible: '.$variante->stock_actual.').',
                        );
                    }
                }

                $total = round($total - (float) $this->input('descuento_global', 0), 2);

                if ($total < 0) {
                    $validator->errors()->add('descuento_global', 'El descuento global no puede superar el total de la venta.');
                }

                $pagado = round(collect($this->input('pagos', []))->sum(fn ($pago) => (float) ($pago['monto'] ?? 0)), 2);

                if (abs($pagado - $total) > 0.009) {
                    $validator->errors()->add(
                        'pagos',
                        'La suma de los pagos (Bs '.number_format($pagado, 2, '.', '').') debe coincidir con el total (Bs '.number_format($total, 2, '.', '').').',
                    );
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'cliente_id.required_without' => 'Seleccioná un cliente para confirmar la venta.',
            'cliente_nuevo.required_without' => 'Completá los datos del cliente nuevo.',
            'cliente_nuevo.tipo_documento.required_with' => 'Elegí el tipo de documento (CI o NIT).',
            'cliente_nuevo.tipo_documento.in' => 'El tipo de documento debe ser CI o NIT.',
            'cliente_nuevo.numero_documento.required_with' => 'Ingresá el número de documento.',
            'cliente_nuevo.nombre.required_with' => 'Ingresá el nombre del cliente.',
            'cliente_nuevo.telefono.regex' => 'El teléfono debe tener entre 7 y 15 dígitos.',
            'cliente_nuevo.email.email' => 'El email no es válido.',
            'items.required' => 'La venta debe incluir al menos un producto.',
            'items.min' => 'La venta debe incluir al menos un producto.',
            'pagos.required' => 'Registrá al menos un pago.',
            'pagos.min' => 'Registrá al menos un pago.',
            'pagos.*.monto.gt' => 'Cada pago debe ser mayor a 0.',
            'pagos.*.metodo_pago_id.required' => 'Elegí el método de pago.',
            'items.*.cantidad.min' => 'La cantidad debe ser al menos 1.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $items = $this->input('items');
        $pagos = $this->input('pagos');

        if (is_array($items)) {
            $this->merge([
                'items' => array_map(function ($item) {
                    if (! is_array($item)) {
                        return $item;
                    }

                    if (isset($item['numero_serie']) && is_string($item['numero_serie'])) {
                        $item['numero_serie'] = trim($item['numero_serie']) ?: null;
                    }

                    return $item;
                }, $items),
            ]);
        }

        $nuevo = $this->input('cliente_nuevo');

        if (is_array($nuevo)) {
            foreach (['numero_documento', 'nombre', 'telefono', 'email'] as $campo) {
                if (isset($nuevo[$campo]) && is_string($nuevo[$campo])) {
                    $nuevo[$campo] = trim($nuevo[$campo]) ?: null;
                }
            }

            $this->merge(['cliente_nuevo' => $nuevo]);
        }

        if (is_array($pagos)) {
            $this->merge([
                'pagos' => array_map(function ($pago) {
                    if (! is_array($pago)) {
                        return $pago;
                    }

                    if (isset($pago['referencia']) && is_string($pago['referencia'])) {
                        $pago['referencia'] = trim($pago['referencia']) ?: null;
                    }

                    return $pago;
                }, $pagos),
            ]);
        }
    }
}
