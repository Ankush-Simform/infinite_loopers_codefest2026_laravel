<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\MedicalReport;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReportUploaded implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public ?MedicalReport $report = null;

    public function __construct(
        public readonly int|string $userId,
        public readonly string $reportId,
        public readonly string $status,
        public readonly string $processingStage,
        public readonly ?string $message = null
    ) {
        $this->report = MedicalReport::find($reportId);
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('reports.' . $this->userId),
        ];
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'report_id' => $this->reportId,
            'status' => $this->status,
            'processing_stage' => $this->processingStage,
            'message' => $this->message,
        ];
    }
}
