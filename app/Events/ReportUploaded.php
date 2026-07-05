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

    public function __construct(
        public readonly MedicalReport $report
    ) {}

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        $userId = $this->report->profile->user_id;

        return [
            new PrivateChannel('reports.'.$userId),
            new PrivateChannel('user.'.$userId),
        ];
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'report_id' => $this->report->id,
            'status' => $this->report->status,
            'file_url' => $this->report->file_url,
            'stage' => 'upload_complete',
        ];
    }
}
