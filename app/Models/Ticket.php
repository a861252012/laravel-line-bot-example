<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Ticket extends Model
{
    public const STATUS_OPEN = 'open';

    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'line_user_id',
        'subject',
        'status',
    ];

    public function lineUser(): BelongsTo
    {
        return $this->belongsTo(LineUser::class);
    }
}
