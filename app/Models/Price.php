<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
class Price extends Model
{
    use HasFactory;
    public function Business(): BelongsTo 
    {
        return $this->belongsTo(Business::class);

    }
    public function Sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }
}
