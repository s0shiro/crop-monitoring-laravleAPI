<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class HarvestReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'crop_planting_id',
        'technician_id',
        'harvest_date',
        'area_harvested',
        'total_yield',
        'profit',
        'damage_quantity'
    ];

    protected $casts = [
        'harvest_date' => 'date',
        'area_harvested' => 'decimal:2',
        'total_yield' => 'decimal:2',
        'profit' => 'decimal:2',
        'damage_quantity' => 'decimal:2'
    ];

    public function cropPlanting()
    {
        return $this->belongsTo(CropPlanting::class);
    }

    public function technician()
    {
        return $this->belongsTo(User::class, 'technician_id');
    }
}