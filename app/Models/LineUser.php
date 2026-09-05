<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LineUser extends Model
{
    protected $fillable = [
        'line_user_id',
        'source_type',
    ];

    public function events(): HasMany
    {
        return $this->hasMany(LineEvent::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }
}
