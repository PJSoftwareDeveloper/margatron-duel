<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class ActionPointsChanged implements ShouldBroadcast
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  array<string, mixed>  $actionPoints
     */
    public function __construct(
        public readonly int $userId,
        public readonly array $actionPoints,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("users.{$this->userId}");
    }

    public function broadcastAs(): string
    {
        return 'action-points.changed';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'actionPoints' => $this->actionPoints,
        ];
    }

    public function broadcastQueue(): string
    {
        return 'broadcasts';
    }
}
