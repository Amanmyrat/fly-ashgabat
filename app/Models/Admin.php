<?php

namespace App\Models;

use App\Enum\AdminRole;
use Eloquent;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\DatabaseNotificationCollection;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;

/**
 *
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string $password
 * @property AdminRole $role
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read DatabaseNotificationCollection<int, DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @method static Admin newModelQuery()
 * @method static Admin newQuery()
 * @method static Admin query()
 * @method static Admin whereCreatedAt($value)
 * @method static Admin whereEmail($value)
 * @method static Admin whereId($value)
 * @method static Admin whereName($value)
 * @method static Admin wherePassword($value)
 * @method static Admin whereRole($value)
 * @method static Admin whereUpdatedAt($value)
 * @mixin Eloquent
 */
class Admin extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'password', 'role'];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'role' => AdminRole::class,
    ];
}
