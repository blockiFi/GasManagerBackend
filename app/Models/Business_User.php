<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Business_User extends Model
{
    use HasFactory;


    public function User(): BelongsTo 
    {
        return $this->belongsTo(User::class);

    }
    public function Business(): BelongsTo 
    {
        return $this->belongsTo(Business::class);

    }
}
