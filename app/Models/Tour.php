<?php

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Spatie\Translatable\HasTranslations;

/**
 *
 *
 * @property int $id
 * @property array $name
 * @property array $description
 * @property array $location
 * @property int $days
 * @property array $included
 * @property array $not_included
 * @property string $main_image
 * @property string $background_image
 * @property int $order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read mixed $translations
 * @method static Builder<static>|Tour newModelQuery()
 * @method static Builder<static>|Tour newQuery()
 * @method static Builder<static>|Tour query()
 * @method static Builder<static>|Tour whereBackgroundImage($value)
 * @method static Builder<static>|Tour whereCreatedAt($value)
 * @method static Builder<static>|Tour whereDays($value)
 * @method static Builder<static>|Tour whereDescription($value)
 * @method static Builder<static>|Tour whereId($value)
 * @method static Builder<static>|Tour whereIncluded($value)
 * @method static Builder<static>|Tour whereJsonContainsLocale(string $column, string $locale, ?mixed $value, string $operand = '=')
 * @method static Builder<static>|Tour whereJsonContainsLocales(string $column, array $locales, ?mixed $value, string $operand = '=')
 * @method static Builder<static>|Tour whereLocale(string $column, string $locale)
 * @method static Builder<static>|Tour whereLocales(string $column, array $locales)
 * @method static Builder<static>|Tour whereLocation($value)
 * @method static Builder<static>|Tour whereMainImage($value)
 * @method static Builder<static>|Tour whereName($value)
 * @method static Builder<static>|Tour whereNotIncluded($value)
 * @method static Builder<static>|Tour whereOrder($value)
 * @method static Builder<static>|Tour whereUpdatedAt($value)
 * @mixin Eloquent
 */
class Tour extends Model
{
    use HasFactory, HasTranslations;

    protected $fillable = [
        'name',
        'description',
        'location',
        'days',
        'included',
        'not_included',
        'main_image',
        'background_image',
        'order',
    ];

    public $translatable = [
        'name',
        'description',
        'location',
        'included',
        'not_included',
    ];


}
