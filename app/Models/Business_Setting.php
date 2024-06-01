<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Business_Setting extends Model
{
    use HasFactory;

    public function Setting(): BelongsTo 
    {
        return $this->belongsTo(Setting::class);

    }
    public function Business(): BelongsTo 
    {
        return $this->belongsTo(Business::class);

    }
}
