<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
class Supplier extends Model
{
    use HasFactory;
    public function Business(): BelongsTo 
    {
        return $this->belongsTo(Business::class);

    }
    public function Supplies(): HasMany
    {
        return $this->hasMany(Supply::class);
    }
}
