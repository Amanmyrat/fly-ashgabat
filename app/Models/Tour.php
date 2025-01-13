<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

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
