<?php

namespace App\Http\Controllers;

use App\Http\Requests\UsuarioRequest;
use App\Models\Personal;
use App\Models\Rol;
use App\Models\User;
use App\Services\BitacoraService;
use App\Services\ClaveTemporalService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class UsuarioController extends Controller
{
    private const POR_PAGINA = 8;

    public function __construct(private ClaveTemporalService $claves) {}

    public function index(Request $request): Response
    {
        $filtros = $this->filtros($request);
        $pagina = $this->consulta($filtros)->paginate(self::POR_PAGINA)->withQueryString();
        $unicosAdmin = $this->administradoresActivos();
        $proteger = $unicosAdmin->count() === 1 ? $unicosAdmin->first() : null;

        return Inertia::render('Usuarios', [
            'usuarios' => [
                'data' => $pagina->getCollection()
                    ->map(fn (User $u) => $this->presentar($u, $request->user(), $proteger))
                    ->values(),
                'meta' => [
                    'current_page' => $pagina->currentPage(),
                    'last_page' => max(1, $pagina->lastPage()),
                    'total' => $pagina->total(),
                    'from' => $pagina->firstItem(),
                    'to' => $pagina->lastItem(),
                ],
            ],
            'filtros' => $filtros,
            'opciones' => [
                'roles' => Rol::query()->where('estado', 'ACTIVO')->orderBy('nombre')->get(['id', 'nombre']),
                'personal' => Personal::query()->orderBy('nombre_completo')->get(['id', 'nombre_completo']),
                'personal_disponible' => Personal::query()
                    ->whereNotIn('id', User::query()->whereNotNull('personal_id')->select('personal_id'))
                    ->orderBy('nombre_completo')
                    ->get(['id', 'nombre_completo', 'cargo', 'ci']),
            ],
        ]);
    }

    public function exportar(Request $request): StreamedResponse
    {
        $filtros = $this->filtros($request);
        $consulta = $this->consulta($filtros);

        return response()->streamDownload(function () use ($consulta): void {
            $salida = fopen('php://output', 'w');
            fwrite($salida, "\xEF\xBB\xBF");
            fputcsv($salida, ['Usuario', 'Correo', 'Rol', 'Personal asociado', 'Cargo', 'Estado']);

            foreach ($consulta->cursor() as $u) {
                fputcsv($salida, [
                    $u->personal?->nombre_completo ?? '',
                    $u->email,
                    $u->rol?->nombre ?? '',
                    $u->personal?->nombre_completo ?? '',
                    $u->personal?->cargo ?? '',
                    $u->estado ?? 'ACTIVO',
                ]);
            }

            fclose($salida);
        }, 'usuarios-'.now()->format('Y-m-d').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function store(UsuarioRequest $request): RedirectResponse
    {
        $datos = $request->validated();
        $clave = $this->claves->generar();

        $user = DB::transaction(function () use ($datos, $clave): User {
            if ($datos['personal_modo'] === 'nuevo') {
                $personal = Personal::query()->create([
                    'nombre_completo' => trim($datos['nombre_completo']),
                    'ci' => $datos['ci'],
                    'telefono' => $datos['telefono'] ?? null,
                    'cargo' => $datos['cargo'],
                    'estado' => 'ACTIVO',
                ]);
                $personalId = $personal->id;
            } else {
                $personalId = (int) $datos['personal_id'];
            }

            $user = new User([
                'personal_id' => $personalId,
                'rol_id' => $datos['rol_id'],
                'email' => $datos['email'],
                'estado' => 'ACTIVO',
                'bloqueado' => false,
            ]);
            $user->password_hash = $clave;
            $user->primer_login = true;
            $user->save();

            return $user;
        });

        return $this->enviarClave(
            $user,
            $clave,
            'Usuario creado. La clave temporal se envió a '.$user->email.'.',
            'Usuario creado, pero no se pudo enviar el correo. Usá "Reenviar clave" cuando el correo esté configurado.',
        );
    }

    public function update(UsuarioRequest $request, User $usuario): RedirectResponse
    {
        $datos = $request->validated();

        if ((int) $datos['rol_id'] !== (int) $usuario->rol_id && $this->esUltimoAdministrador($usuario)) {
            return $this->rechazar('No podés cambiar el rol del último administrador activo.');
        }

        DB::transaction(function () use ($usuario, $datos): void {
            $usuario->forceFill([
                'email' => $datos['email'],
                'rol_id' => $datos['rol_id'],
            ])->save();

            if ($usuario->personal_id !== null) {
                Personal::query()->whereKey($usuario->personal_id)->update([
                    'nombre_completo' => trim($datos['nombre_completo']),
                    'telefono' => $datos['telefono'] ?? null,
                    'cargo' => $datos['cargo'] ?? null,
                ]);
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Usuario actualizado.']);

        return back();
    }

    public function estado(Request $request, User $usuario): RedirectResponse
    {
        $datos = $request->validate([
            'estado' => ['required', Rule::in(['ACTIVO', 'INACTIVO'])],
        ]);

        if ($datos['estado'] === 'INACTIVO') {
            if ($usuario->is($request->user())) {
                return $this->rechazar('No podés deshabilitar tu propia cuenta.');
            }

            if ($this->esUltimoAdministrador($usuario)) {
                return $this->rechazar('No podés deshabilitar al último administrador activo.');
            }
        }

        $usuario->forceFill(['estado' => $datos['estado']])->save();

        if ($datos['estado'] === 'INACTIVO') {
            DB::table('sessions')->where('user_id', $usuario->id)->delete();
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $datos['estado'] === 'INACTIVO' ? 'Usuario deshabilitado.' : 'Usuario habilitado.',
        ]);

        return back();
    }

    public function reenviarClave(User $usuario): RedirectResponse
    {
        if (($usuario->estado ?? 'ACTIVO') !== 'ACTIVO') {
            return $this->rechazar('Habilitá al usuario antes de reenviar la clave.');
        }

        $clave = $this->claves->asignar($usuario);
        DB::table('sessions')->where('user_id', $usuario->id)->delete();

        return $this->enviarClave(
            $usuario,
            $clave,
            'Se envió una nueva clave temporal a '.$usuario->email.'.',
            'No se pudo enviar el correo. Revisá la configuración de correo e intentá de nuevo.',
        );
    }

    private function enviarClave(User $user, string $clave, string $exito, string $fallo): RedirectResponse
    {
        try {
            $this->claves->enviar($user, $clave);
            BitacoraService::registrar(
                'Seguridad',
                'ENVIAR_CLAVE_TEMPORAL',
                'usuario',
                $user->id,
                null,
                ['email' => $user->email],
                'Clave temporal enviada por correo',
                auth()->id(),
            );
            Inertia::flash('toast', ['type' => 'success', 'message' => $exito]);
        } catch (Throwable $excepcion) {
            report($excepcion);
            Inertia::flash('toast', ['type' => 'error', 'message' => $fallo]);
        }

        return back();
    }

    private function rechazar(string $mensaje): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'error', 'message' => $mensaje]);

        return back();
    }

    /**
     * @return Collection<int, int>
     */
    private function administradoresActivos(): Collection
    {
        return User::query()
            ->where(fn (Builder $q) => $q->whereNull('estado')->orWhere('estado', 'ACTIVO'))
            ->where(fn (Builder $q) => $q->whereNull('bloqueado')->orWhere('bloqueado', false))
            ->whereHas('rol', fn (Builder $q) => $q
                ->where('estado', 'ACTIVO')
                ->whereHas('permisos', fn (Builder $p) => $p->where('clave', 'usuarios.administrar')))
            ->pluck('id');
    }

    private function esUltimoAdministrador(User $usuario): bool
    {
        $admins = $this->administradoresActivos();

        return $admins->count() === 1 && $admins->first() === $usuario->id;
    }

    /**
     * @return array{q: string, rol: string, estado: string, personal: string}
     */
    private function filtros(Request $request): array
    {
        $datos = $request->validate([
            'q' => ['nullable', 'string', 'max:150'],
            'rol' => ['nullable', 'integer'],
            'estado' => ['nullable', Rule::in(['ACTIVO', 'INACTIVO'])],
            'personal' => ['nullable', 'integer'],
        ]);

        return [
            'q' => trim((string) ($datos['q'] ?? '')),
            'rol' => isset($datos['rol']) ? (string) $datos['rol'] : '',
            'estado' => $datos['estado'] ?? '',
            'personal' => isset($datos['personal']) ? (string) $datos['personal'] : '',
        ];
    }

    /**
     * @param  array{q: string, rol: string, estado: string, personal: string}  $filtros
     * @return Builder<User>
     */
    private function consulta(array $filtros): Builder
    {
        $operador = DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';

        return User::query()
            ->with(['personal', 'rol'])
            ->when($filtros['q'] !== '', function (Builder $query) use ($filtros, $operador): void {
                $texto = '%'.addcslashes($filtros['q'], '%_\\').'%';
                $query->where(function (Builder $q) use ($texto, $operador): void {
                    $q->where('email', $operador, $texto)
                        ->orWhereHas('personal', fn (Builder $p) => $p->where('nombre_completo', $operador, $texto));
                });
            })
            ->when($filtros['rol'] !== '', fn (Builder $q) => $q->where('rol_id', $filtros['rol']))
            ->when($filtros['personal'] !== '', fn (Builder $q) => $q->where('personal_id', $filtros['personal']))
            ->when($filtros['estado'] === 'ACTIVO', fn (Builder $q) => $q->where(
                fn (Builder $e) => $e->whereNull('estado')->orWhere('estado', 'ACTIVO'),
            ))
            ->when($filtros['estado'] === 'INACTIVO', fn (Builder $q) => $q->where('estado', 'INACTIVO'))
            ->orderBy('id');
    }

    /**
     * @return array<string, mixed>
     */
    private function presentar(User $u, ?User $actual, ?int $unicoAdminId): array
    {
        return [
            'id' => $u->id,
            'nombre' => $u->personal?->nombre_completo ?? $u->email,
            'email' => $u->email,
            'rol_id' => $u->rol_id,
            'rol' => $u->rol?->nombre,
            'personal_id' => $u->personal_id,
            'cargo' => $u->personal?->cargo,
            'ci' => $u->personal?->ci,
            'telefono' => $u->personal?->telefono,
            'estado' => $u->estado ?? 'ACTIVO',
            'primer_login' => (bool) $u->primer_login,
            'es_actual' => $actual !== null && $u->id === $actual->id,
            'protegido' => $unicoAdminId !== null && $u->id === $unicoAdminId,
        ];
    }
}
