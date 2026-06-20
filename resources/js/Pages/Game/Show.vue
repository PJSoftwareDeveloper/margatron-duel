<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, nextTick, onUnmounted, ref, watch } from 'vue';
import type { AxiosError } from 'axios';
import type { BattleResult, GameSnapshot, Item, Location, Stage } from '@/types/game';

type Resource<T> = T | { data: T };
type GameView = 'map' | 'battleSelection' | 'arena' | 'battle' | 'shop' | 'rest' | 'worldMap';

const props = defineProps<{
    game: Resource<GameSnapshot>;
}>();

const game = ref(unwrap(props.game));
const currentView = ref<GameView>('map');
const selectedLocation = ref<Location | null>(null);
const selectedBattleLocation = ref<Location | null>(null);
const lastBattleStage = ref<number | null>(null);
const battleResult = ref<BattleResult | null>(null);
const currentShopId = ref<string | null>(null);
const shopTab = ref<'buy' | 'sell'>('buy');
const showSettings = ref(false);
const showPAShop = ref(false);
const alertMessage = ref('');
const settings = ref({ sound: true, music: false, notifications: true });
const tooltipItem = ref<Item | null>(null);
const tooltipX = ref(0);
const tooltipY = ref(0);
const logScroll = ref<HTMLElement | null>(null);
const equipmentSlots = ['weapon', 'armor', 'accessory'] as const;
let actionPointRefreshTimer: number | undefined;
let isGameViewDisposed = false;

const user = computed(() => game.value.user);
const currentMap = computed(() => game.value.currentMap);
const equipped = computed(() => user.value.equipped);
const inventory = computed(() => user.value.inventory);
const inventoryFiltered = computed(() => inventory.value.filter(Boolean) as Item[]);
const currentShop = computed(() => currentShopId.value ? game.value.shops[currentShopId.value] : null);
const shopName = computed(() => currentShop.value?.name ?? '');
const shopItems = computed(() => currentShop.value?.items ?? []);
const expPercent = computed(() => user.value.expMax > 0 ? (user.value.exp / user.value.expMax) * 100 : 0);
const battleStages = computed(() => selectedBattleLocation.value?.stages?.map((stage) => ({
    ...stage,
    id: stage.stage,
    locked: !stage.unlocked,
})) ?? []);

const mapLocations = computed(() => currentMap.value.locations.map((location) => decorateLocation(location)));

function unwrap<T extends object>(resource: Resource<T>): T {
    return 'data' in resource ? resource.data : resource;
}

function syncGame(payload: Resource<GameSnapshot>): void {
    game.value = unwrap(payload);
}

function clearActionPointRefresh(): void {
    if (actionPointRefreshTimer !== undefined) {
        window.clearTimeout(actionPointRefreshTimer);
        actionPointRefreshTimer = undefined;
    }
}

function actionPointRefreshDelay(): number {
    if (user.value.paRegeneratesAt) {
        return Math.max(250, Date.parse(user.value.paRegeneratesAt) - Date.now());
    }

    return Math.max(1, user.value.paRegenerationSeconds) * 1000;
}

async function refreshGameState(): Promise<void> {
    const response = await axios.get('/game/state');
    syncGame(response.data);
}

function scheduleActionPointRefresh(): void {
    clearActionPointRefresh();

    if (isGameViewDisposed || user.value.pa >= user.value.paRegenerationLimit) {
        return;
    }

    actionPointRefreshTimer = window.setTimeout(async () => {
        try {
            await refreshGameState();
        } finally {
            if (! isGameViewDisposed) {
                scheduleActionPointRefresh();
            }
        }
    }, actionPointRefreshDelay());
}

function decorateLocation(location: Location): Location {
    const meta = {
        battle: { icon: '⚔', description: 'Expowisko z pięcioma etapami walki.' },
        arena: { icon: '♜', description: 'Arena z losowymi przeciwnikami.' },
        shop: { icon: '¤', description: 'Sklep z przedmiotami.' },
        rest: { icon: '⌛', description: 'Odpoczynek i regeneracja PA.' },
        worldmap: { icon: '◆', description: 'Przejście do mapy świata.' },
    }[location.type];
    const paCost = location.type === 'battle' ? (location.pa ?? 1) : 0;

    return {
        ...location,
        icon: location.icon ?? meta.icon,
        description: location.description ?? meta.description,
        paCost,
        locked: Boolean(location.levelReq && user.value.level < location.levelReq),
    };
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

function formatNumber(num?: number): string {
    return (num ?? 0).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
}

function showTooltip(item: Item | null | undefined, event: MouseEvent): void {
    if (!item) {
        return;
    }

    tooltipItem.value = item;
    const offset = 15;
    tooltipX.value = event.clientX + offset;
    tooltipY.value = event.clientY + offset;

    if (tooltipX.value + 280 > window.innerWidth) {
        tooltipX.value = event.clientX - 295;
    }

    if (tooltipY.value + 250 > window.innerHeight) {
        tooltipY.value = event.clientY - 265;
    }
}

function hideTooltip(): void {
    tooltipItem.value = null;
}

function bonusRows(item: Item): Array<{ key: string; value: number; name: string; suffix: string }> {
    return Object.entries(item.bonusStats ?? {})
        .filter(([key]) => !['dmgMin', 'dmgMax', 'armor'].includes(key))
        .map(([key, stat]) => {
            if (typeof stat === 'number') {
                return { key, value: stat, name: statName(key), suffix: statSuffix(key) };
            }

            return { key, value: stat.value, name: stat.name, suffix: stat.suffix };
        });
}

function statName(key: string): string {
    return {
        hp: 'Punkty życia',
        critChance: 'Szansa krytyka',
        critPower: 'Moc krytyka',
        dodge: 'Unik',
        stun: 'Ogłuszenie',
        strength: 'Siła',
        armor: 'Pancerz',
    }[key] ?? key;
}

function statSuffix(key: string): string {
    return ['critChance', 'critPower', 'dodge', 'stun'].includes(key) ? '%' : '';
}

function enterLocation(location: Location): void {
    if (location.locked) {
        return;
    }

    selectedLocation.value = location;
}

function canEnterLocation(location: Location | null): boolean {
    if (!location || location.locked) {
        return false;
    }

    if ((location.paCost ?? 0) > 0 && user.value.pa < (location.paCost ?? 0)) {
        return false;
    }

    if (location.levelReq && user.value.level < location.levelReq) {
        return false;
    }

    return true;
}

function getEnterButtonText(location: Location): string {
    if (location.locked) return 'Zablokowane';
    if ((location.paCost ?? 0) > 0 && user.value.pa < (location.paCost ?? 0)) return 'Brak PA';
    if (location.levelReq && user.value.level < location.levelReq) return `Wymagany poziom ${location.levelReq}`;

    return {
        battle: 'Walcz!',
        shop: 'Wejdź',
        rest: 'Odpocznij',
        worldmap: 'Otwórz mapę',
        arena: 'Wejdź na arenę',
    }[location.type] ?? 'Wejdź';
}

function confirmEnterLocation(): void {
    const location = selectedLocation.value;

    if (!location || !canEnterLocation(location)) {
        return;
    }

    if (location.type === 'battle') {
        selectedBattleLocation.value = location;
        currentView.value = 'battleSelection';
    } else if (location.type === 'shop') {
        currentShopId.value = location.shopId ?? null;
        shopTab.value = 'buy';
        currentView.value = 'shop';
    } else if (location.type === 'rest') {
        currentView.value = 'rest';
    } else if (location.type === 'worldmap') {
        currentView.value = 'worldMap';
    } else if (location.type === 'arena') {
        currentView.value = 'arena';
    }

    selectedLocation.value = null;
}

function goBackToMap(): void {
    currentView.value = 'map';
    selectedLocation.value = null;
}

async function selectWorldMap(worldMap: { id: number; locked: boolean }): Promise<void> {
    if (worldMap.locked) {
        return;
    }

    await action('/game/actions/map', { mapId: worldMap.id });
    currentView.value = 'map';
}

async function selectBattleStage(stage: Stage & { id?: number; locked?: boolean }): Promise<void> {
    if (stage.locked || !selectedBattleLocation.value) {
        return;
    }

    lastBattleStage.value = stage.stage;
    const response = await axios.post('/game/actions/battle/stage', {
        locationId: selectedBattleLocation.value.id,
        stage: stage.stage,
    });
    battleResult.value = response.data.battle;
    syncGame(response.data.game);
    currentView.value = 'battle';
    await scrollLog();
}

async function startArenaFight(difficulty: 'easy' | 'medium' | 'hard'): Promise<void> {
    const response = await axios.post('/game/actions/battle/arena', { difficulty });
    battleResult.value = response.data.battle;
    syncGame(response.data.game);
    currentView.value = 'battle';
    await scrollLog();
}

async function startNextBattle(): Promise<void> {
    if (!selectedBattleLocation.value || !lastBattleStage.value) {
        closeBattle();
        return;
    }

    const nextStage = Math.min(5, lastBattleStage.value + (battleResult.value?.won ? 1 : 0));
    const target = battleStages.value.find((stage) => stage.stage === nextStage && !stage.locked) ?? battleStages.value.find((stage) => stage.stage === lastBattleStage.value);

    if (!target) {
        closeBattle();
        return;
    }

    await selectBattleStage(target);
}

function closeBattle(): void {
    battleResult.value = null;
    currentView.value = 'map';
}

async function action(url: string, data: Record<string, unknown> = {}): Promise<void> {
    try {
        const response = await axios.post(url, data);
        syncGame(response.data);
    } catch (error) {
        showActionError(error);
    }
}

async function buyItem(item: Item): Promise<void> {
    if (!currentShopId.value || user.value.gold < item.price || (item.level ?? 1) > user.value.level) {
        return;
    }

    await action('/game/actions/shop/buy', {
        shopId: currentShopId.value,
        itemId: item.shopItemId ?? item.id,
    });
}

async function sellItem(index: number): Promise<void> {
    await action('/game/actions/inventory/sell', { index });
}

async function useItem(index: number): Promise<void> {
    const item = inventory.value[index];

    if (!item) {
        return;
    }

    if (item.type === 'potion' || item.itemType === 'potion') {
        await action('/game/actions/inventory/use', { index });
        return;
    }

    await action('/game/actions/inventory/equip', { index });
}

async function unequip(slot: 'weapon' | 'armor' | 'accessory'): Promise<void> {
    if (!equipped.value[slot]) {
        return;
    }

    await action('/game/actions/inventory/unequip', { slot });
}

async function rest(minutes: 1 | 5): Promise<void> {
    await action('/game/actions/rest', { minutes });
    currentView.value = 'map';
}

async function instantRest(): Promise<void> {
    await action('/game/actions/rest/instant');
    currentView.value = 'map';
}

async function addAttribute(attribute: 'vitality' | 'strength' | 'luck'): Promise<void> {
    if (user.value.attributePoints <= 0) {
        return;
    }

    await action('/game/actions/attribute', { attribute });
}

async function buyPA(amount: 5 | 10 | 15, price: 100 | 180 | 250): Promise<void> {
    await action('/game/actions/pa', { amount, price });
    showPAShop.value = false;
}

function showItemInfo(item: Item | null | undefined): void {
    if (item) {
        tooltipItem.value = item;
    }
}

function showItemMenu(index: number): void {
    void sellItem(index);
}

function logout(): void {
    clearActionPointRefresh();
    router.post('/logout');
}

function disposeGameView(): void {
    isGameViewDisposed = true;
    clearActionPointRefresh();
}

watch(
    () => [user.value.pa, user.value.paRegenerationLimit, user.value.paRegenerationSeconds, user.value.paRegeneratesAt],
    scheduleActionPointRefresh,
    { immediate: true },
);

onUnmounted(disposeGameView);

function showActionError(error: unknown): void {
    const axiosError = error as AxiosError<{ message?: string }>;
    alertMessage.value = axiosError.response?.data?.message ?? 'Akcja nie powiodła się.';
}

async function scrollLog(): Promise<void> {
    await nextTick();
    if (logScroll.value) {
        logScroll.value.scrollTop = logScroll.value.scrollHeight;
    }
}
</script>

<template>
    <Head title="Gra" />

    <div id="game-container">
        <header id="top-bar">
            <div id="logo-container">
                <span class="version">v.0.1</span>
            </div>

            <nav id="top-nav">
                <button class="nav-btn">FORUM</button>
                <button class="nav-btn">RANKINGI</button>
                <button class="nav-btn">OSIĄGNIĘCIA</button>
                <button class="nav-btn" @click="showSettings = true">KONFIGURACJA</button>
                <button class="nav-btn logout" @click="logout">WYLOGUJ</button>
            </nav>
        </header>

        <div id="main-content">
            <aside id="left-panel">
                <div class="panel-section level-section">
                    <div class="section-label">POZIOM: <span class="white-val">{{ user.level }}</span></div>
                    <div class="exp-bar-container" :title="`EXP: ${user.exp} / ${user.expMax}`">
                        <div class="exp-fill" :style="{ width: expPercent + '%' }"></div>
                    </div>
                </div>

                <div class="panel-section resources-section">
                    <div class="res-row">
                        <span class="label">ZŁOTO:</span>
                        <span class="val gold">{{ formatNumber(user.gold) }}</span>
                    </div>
                    <div class="res-row">
                        <span class="label">PUNKTY AKCJI:</span>
                        <span class="val">{{ user.pa }}</span>
                    </div>
                    <button class="btn-more-pa" @click="showPAShop = true">WIĘCEJ PA</button>
                </div>

                <div class="panel-section attributes-section">
                    <div class="attr-row">
                        <span class="label">WITALNOŚĆ:</span>
                        <span class="val">{{ user.vitality }}</span>
                        <button v-if="user.attributePoints > 0" class="btn-plus-small" @click="addAttribute('vitality')">+</button>
                    </div>
                    <div class="attr-row">
                        <span class="label">SIŁA:</span>
                        <span class="val">{{ user.strength }}</span>
                        <button v-if="user.attributePoints > 0" class="btn-plus-small" @click="addAttribute('strength')">+</button>
                    </div>
                    <div class="attr-row">
                        <span class="label">SZCZĘŚCIE:</span>
                        <span class="val">{{ user.luck }}</span>
                        <button v-if="user.attributePoints > 0" class="btn-plus-small" @click="addAttribute('luck')">+</button>
                    </div>
                </div>

                <div class="panel-section stats-section">
                    <div class="section-header">STATYSTYKI</div>
                    <div class="stat-row"><span class="label">Obrażenia:</span><span class="val">{{ user.dmgMin }}-{{ user.dmgMax }}</span></div>
                    <div class="stat-row"><span class="label">Punkty życia:</span><span class="val hp">{{ user.hp }}</span></div>
                    <div class="stat-row"><span class="label">Pancerz:</span><span class="val">{{ user.armor }}</span></div>
                    <div class="stat-row"><span class="label">Cios kryt.:</span><span class="val">{{ user.critChance }}%</span></div>
                    <div class="stat-row"><span class="label">Moc krytyka:</span><span class="val">{{ user.critPower }}%</span></div>
                    <div class="stat-row"><span class="label">Unik:</span><span class="val">{{ user.dodge }}%</span></div>
                    <div class="stat-row"><span class="label">Ogłuszenie:</span><span class="val">{{ user.stun }}%</span></div>
                </div>

                <div class="panel-section equipment-section">
                    <div class="equip-grid">
                        <div
                            v-for="slot in equipmentSlots"
                            :key="slot"
                            class="eq-slot"
                            :class="equipped[slot]?.rarityCss"
                            @click="unequip(slot)"
                            @mouseenter="showTooltip(equipped[slot], $event)"
                            @mouseleave="hideTooltip"
                        >
                            <img v-if="equipped[slot]" :src="getItemImage(equipped[slot])" :alt="equipped[slot]?.name" class="item-image">
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
                            :class="inventory[i - 1]?.rarityCss"
                            @click="useItem(i - 1)"
                            @contextmenu.prevent="showItemMenu(i - 1)"
                            @mouseenter="showTooltip(inventory[i - 1], $event)"
                            @mouseleave="hideTooltip"
                        >
                            <img v-if="inventory[i - 1]" :src="getItemImage(inventory[i - 1])" :alt="inventory[i - 1]?.name" class="item-image">
                            <span v-if="(inventory[i - 1]?.quantity ?? 1) > 1" class="qty">{{ inventory[i - 1]?.quantity }}</span>
                        </div>
                    </div>
                </div>

                <button class="btn-chat-classic">CHAT</button>
            </aside>

            <main id="map-area">
                <div v-if="currentView === 'map'" class="view-map">
                    <div class="map-container">
                        <div class="map-image" :style="{ backgroundImage: `url(${currentMap.imageUrl})` }">
                            <div class="map-name-label">{{ currentMap.name }}</div>

                            <div
                                v-for="location in mapLocations"
                                :key="location.id"
                                class="map-location"
                                :class="[location.type, { locked: location.locked }]"
                                :style="{ left: location.x + '%', top: location.y + '%' }"
                                :title="location.name"
                                @click="enterLocation(location)"
                            >
                                <span class="location-icon">{{ location.icon }}</span>
                            </div>
                        </div>
                    </div>

                    <div v-if="selectedLocation" class="location-info">
                        <h3>{{ selectedLocation.icon }} {{ selectedLocation.name }}</h3>
                        <p>{{ selectedLocation.description }}</p>
                        <div class="location-details">
                            <span v-if="selectedLocation.paCost">Koszt: {{ selectedLocation.paCost }} PA</span>
                            <span v-if="selectedLocation.levelReq">Wymagany poziom: {{ selectedLocation.levelReq }}</span>
                        </div>
                        <button class="btn-enter" :disabled="!canEnterLocation(selectedLocation)" @click="confirmEnterLocation">
                            {{ getEnterButtonText(selectedLocation) }}
                        </button>
                    </div>
                </div>

                <div v-else-if="currentView === 'battleSelection'" class="inline-view battle-selection-inline">
                    <div class="inline-header">{{ selectedBattleLocation?.name || 'Wybór Walki' }}</div>
                    <div class="battle-selection-content">
                        <div
                            v-for="stage in battleStages"
                            :key="stage.stage"
                            class="battle-stage-card"
                            :class="{ locked: stage.locked }"
                            @click="selectBattleStage(stage)"
                        >
                            <div class="stage-background" :style="{ backgroundImage: `url(${currentMap.imageUrl})` }"></div>
                            <div class="stage-content">
                                <span class="stage-number">{{ stage.stage }}</span>
                                <span class="stage-label">Etap {{ stage.stage }}</span>
                                <span class="stage-level">Poziom {{ stage.level }}</span>
                                <span v-if="stage.locked" class="stage-lock">🔒</span>
                            </div>
                        </div>
                    </div>
                    <div class="inline-footer">
                        <button class="btn-back" @click="goBackToMap">← Powrót do mapy</button>
                    </div>
                </div>

                <div v-else-if="currentView === 'arena'" class="inline-view arena-inline">
                    <div class="inline-header">Arena</div>
                    <div class="arena-main-layout">
                        <div class="arena-left-panel">
                            <div class="arena-info-text">
                                <h3>Witaj na Arenie!</h3>
                                <p>Tutaj możesz zmierzyć się z losowymi przeciwnikami o różnej sile.</p>
                                <p class="arena-tip">Im trudniejsza walka, tym większa szansa na lepszą nagrodę.</p>
                            </div>
                        </div>
                        <div class="arena-right-panel" :style="{ backgroundImage: `url(${currentMap.imageUrl})` }">
                            <div class="arena-buttons-container">
                                <button class="arena-difficulty-btn easy" @click="startArenaFight('easy')">
                                    <span class="difficulty-name">Łatwa walka</span>
                                    <span class="difficulty-desc">Poziom {{ currentMap.levelRange.min }}</span>
                                </button>
                                <button class="arena-difficulty-btn medium" @click="startArenaFight('medium')">
                                    <span class="difficulty-name">Średnia walka</span>
                                    <span class="difficulty-desc">Poziom {{ currentMap.levelRange.min + 3 }}</span>
                                </button>
                                <button class="arena-difficulty-btn hard" @click="startArenaFight('hard')">
                                    <span class="difficulty-name">Trudna walka</span>
                                    <span class="difficulty-desc">Poziom {{ currentMap.levelRange.min + 6 }}</span>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="inline-footer">
                        <button class="btn-back" @click="goBackToMap">← Wyjdź z areny</button>
                    </div>
                </div>

                <div v-else-if="currentView === 'battle'" class="inline-view battle-inline">
                    <div class="inline-header">{{ battleResult?.name }}</div>
                    <div class="battle-main-layout">
                        <div class="battle-log-container">
                            <div ref="logScroll" class="battle-log-scroll">
                                <div
                                    v-for="(log, i) in battleResult?.log ?? []"
                                    :key="i"
                                    :class="['log-entry', log.type]"
                                    :style="log.color ? { color: log.color } : {}"
                                >
                                    <span class="log-text">{{ log.text }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="battle-visuals" :style="{ backgroundImage: `url(${currentMap.imageUrl})` }">
                            <div class="enemy-container">
                                <img v-if="battleResult?.enemy.imageUrl" :src="battleResult.enemy.imageUrl" :alt="battleResult.enemy.name" class="enemy-image-pixel">
                            </div>

                            <div v-if="battleResult?.rewards.drop" class="battle-drop">
                                <div class="drop-item" :class="battleResult.rewards.drop.rarityCss">
                                    <img :src="getItemImage(battleResult.rewards.drop)" :alt="battleResult.rewards.drop.name" class="drop-image">
                                    <span class="drop-name" :style="{ color: battleResult.rewards.drop.rarityColor }">{{ battleResult.rewards.drop.name }}</span>
                                </div>
                            </div>

                            <div class="battle-footer">
                                <div v-if="battleResult" class="battle-end-message">
                                    <span v-if="battleResult.won" class="win">Walka wygrana!</span>
                                    <span v-else class="lose">Walka przegrana</span>
                                </div>
                                <div class="battle-buttons">
                                    <button v-if="battleResult?.won" class="btn-battle-action btn-next" @click="startNextBattle">Idź dalej ➜</button>
                                    <button class="btn-battle-action" @click="closeBattle">Wróć do mapy</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-else-if="currentView === 'shop'" class="inline-view shop-inline">
                    <div class="inline-header">{{ shopName }}</div>
                    <div class="shop-main-content">
                        <div class="shop-tabs">
                            <button :class="{ active: shopTab === 'buy' }" @click="shopTab = 'buy'">Kup</button>
                            <button :class="{ active: shopTab === 'sell' }" @click="shopTab = 'sell'">Sprzedaj</button>
                        </div>

                        <div v-if="shopTab === 'buy'" class="shop-items-list">
                            <div
                                v-for="item in shopItems"
                                :key="item.id"
                                class="shop-row"
                                :class="{ 'cant-afford': user.gold < item.price, 'cant-use': (item.level ?? 1) > user.level }"
                                @click="buyItem(item)"
                                @mouseenter="showTooltip(item, $event)"
                                @mouseleave="hideTooltip"
                            >
                                <img :src="getItemImage(item)" :alt="item.name" class="shop-item-image">
                                <span class="item-name" :class="item.rarityCss" :style="{ color: item.rarityColor }">{{ item.name }}</span>
                                <span v-if="(item.level ?? 1) > 1" class="item-level">Poz. {{ item.level }}</span>
                                <span class="item-price" :class="{ 'no-gold': user.gold < item.price }">💰 {{ item.price }}</span>
                            </div>
                        </div>

                        <div v-if="shopTab === 'sell'" class="shop-items-list">
                            <div
                                v-for="item in inventoryFiltered"
                                :key="item.id"
                                class="shop-row"
                                @click="sellItem(inventory.indexOf(item))"
                                @mouseenter="showTooltip(item, $event)"
                                @mouseleave="hideTooltip"
                            >
                                <img :src="getItemImage(item)" :alt="item.name" class="shop-item-image">
                                <span class="item-name" :class="item.rarityCss" :style="{ color: item.rarityColor }">{{ item.name }}</span>
                                <span v-if="(item.quantity ?? 1) > 1" class="item-qty">x{{ item.quantity }}</span>
                                <span class="item-price sell-price">💰 {{ Math.floor(item.price * 0.5) }}</span>
                            </div>
                            <div v-if="inventoryFiltered.length === 0" class="empty-message">Plecak jest pusty</div>
                        </div>

                        <div class="shop-gold-bar">
                            Twoje złoto: <span class="gold-amount">{{ formatNumber(user.gold) }}</span>
                        </div>
                    </div>
                    <div class="inline-footer">
                        <button class="btn-back" @click="goBackToMap">← Wyjdź ze sklepu</button>
                    </div>
                </div>

                <div v-else-if="currentView === 'rest'" class="inline-view rest-inline">
                    <div class="inline-header">Odpoczynek</div>
                    <div class="rest-content">
                        <p class="rest-description">Odpocznij, aby zregenerować PA szybciej.</p>
                        <div class="rest-options">
                            <div class="rest-option" @click="rest(1)">
                                <span class="rest-time">1 minuta</span>
                                <span class="rest-bonus">+2 PA</span>
                            </div>
                            <div class="rest-option" @click="rest(5)">
                                <span class="rest-time">5 minut</span>
                                <span class="rest-bonus">+12 PA</span>
                            </div>
                            <div class="rest-option premium" @click="instantRest">
                                <span class="rest-time">Natychmiast</span>
                                <span class="rest-bonus">Pełne PA</span>
                                <span class="rest-price">💰 500</span>
                            </div>
                        </div>
                    </div>
                    <div class="inline-footer">
                        <button class="btn-back" @click="goBackToMap">← Powrót</button>
                    </div>
                </div>

                <div v-else-if="currentView === 'worldMap'" class="inline-view world-map-inline">
                    <div class="world-map-content">
                        <div class="world-map-image" :style="{ backgroundImage: `url('/game-assets/map.png')` }">
                            <div
                                v-for="worldMap in game.worldMaps"
                                :key="worldMap.id"
                                class="world-map-location"
                                :class="{ locked: worldMap.locked, current: worldMap.current }"
                                :style="{ left: worldMap.x + '%', top: worldMap.y + '%' }"
                                @click="selectWorldMap(worldMap)"
                            >
                                <span class="world-map-number">{{ worldMap.id }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="inline-footer">
                        <button class="btn-back" @click="goBackToMap">← Powrót</button>
                    </div>
                </div>
            </main>
        </div>

        <div v-if="showPAShop" class="modal" @click.self="showPAShop = false">
            <div class="modal-content pa-shop">
                <h2>Sklep z PA</h2>
                <div class="shop-items">
                    <div class="shop-item" @click="buyPA(5, 100)">
                        <span class="item-name">Mała butelka PA</span>
                        <span class="item-bonus">+5 PA</span>
                        <span class="item-price">💰 100</span>
                    </div>
                    <div class="shop-item" @click="buyPA(10, 180)">
                        <span class="item-name">Średnia butelka PA</span>
                        <span class="item-bonus">+10 PA</span>
                        <span class="item-price">💰 180</span>
                    </div>
                    <div class="shop-item" @click="buyPA(15, 250)">
                        <span class="item-name">Duża butelka PA</span>
                        <span class="item-bonus">+15 PA</span>
                        <span class="item-price">💰 250</span>
                    </div>
                </div>
                <button class="btn-close" @click="showPAShop = false">Zamknij</button>
            </div>
        </div>

        <div v-if="showSettings" class="modal" @click.self="showSettings = false">
            <div class="modal-content settings-modal">
                <h2>Ustawienia</h2>
                <div class="setting-row">
                    <label>Dźwięki</label>
                    <input v-model="settings.sound" type="checkbox">
                </div>
                <div class="setting-row">
                    <label>Muzyka</label>
                    <input v-model="settings.music" type="checkbox">
                </div>
                <div class="setting-row">
                    <label>Powiadomienia</label>
                    <input v-model="settings.notifications" type="checkbox">
                </div>
                <button class="btn-close" @click="showSettings = false">Zamknij</button>
            </div>
        </div>

        <div v-if="alertMessage" class="modal" @click.self="alertMessage = ''">
            <div class="modal-content settings-modal">
                <h2>Alert</h2>
                <p>{{ alertMessage }}</p>
                <button class="btn-close" @click="alertMessage = ''">OK</button>
            </div>
        </div>

        <footer id="game-footer"></footer>

        <div v-if="tooltipItem" id="tip" class="t_item" :style="{ left: tooltipX + 'px', top: tooltipY + 'px', display: 'block' }">
            <div class="tipInnerContainer">
                <b :class="tooltipItem.rarityCss">{{ tooltipItem.name }}</b>
                <i v-if="tooltipItem.rarity !== 'common'" :class="tooltipItem.rarityCss">{{ tooltipItem.itemTypeName }} ({{ tooltipItem.rarityName }})</i>
                <i v-else>{{ tooltipItem.itemTypeName }}</i>
                <i v-if="(tooltipItem.level ?? 1) > 1" class="idesc">Wymagany poziom: {{ tooltipItem.level }}</i>
                <div class="tip-divider"></div>
                <i v-if="tooltipItem.dmgMin !== undefined" class="idesc stat-dmg">Obrażenia: {{ tooltipItem.dmgMin }}-{{ tooltipItem.dmgMax }}</i>
                <i v-if="tooltipItem.armor !== undefined" class="idesc stat-dmg">Pancerz: +{{ tooltipItem.armor }}</i>
                <i v-if="tooltipItem.effect === 'heal'" class="idesc stat-heal">Leczy {{ tooltipItem.effectValue }} punktów życia</i>
                <i v-if="tooltipItem.effect === 'pa'" class="idesc stat-heal">Przywraca {{ tooltipItem.effectValue }} PA</i>
                <i v-for="stat in bonusRows(tooltipItem)" :key="stat.key" class="idesc stat-bonus">+{{ stat.value }}{{ stat.suffix }} {{ stat.name }}</i>
                <div class="tip-divider"></div>
                <i v-if="tooltipItem.power" class="idesc stat-power">Moc przedmiotu: {{ tooltipItem.power }}</i>
                <i class="idesc stat-gold">Wartość: {{ tooltipItem.price }} złota</i>
            </div>
        </div>
    </div>
</template>
