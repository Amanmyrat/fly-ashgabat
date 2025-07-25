<?php

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 *
 *
 * @property string $email
 * @property string $token
 * @property Carbon|null $created_at
 * @method static Builder<static>|PasswordResetToken newModelQuery()
 * @method static Builder<static>|PasswordResetToken newQuery()
 * @method static Builder<static>|PasswordResetToken query()
 * @method static Builder<static>|PasswordResetToken whereCreatedAt($value)
 * @method static Builder<static>|PasswordResetToken whereEmail($value)
 * @method static Builder<static>|PasswordResetToken whereToken($value)
 * @mixin Eloquent
 */
class PasswordResetToken extends Model
{
    use HasFactory;

    const UPDATED_AT = null;

    protected $primaryKey = 'email';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = ['email', 'token'];

}
