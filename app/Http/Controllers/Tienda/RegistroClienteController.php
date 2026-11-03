<?php

namespace App\Http\Controllers\Tienda;

use App\Concerns\PasswordValidationRules;
use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\Rol;
use App\Models\User;
use App\Services\BitacoraService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Alta de clientes de la tienda en línea. Distinto del alta de personal
 * (CU02): la cuenta queda con el rol Cliente Web y sin permisos del POS.
 */
class RegistroClienteController extends Controller
{
    use PasswordValidationRules;

    public function create(Request $request): Response
    {
        return Inertia::render('auth/Registro', [
            'desdeTienda' => str_contains((string) $request->session()->get('url.intended', ''), '/tienda'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge([
            'email' => Str::lower(trim((string) $request->input('email'))),
            'numero_documento' => trim((string) $request->input('numero_documento')),
        ]);

        $datos = $request->validate([
            'nombre' => ['required', 'string', 'min:3', 'max:200'],
            'tipo_documento' => ['required', Rule::in(['CI', 'NIT', 'PASAPORTE'])],
            'numero_documento' => ['required', 'string', 'max:50'],
            'telefono' => ['nullable', 'string', 'regex:/^[0-9+\-\s]{7,15}$/'],
            'email' => ['required', 'string', 'email', 'max:150', Rule::unique('usuario', 'email')],
            'password' => $this->passwordRules(),
        ], [
            'nombre.required' => 'Ingresá tu nombre completo.',
            'numero_documento.required' => 'Ingresá tu CI o NIT.',
            'telefono.regex' => 'El teléfono no tiene un formato válido.',
            'email.unique' => 'Ya existe una cuenta con ese correo. Iniciá sesión.',
            'password.confirmed' => 'Las claves no coinciden.',
        ]);

        $rolId = Rol::query()->where('nombre', Rol::CLIENTE_WEB)->where('estado', 'ACTIVO')->value('id');

        if ($rolId === null) {
            throw ValidationException::withMessages([
                'email' => 'El registro en línea no está disponible en este momento.',
            ]);
        }

        $user = DB::transaction(function () use ($datos, $rolId): User {
            $cliente = Cliente::query()
                ->where('numero_documento', $datos['numero_documento'])
                ->lockForUpdate()
                ->first();

            if ($cliente !== null && User::query()->where('cliente_id', $cliente->id)->exists()) {
                throw ValidationException::withMessages([
                    'numero_documento' => 'Ese documento ya tiene una cuenta en la tienda. Iniciá sesión.',
                ]);
            }

            if ($cliente === null) {
                $cliente = Cliente::query()->create([
                    'tipo_documento' => $datos['tipo_documento'],
                    'numero_documento' => $datos['numero_documento'],
                    'nombre' => trim($datos['nombre']),
                    'telefono' => $datos['telefono'] ?? null,
                    'email' => $datos['email'],
                    'estado' => 'ACTIVO',
                ]);
            } else {
                $cliente->forceFill([
                    'email' => $cliente->email ?: $datos['email'],
                    'telefono' => $cliente->telefono ?: ($datos['telefono'] ?? null),
                    'estado' => 'ACTIVO',
                ])->save();
            }

            return User::query()->create([
                'cliente_id' => $cliente->id,
                'rol_id' => $rolId,
                'email' => $datos['email'],
                'password_hash' => $datos['password'],
                'estado' => 'ACTIVO',
                'bloqueado' => false,
                'primer_login' => false,
            ]);
        });

        Auth::login($user);
        $request->session()->regenerate();

        BitacoraService::registrar(
            'Tienda',
            'REGISTRO_CLIENTE_WEB',
            'usuario',
            (int) $user->id,
            null,
            ['email' => $user->email, 'cliente_id' => $user->cliente_id],
            'Registro desde la tienda en línea',
            (int) $user->id,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => '¡Bienvenido a Computer City! Tu cuenta está lista.']);

        return redirect()->intended(route('tienda.index'));
    }
}
