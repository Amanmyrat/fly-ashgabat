<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

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
