<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChatAttachment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'chat_message_id',
        'original_name',
        'stored_name',
        'file_path',
        'mime_type',
        'extension',
        'file_size',
        'type',
        'upload_status',
        'processing_status',
        'processed_report_id',
        'checksum',
    ];

    protected $casts = [
        'file_size' => 'integer',
    ];

    public function message()
    {
        return $this->belongsTo(ChatMessage::class);
    }

    public function medicalReport()
    {
        return $this->belongsTo(MedicalReport::class, 'processed_report_id');
    }
}
