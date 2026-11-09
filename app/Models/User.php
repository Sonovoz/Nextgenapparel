<?php

namespace App\Models;

use App\Dominio\Sistema\Acceso\Rol;
use App\Notifications\Cuenta\RestablecerContrasena;
use App\Notifications\Cuenta\VerificarCorreo;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'activo'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser, MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, LogsActivity, Notifiable {
        LogsActivity::shouldLogEvent as private registraEvento;
    }

    /** @var array<string, mixed> */
    protected $attributes = [
        'activo' => true,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'registro_pendiente' => 'array',
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'activo' => 'boolean',
        ];
    }

    /**
     * Solo entran en el backend los usuarios activos con uno de los cuatro
     * roles del equipo. Los futuros clientes no tendrán ninguno.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->activo && $this->hasAnyRole(Rol::valores());
    }

    /** Siempre en minúsculas: en MariaDB «Ana@x.com» y «ana@x.com» son el mismo; en SQLite, no (revisión N3, B1). */
    protected function email(): Attribute
    {
        return Attribute::set(fn (?string $valor) => $valor === null ? null : mb_strtolower(trim($valor)));
    }

    /** Correo de verificación de la tienda (paso 5.2), en el idioma del cliente. */
    public function sendEmailVerificationNotification(): void
    {
        $this->notify((new VerificarCorreo)->locale($this->idiomaCorreo()));
    }

    /** Enlace para restablecer la contraseña desde la tienda (paso 5.2). */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify((new RestablecerContrasena($token))->locale($this->idiomaCorreo()));
    }

    private function idiomaCorreo(): string
    {
        return $this->cliente?->idioma ?? app()->getLocale();
    }

    /**
     * Los clientes de la tienda no van al registro de actividad del equipo (D57): sus
     * cambios no se apuntan (sus altas se crean sin registro, RegistrarCliente).
     */
    protected function shouldLogEvent(string $eventName): bool
    {
        // Solo los cambios: al borrar, HasRoles ya ha quitado los roles y la baja de un
        // empleado no se registraría (revisión N3, M2). Las bajas se registran siempre.
        if ($eventName === 'updated' && ! $this->roles()->exists()) {
            return false;
        }

        return $this->registraEvento($eventName);
    }

    /**
     * Ficha de cliente de la tienda, si el usuario compra (fase 5). Los clientes no tienen rol.
     *
     * @return HasOne<Cliente, $this>
     */
    public function cliente(): HasOne
    {
        return $this->hasOne(Cliente::class);
    }

    public function rol(): ?Rol
    {
        $nombre = $this->getRoleNames()->first();

        return $nombre !== null ? Rol::tryFrom($nombre) : null;
    }

    public function esSuperadministrador(): bool
    {
        return $this->hasRole(Rol::Superadministrador->value);
    }

    /**
     * Registro de actividad: altas y cambios de nombre, correo y estado. La
     * contraseña nunca se registra; los cambios de rol se apuntan aparte.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('usuarios')
            ->logOnly(['name', 'email', 'activo'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $evento): string => match ($evento) {
                'created' => 'Usuario creado',
                'updated' => 'Usuario modificado',
                'deleted' => 'Usuario eliminado',
                default => $evento,
            });
    }
}
