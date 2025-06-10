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
 * @property array $location
 * @property array $description
 * @property int $days
 * @property int $price
 * @property array $necessary_documents
 * @property string $main_image
 * @property int $order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read mixed $translations
 * @method static Builder<static>|Visa newModelQuery()
 * @method static Builder<static>|Visa newQuery()
 * @method static Builder<static>|Visa query()
 * @method static Builder<static>|Visa whereCreatedAt($value)
 * @method static Builder<static>|Visa whereDays($value)
 * @method static Builder<static>|Visa whereDescription($value)
 * @method static Builder<static>|Visa whereId($value)
 * @method static Builder<static>|Visa whereJsonContainsLocale(string $column, string $locale, ?mixed $value, string $operand = '=')
 * @method static Builder<static>|Visa whereJsonContainsLocales(string $column, array $locales, ?mixed $value, string $operand = '=')
 * @method static Builder<static>|Visa whereLocale(string $column, string $locale)
 * @method static Builder<static>|Visa whereLocales(string $column, array $locales)
 * @method static Builder<static>|Visa whereLocation($value)
 * @method static Builder<static>|Visa whereMainImage($value)
 * @method static Builder<static>|Visa whereNecessaryDocuments($value)
 * @method static Builder<static>|Visa whereOrder($value)
 * @method static Builder<static>|Visa wherePrice($value)
 * @method static Builder<static>|Visa whereUpdatedAt($value)
 * @mixin Eloquent
 */
class Visa extends Model
{
    use HasFactory, HasTranslations;

    protected $fillable = [
        'location',
        'description',
        'days',
        'price',
        'included',
        'necessary_documents',
        'main_image',
        'order',
    ];

    public $translatable = [
        'location',
        'description',
        'necessary_documents',
    ];
}
