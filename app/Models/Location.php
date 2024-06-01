<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
class Location extends Model
{
    use HasFactory;
    public function Business(): BelongsTo 
    {
        return $this->belongsTo(Business::class);

    }
    public function Manager(): BelongsTo 
    {
        return $this->belongsTo(User::class);

    }
    public function Supplies(): HasMany
    {
        return $this->hasMany(Supply::class);
    }
    public function Sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }
    public function OperationalCosts(): HasMany
    {
        return $this->hasMany(Operation_Cost::class);
    }

    public function Dispensers(): HasMany
    {
        return $this->hasMany(Dispenser::class);
    }

}
