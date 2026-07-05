<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChatAttachment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'chat_message_id',
        'original_name',
        'stored_name',
        'file_path',
        'mime_type',
        'file_size',
        'extension',
        'type',
    ];

    /**
     * Get the chat message that owns the attachment.
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(ChatMessage::class, 'chat_message_id');
    }
}
