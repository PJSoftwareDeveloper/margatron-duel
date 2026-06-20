<?php

namespace App\Http\Resources;

use App\Game\Data\AchievementProgress;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AchievementProgress
 */
final class AchievementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'icon' => $this->icon,
            'value' => $this->value,
            'target' => $this->target,
            'percent' => $this->percent,
            'progressLabel' => $this->progressLabel,
            'completed' => $this->completed,
        ];
    }
}
