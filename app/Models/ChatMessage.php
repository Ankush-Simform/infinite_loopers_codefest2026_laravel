<?php

namespace App\Models;

use App\Enums\ChatMessageRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChatMessage extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'chat_session_id',
        'report_id',
        'role',
        'content',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'role' => ChatMessageRole::class,
            'metadata' => 'array',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function session(): BelongsTo
    {
        return $this->belongsTo(ChatSession::class, 'chat_session_id');
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(MedicalReport::class, 'report_id');
    }
}
