<?php

namespace App\Game\Services;

use App\Game\Enums\ItemType;
use App\Game\Repositories\StaticGameCatalogRepository;
use App\Models\GameProfile;
use DomainException;

final readonly class InventoryService
{
    public function __construct(
        private StaticGameCatalogRepository $catalog,
        private GameProfileService $profiles,
    ) {}

    /**
     * @param  array<string, mixed>  $item
     */
    public function addItem(GameProfile $profile, array $item): bool
    {
        $inventory = $this->normalizedInventory($profile);

        if (($item['stackable'] ?? false) === true) {
            foreach ($inventory as $index => $existing) {
                if (($existing['id'] ?? null) === ($item['id'] ?? null)) {
                    $inventory[$index]['quantity'] = ($existing['quantity'] ?? 1) + ($item['quantity'] ?? 1);
                    $profile->inventory = $inventory;
                    $profile->save();

                    return true;
                }
            }
        }

        foreach ($inventory as $index => $slot) {
            if ($slot === null) {
                $inventory[$index] = [...$item, 'quantity' => $item['quantity'] ?? 1];
                $profile->inventory = $inventory;
                $profile->save();

                return true;
            }
        }

        return false;
    }

    public function buyItem(GameProfile $profile, string $shopId, int|string $itemId): GameProfile
    {
        $shop = $this->catalog->shopById($shopId) ?? throw new DomainException('Nie znaleziono sklepu.');
        $item = collect($shop['items'])->first(fn (array $item): bool => (string) $item['id'] === (string) $itemId);

        if (! $item) {
            throw new DomainException('Ten przedmiot nie istnieje w sklepie.');
        }

        if ($profile->gold < $item['price']) {
            throw new DomainException('Masz za mało złota.');
        }

        $copy = [
            ...$item,
            'id' => "{$item['id']}_".bin2hex(random_bytes(6)),
            'shopItemId' => $item['id'],
        ];

        if (! $this->addItem($profile, $copy)) {
            throw new DomainException('Ekwipunek jest pełny.');
        }

        $profile->decrement('gold', $item['price']);

        return $profile->refresh();
    }

    public function equip(GameProfile $profile, int $index): GameProfile
    {
        $inventory = $this->normalizedInventory($profile);
        $item = $inventory[$index] ?? null;

        if (! $item) {
            throw new DomainException('Ten slot jest pusty.');
        }

        $slot = $this->slotForItem($item);
        $equipped = $profile->equipped ?? [];
        $inventory[$index] = $equipped[$slot] ?? null;
        $equipped[$slot] = $item;

        $profile->forceFill([
            'inventory' => $inventory,
            'equipped' => $equipped,
        ])->save();

        return $this->profiles->recalculate($profile);
    }

    public function unequip(GameProfile $profile, string $slot): GameProfile
    {
        $equipped = $profile->equipped ?? [];
        $item = $equipped[$slot] ?? null;

        if (! $item) {
            throw new DomainException('Ten slot ekwipunku jest pusty.');
        }

        $equipped[$slot] = null;
        $profile->equipped = $equipped;

        if (! $this->addItem($profile, $item)) {
            $equipped[$slot] = $item;
            $profile->equipped = $equipped;
            $profile->save();

            throw new DomainException('Ekwipunek jest pełny.');
        }

        return $this->profiles->recalculate($profile);
    }

    /**
     * @return array{profile: GameProfile, gold: int}
     */
    public function sell(GameProfile $profile, int $index): array
    {
        $inventory = $this->normalizedInventory($profile);
        $item = $inventory[$index] ?? null;

        if (! $item) {
            throw new DomainException('Ten slot jest pusty.');
        }

        $gold = (int) floor(($item['price'] ?? 0) * 0.5);
        $inventory[$index] = null;
        $profile->forceFill([
            'inventory' => $inventory,
            'gold' => $profile->gold + $gold,
        ])->save();

        return [
            'profile' => $profile->refresh(),
            'gold' => $gold,
        ];
    }

    public function useItem(GameProfile $profile, int $index): GameProfile
    {
        $inventory = $this->normalizedInventory($profile);
        $item = $inventory[$index] ?? null;

        if (! $item || ($item['type'] ?? $item['itemType'] ?? null) !== ItemType::Potion->value) {
            throw new DomainException('Tego przedmiotu nie da się użyć.');
        }

        $effect = $item['effectData'] ?? [
            'type' => $item['effect'] ?? null,
            'value' => $item['effectValue'] ?? null,
        ];
        $effectType = $effect['type'] ?? null;

        $this->applyEffect($profile, $effect);

        if (($item['quantity'] ?? 1) > 1) {
            $inventory[$index]['quantity']--;
        } else {
            $inventory[$index] = null;
        }

        $profile->inventory = $inventory;
        $profile->save();

        if (in_array($effectType, ['buff_strength', 'buff_all'], true)) {
            return $this->profiles->recalculate($profile);
        }

        return $profile->refresh();
    }

    /**
     * @return array<int, array<string, mixed>|null>
     */
    public function normalizedInventory(GameProfile $profile): array
    {
        $inventory = $profile->inventory ?? [];
        $inventory = array_values(array_pad(array_slice($inventory, 0, GameProfileService::INVENTORY_SIZE), GameProfileService::INVENTORY_SIZE, null));

        return $inventory;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function slotForItem(array $item): string
    {
        return match ($item['type'] ?? $item['itemType'] ?? null) {
            ItemType::Weapon->value => 'weapon',
            ItemType::Armor->value => 'armor',
            ItemType::Talisman->value, 'accessory' => 'accessory',
            default => throw new DomainException('Tego przedmiotu nie da się założyć.'),
        };
    }

    /**
     * @param  array<string, mixed>  $effect
     */
    private function applyEffect(GameProfile $profile, array $effect): void
    {
        $value = (int) ($effect['value'] ?? 0);

        match ($effect['type'] ?? null) {
            'heal' => $profile->hp = min($profile->hp_max, $profile->hp + $value),
            'pa' => $profile->pa = min($profile->pa_max, $profile->pa + $value),
            'buff_strength' => $profile->strength += $value,
            'buff_crit' => $profile->crit_chance += $value,
            'buff_armor' => $profile->armor += $value,
            'buff_all' => $this->applyAllBuff($profile, $value),
            default => null,
        };
    }

    private function applyAllBuff(GameProfile $profile, int $value): void
    {
        $profile->strength += (int) floor($value / 2);
        $profile->vitality += (int) floor($value / 2);
        $profile->luck += (int) floor($value / 3);
    }
}
