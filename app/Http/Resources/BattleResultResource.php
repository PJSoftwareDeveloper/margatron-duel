<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class BattleResultResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'name' => $this->resource['name'],
            'enemy' => $this->resource['enemy'],
            'won' => $this->resource['won'],
            'playerHp' => $this->resource['playerHp'],
            'enemyHp' => $this->resource['enemyHp'],
            'arenaDifficulty' => $this->resource['arenaDifficulty'],
            'log' => $this->resource['log'],
            'rewards' => $this->resource['rewards'],
        ];
    }
}
