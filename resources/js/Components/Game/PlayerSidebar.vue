<script setup lang="ts">
import { computed } from 'vue';
import type { EquipmentSlot, Item, Player, PlayerAttributeKey } from '@/types/game';

const props = withDefaults(defineProps<{
    user: Player;
    actionPointFlash?: boolean;
    readOnly?: boolean;
}>(), {
    actionPointFlash: false,
    readOnly: false,
});

const emit = defineEmits<{
    openPaShop: [];
    addAttribute: [attribute: PlayerAttributeKey];
    unequip: [slot: EquipmentSlot];
    useInventoryItem: [index: number];
    sellInventoryItem: [index: number];
    showTooltip: [item: Item | null | undefined, event: MouseEvent];
    hideTooltip: [];
}>();

const equipmentSlots: EquipmentSlot[] = ['weapon', 'armor', 'accessory'];
const expPercent = computed(() => props.user.expMax > 0 ? (props.user.exp / props.user.expMax) * 100 : 0);

function formatNumber(num?: number): string {
    return (num ?? 0).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
}

function getItemImage(itemOrPath?: Item | string | null): string {
    if (!itemOrPath) {
        return '';
    }

    if (typeof itemOrPath === 'string') {
        return itemOrPath.startsWith('/game-assets/') ? itemOrPath : `/game-assets/${itemOrPath}`;
    }

    return itemOrPath.imageUrl ?? getItemImage(itemOrPath.image);
}

function openPaShop(): void {
    if (!props.readOnly) {
        emit('openPaShop');
    }
}

function addAttribute(attribute: PlayerAttributeKey): void {
    if (!props.readOnly) {
        emit('addAttribute', attribute);
    }
}

function unequip(slot: EquipmentSlot): void {
    if (!props.readOnly) {
        emit('unequip', slot);
    }
}

function useInventoryItem(index: number): void {
    if (!props.readOnly) {
        emit('useInventoryItem', index);
    }
}

function sellInventoryItem(index: number): void {
    if (!props.readOnly) {
        emit('sellInventoryItem', index);
    }
}
</script>

<template>
    <aside id="left-panel" :class="{ 'read-only': props.readOnly }">
        <div class="panel-section level-section">
            <div class="section-label">POZIOM: <span class="white-val">{{ props.user.level }}</span></div>
            <div class="exp-bar-container" :title="`EXP: ${props.user.exp} / ${props.user.expMax}`">
                <div class="exp-fill" :style="{ width: expPercent + '%' }"></div>
            </div>
        </div>

        <div class="panel-section resources-section">
            <div class="res-row">
                <span class="label">ZŁOTO:</span>
                <span class="val gold">{{ formatNumber(props.user.gold) }}</span>
            </div>
            <div class="res-row action-points-row" :class="{ 'pa-flash': props.actionPointFlash }">
                <span class="label">PUNKTY AKCJI:</span>
                <span class="val">{{ props.user.pa }}</span>
            </div>
            <button class="btn-more-pa" type="button" :disabled="props.readOnly" @click="openPaShop">WIĘCEJ PA</button>
        </div>

        <div class="panel-section attributes-section">
            <div class="attr-row">
                <span class="label">WITALNOŚĆ:</span>
                <span class="val">{{ props.user.vitality }}</span>
                <button v-if="!props.readOnly && props.user.attributePoints > 0" class="btn-plus-small" type="button" @click="addAttribute('vitality')">+</button>
            </div>
            <div class="attr-row">
                <span class="label">SIŁA:</span>
                <span class="val">{{ props.user.strength }}</span>
                <button v-if="!props.readOnly && props.user.attributePoints > 0" class="btn-plus-small" type="button" @click="addAttribute('strength')">+</button>
            </div>
            <div class="attr-row">
                <span class="label">SZCZĘŚCIE:</span>
                <span class="val">{{ props.user.luck }}</span>
                <button v-if="!props.readOnly && props.user.attributePoints > 0" class="btn-plus-small" type="button" @click="addAttribute('luck')">+</button>
            </div>
        </div>

        <div class="panel-section stats-section">
            <div class="section-header">STATYSTYKI</div>
            <div class="stat-row"><span class="label">Obrażenia:</span><span class="val">{{ props.user.dmgMin }}-{{ props.user.dmgMax }}</span></div>
            <div class="stat-row"><span class="label">Punkty życia:</span><span class="val hp">{{ props.user.hp }}</span></div>
            <div class="stat-row"><span class="label">Pancerz:</span><span class="val">{{ props.user.armor }}</span></div>
            <div class="stat-row"><span class="label">Cios kryt.:</span><span class="val">{{ props.user.critChance }}%</span></div>
            <div class="stat-row"><span class="label">Moc krytyka:</span><span class="val">{{ props.user.critPower }}%</span></div>
            <div class="stat-row"><span class="label">Unik:</span><span class="val">{{ props.user.dodge }}%</span></div>
            <div class="stat-row"><span class="label">Ogłuszenie:</span><span class="val">{{ props.user.stun }}%</span></div>
        </div>

        <div class="panel-section equipment-section">
            <div class="equip-grid">
                <div
                    v-for="slot in equipmentSlots"
                    :key="slot"
                    class="eq-slot"
                    :class="props.user.equipped[slot]?.rarityCss"
                    @click="unequip(slot)"
                    @mouseenter="emit('showTooltip', props.user.equipped[slot], $event)"
                    @mouseleave="emit('hideTooltip')"
                >
                    <img v-if="props.user.equipped[slot]" :src="getItemImage(props.user.equipped[slot])" :alt="props.user.equipped[slot]?.name" class="item-image">
                    <span v-else class="slot-bg"></span>
                </div>
            </div>
        </div>

        <div class="panel-section inventory-section">
            <div class="inv-grid-classic">
                <div
                    v-for="i in 15"
                    :key="i"
                    class="inv-cell"
                    :class="props.user.inventory[i - 1]?.rarityCss"
                    @click="useInventoryItem(i - 1)"
                    @contextmenu.prevent="sellInventoryItem(i - 1)"
                    @mouseenter="emit('showTooltip', props.user.inventory[i - 1], $event)"
                    @mouseleave="emit('hideTooltip')"
                >
                    <img v-if="props.user.inventory[i - 1]" :src="getItemImage(props.user.inventory[i - 1])" :alt="props.user.inventory[i - 1]?.name" class="item-image">
                    <span v-if="(props.user.inventory[i - 1]?.quantity ?? 1) > 1" class="qty">{{ props.user.inventory[i - 1]?.quantity }}</span>
                </div>
            </div>
        </div>

        <button class="btn-chat-classic" type="button" disabled>CHAT</button>
    </aside>
</template>
