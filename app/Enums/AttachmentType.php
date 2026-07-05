<?php

declare(strict_types=1);

namespace App\Enums;

enum AttachmentType: string
{
    case IMAGE = 'image';
    case PDF = 'pdf';
    case DOCUMENT = 'document';
    case AUDIO = 'audio';
    case VIDEO = 'video';
    case OTHER = 'other';

    /**
     * Map MIME type and file extension to AttachmentType enum.
     */
    public static function fromMime(string $mime, string $extension): self
    {
        $mime = strtolower($mime);
        $extension = strtolower($extension);

        if (str_starts_with($mime, 'image/')) {
            return self::IMAGE;
        }

        if ($mime === 'application/pdf' || $extension === 'pdf') {
            return self::PDF;
        }

        if (str_starts_with($mime, 'audio/')) {
            return self::AUDIO;
        }

        if (str_starts_with($mime, 'video/')) {
            return self::VIDEO;
        }

        $docs = ['doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'rtf', 'csv', 'odt', 'ods'];
        if (in_array($extension, $docs, true) || str_contains($mime, 'document') || str_contains($mime, 'excel') || str_contains($mime, 'spreadsheet') || str_contains($mime, 'powerpoint')) {
            return self::DOCUMENT;
        }

        return self::OTHER;
    }
}
