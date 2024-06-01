<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Operation_Cost extends Model
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
}
