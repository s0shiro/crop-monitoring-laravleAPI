<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CropPlanting extends Model
{
    use HasFactory;

    protected $fillable = [
        'farmer_id',
        'category_id',
        'crop_id',
        'variety_id',
        'planting_date',
        'expected_harvest_date',
        'area_planted',
        'quantity',
        'expenses',
        'technician_id',
        'remarks',
        'status',
        'latitude',
        'longitude',
        'municipality',
        'barangay',
        'harvested_area',
        'remaining_area',
        'damaged_area'
    ];

    protected $casts = [
        'planting_date' => 'date',
        'expected_harvest_date' => 'date',
        'area_planted' => 'decimal:2',
        'harvested_area' => 'decimal:2',
        'remaining_area' => 'decimal:2',
        'quantity' => 'decimal:2',
        'expenses' => 'decimal:2',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'damaged_area' => 'decimal:2'
    ];

    public function farmer()
    {
        return $this->belongsTo(Farmer::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function crop()
    {
        return $this->belongsTo(Crop::class);
    }

    public function variety()
    {
        return $this->belongsTo(Variety::class);
    }

    public function technician()
    {
        return $this->belongsTo(User::class, 'technician_id');
    }

    public function hvcDetail()
    {
        return $this->hasOne(HvcDetail::class);
    }

    public function riceDetail()
    {
        return $this->hasOne(RiceDetail::class);
    }

    public function inspections()
    {
        return $this->hasMany(CropInspection::class);
    }

    public function harvestReports()
    {
        return $this->hasMany(HarvestReport::class);
    }

    // Harvest tracking methods
    public function canBeHarvested(): bool
    {
        return ($this->status === 'harvest' || $this->status === 'partially harvested') && $this->remaining_area > 0;
    }

    public function getHarvestProgressAttribute()
    {
        if ($this->area_planted == 0) return 0;
        return ($this->harvested_area / $this->area_planted) * 100;
    }

    public function updateHarvestAreas($harvestedArea)
    {
        $this->harvested_area += $harvestedArea;
        $this->remaining_area = max(0, $this->area_planted - $this->harvested_area);

        // Update status based on harvest progress
        if ($this->remaining_area <= 0) {
            $this->status = 'harvested';
        } elseif ($this->harvested_area > 0) {
            $this->status = 'partially harvested';
        }

        return $this->save();
    }
}