<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CropInspection extends Model
{
    use HasFactory;

    protected $fillable = [
        'crop_planting_id',
        'technician_id',
        'inspection_date',
        'remarks',
        'damaged_area',
    ];

    protected $casts = [
        'inspection_date' => 'date',
        'damaged_area' => 'decimal:2'
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