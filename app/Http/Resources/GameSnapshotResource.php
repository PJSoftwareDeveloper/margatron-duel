<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class GameSnapshotResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $profile = $this->resource['profile'];

        return [
            'user' => new GameProfileResource($profile),
            'currentMap' => $this->resource['map'],
            'worldMaps' => $this->resource['worldMaps'],
            'shops' => $this->resource['shops'],
            'rest' => $this->resource['rest'],
        ];
    }
}
