<?php

namespace App\Models;

use App\Dominio\Sistema\Acceso\Rol;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'activo'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, LogsActivity, Notifiable;

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

    /** Ficha de cliente de la tienda, si el usuario compra (fase 5). Los clientes no tienen rol. */
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
