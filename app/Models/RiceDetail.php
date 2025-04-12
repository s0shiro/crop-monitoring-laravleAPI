<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class RiceDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'crop_planting_id',
        'classification',
        'water_supply',
        'land_type'
    ];

    public function cropPlanting()
    {
        return $this->belongsTo(CropPlanting::class);
    }
}