<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class HvcDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'crop_planting_id',
        'classification'
    ];

    public function cropPlanting()
    {
        return $this->belongsTo(CropPlanting::class);
    }
}