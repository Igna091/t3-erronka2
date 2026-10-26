<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A medicine the shop sells. Only medicines that don't need a prescription in Spain may be
 * sold online, so check every new one on CIMA before adding it.
 */
class Medicine extends Model
{
    /**
     * Shop categories: their label, and the colour of their dot and pack picture in the design.
     */
    public const CATEGORIES = [
        'pain' => ['label' => 'Pain and fever', 'colour' => '#2A4BD7'],
        'stomach' => ['label' => 'Stomach', 'colour' => '#F2C94C'],
        'cough' => ['label' => 'Cough and cold', 'colour' => '#FFFFFF'],
        'skin' => ['label' => 'Skin and wounds', 'colour' => '#1DB45A'],
    ];

    protected $fillable = [
        'slug', 'name', 'pack', 'summary', 'description', 'dosage', 'warnings',
        'category', 'form', 'price_cents', 'active',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'warnings' => 'array',
            'price_cents' => 'integer',
            'active' => 'boolean',
        ];
    }

    /**
     * @param  Builder<Medicine>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('active', true);
    }

    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->category]['label'];
    }

    public function colour(): string
    {
        return self::CATEGORIES[$this->category]['colour'];
    }
}
