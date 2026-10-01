<?php

namespace App\Http\Controllers;

use App\Http\Requests\RolRequest;
use App\Models\Permiso;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RolController extends Controller
{
    /** Permisos que el rol Administrador nunca puede perder. */
    public const ESENCIALES = ['usuarios.administrar', 'roles.administrar', 'bitacora.ver'];

    public function index(Request $request): Response
    {
        $roles = Rol::query()
            ->with('permisos:id,clave')
            ->orderBy('id')
            ->get()
            ->map(fn (Rol $rol) => [
                'id' => $rol->id,
                'nombre' => $rol->nombre,
                'descripcion' => $rol->descripcion,
                'estado' => $rol->estado ?? 'ACTIVO',
                'es_sistema' => (bool) $rol->es_sistema,
                'protegido' => $this->esAdministradorPrincipal($rol),
                'usuarios' => User::query()->where('rol_id', $rol->id)->count(),
                'usuarios_activos' => $this->usuariosActivos($rol),
                'permisos' => $rol->permisos->pluck('id')->values(),
            ])
            ->values();

        return Inertia::render('Roles', [
            'roles' => $roles,
            'permisos' => Permiso::query()
                ->where(fn ($q) => $q->whereNull('estado')->orWhere('estado', 'ACTIVO'))
                ->orderBy('modulo')
                ->orderBy('nombre')
                ->get(['id', 'clave', 'nombre', 'modulo']),
            'esenciales' => self::ESENCIALES,
            'seleccionado' => $request->integer('rol') ?: null,
        ]);
    }

    public function store(RolRequest $request): RedirectResponse
    {
        $datos = $request->validated();

        $rol = DB::transaction(function () use ($datos): Rol {
            $rol = Rol::query()->create([
                'nombre' => $datos['nombre'],
                'descripcion' => $datos['descripcion'],
                'es_sistema' => false,
                'estado' => ($datos['activo'] ?? true) ? 'ACTIVO' : 'INACTIVO',
            ]);
            $rol->permisos()->sync($datos['permisos']);

            return $rol;
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Rol creado.']);

        return redirect()->route('roles.index', ['rol' => $rol->id]);
    }

    public function update(RolRequest $request, Rol $rol): RedirectResponse
    {
        $datos = $request->validated();

        if ($this->esAdministradorPrincipal($rol)) {
            if (mb_strtolower($datos['nombre']) !== mb_strtolower($rol->nombre)) {
                throw ValidationException::withMessages([
                    'nombre' => 'El rol Administrador no se puede renombrar.',
                ]);
            }

            $clavesEnviadas = Permiso::query()
                ->whereIn('id', $datos['permisos'])
                ->pluck('clave')
                ->all();

            if (array_diff(self::ESENCIALES, $clavesEnviadas) !== []) {
                throw ValidationException::withMessages([
                    'permisos' => 'Protección activa: el rol Administrador debe conservar los permisos esenciales de seguridad (usuarios, roles y bitácora).',
                ]);
            }
        }

        DB::transaction(function () use ($rol, $datos): void {
            $rol->forceFill([
                'nombre' => $datos['nombre'],
                'descripcion' => $datos['descripcion'],
            ])->save();
            $rol->permisos()->sync($datos['permisos']);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Rol actualizado.']);

        return back();
    }

    public function estado(Request $request, Rol $rol): RedirectResponse
    {
        $datos = $request->validate([
            'estado' => ['required', Rule::in(['ACTIVO', 'INACTIVO'])],
        ]);

        if ($datos['estado'] === 'INACTIVO') {
            if ($this->esAdministradorPrincipal($rol)) {
                return $this->rechazar('El rol Administrador no se puede desactivar.');
            }

            if ($this->usuariosActivos($rol) > 0) {
                return $this->rechazar('No se puede desactivar un rol con usuarios activos. Reasigná esos usuarios primero.');
            }
        }

        $rol->forceFill(['estado' => $datos['estado']])->save();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $datos['estado'] === 'INACTIVO' ? 'Rol desactivado.' : 'Rol activado.',
        ]);

        return back();
    }

    private function esAdministradorPrincipal(Rol $rol): bool
    {
        return (bool) $rol->es_sistema && mb_strtolower($rol->nombre) === 'administrador';
    }

    private function usuariosActivos(Rol $rol): int
    {
        return User::query()
            ->where('rol_id', $rol->id)
            ->where(fn ($q) => $q->whereNull('estado')->orWhere('estado', 'ACTIVO'))
            ->count();
    }

    private function rechazar(string $mensaje): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'error', 'message' => $mensaje]);

        return back();
    }
}
