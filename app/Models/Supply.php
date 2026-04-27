<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Supply extends Model
{
    use HasFactory;

    public function Business(): BelongsTo 
    {
        return $this->belongsTo(Business::class);

    }
    public function Location(): BelongsTo 
    {
        return $this->belongsTo(Location::class);

    }
    public function Dispenser(): BelongsTo 
    {
        return $this->belongsTo(Dispenser::class);

    }
    public function Supplier(): BelongsTo 
    {
        return $this->belongsTo(Supplier::class);

    }
    public function Reciever(): BelongsTo 
    {
        return $this->belongsTo(User::class  , 'recieved_by');

    }
    public function Sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }
    public function LastSale(): HasOne
    {
        return $this->hasOne(Sale::class)->orderBy('sales_date', 'desc');
    }


}
