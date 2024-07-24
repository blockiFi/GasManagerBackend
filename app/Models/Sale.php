<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Sale extends Model
{
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sales_date' => 'date:Y/m/d'
        ];
    }

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
    public function Price(): BelongsTo 
    {
        return $this->belongsTo(Price::class);

    }

}
