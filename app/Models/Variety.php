<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Variety extends Model
{
    protected $fillable = ['name', 'maturity_days', 'crop_id'];
}
