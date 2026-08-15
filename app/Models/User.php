<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $table = 'm_users';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'role',
        'id_turnamen',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    protected static function booted()
    {
        static::saved(function (User $user) {
            if ($user->isAdmin() || ! Schema::hasTable('user_turnamen')) {
                return;
            }

            if ($user->id_turnamen) {
                $alreadyAssigned = $user->assignedTurnamen()
                    ->where('m_turnamen.id', $user->id_turnamen)
                    ->exists();

                if (! $alreadyAssigned) {
                    $user->assignedTurnamen()->syncWithoutDetaching([(int) $user->id_turnamen]);
                }
            }
        });
    }

    public function turnamen()
    {
        return $this->belongsTo(Turnamen::class, 'id_turnamen');
    }

    public function assignedTurnamen()
    {
        return $this->belongsToMany(Turnamen::class, 'user_turnamen', 'id_user', 'id_turnamen')
            ->withTimestamps();
    }

    public function syncAssignedTurnamen(array $turnamenIds): void
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $turnamenIds))));

        if ($this->isAdmin()) {
            $ids = [];
        }

        if (Schema::hasTable('user_turnamen')) {
            $this->assignedTurnamen()->sync($ids);
        }

        $primary = $ids[0] ?? null;

        if ((int) $this->id_turnamen !== (int) $primary) {
            $this->forceFill(['id_turnamen' => $primary])->saveQuietly();
        }
    }

    public function assignedTurnamenIds(): array
    {
        if ($this->isAdmin()) {
            return [];
        }

        if ($this->relationLoaded('assignedTurnamen')) {
            $ids = $this->assignedTurnamen->pluck('id')->map(function ($id) {
                return (int) $id;
            })->all();
        } elseif (Schema::hasTable('user_turnamen')) {
            $ids = $this->assignedTurnamen()->pluck('m_turnamen.id')->map(function ($id) {
                return (int) $id;
            })->all();
        } else {
            $ids = [];
        }

        if ($ids === [] && $this->id_turnamen) {
            $ids = [(int) $this->id_turnamen];
        }

        return array_values(array_unique($ids));
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isPanitia(): bool
    {
        return $this->role === 'panitia';
    }
}
