<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
class Business extends Model
{
    use HasFactory;

    public function Owner(): BelongsTo 
    {
        return $this->belongsTo(User::class , 'owner_id');

    }

    public function Locations(): HasMany
    {
        return $this->hasMany(Location::class);
    }

    public function Dispensers(): HasMany
    {
        return $this->hasMany(Dispenser::class);
    }

    public function Suppliers(): HasMany
    {
        return $this->hasMany(Supplier::class);
    }
    public function Supplies(): HasMany
    {
        return $this->hasMany(Supply::class);
    }

    public function Sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }
 
    public function PriceHistory(): HasMany
    {
        return $this->hasMany(Price::class);
    }
    public function Settings(): HasMany
    {
        return $this->hasMany(Business_Setting::class);
    }
    public function OperationCosts(): HasMany
    {
        return $this->hasMany(Operation_Cost::class);
    }
    public function Users(): HasMany
    {
        return $this->hasMany(Business_User::class);
    }
    
}
