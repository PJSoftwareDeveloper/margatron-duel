<script setup lang="ts">
import BattleLogEntry from '@/Components/Game/BattleLogEntry.vue';
import GameTopBar from '@/Components/Game/GameTopBar.vue';
import PlayerSidebar from '@/Components/Game/PlayerSidebar.vue';
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import { echo } from '@/echo';
import type { AxiosError } from 'axios';
import type { ActionPointsChangedEvent, ActionPointState, BattleResult, EquipmentSlot, GameSnapshot, Item, Npc, Location, PlayerAttributeKey, RestOption, Stage } from '@/types/game';


type Resource<T> = T | { data: T };
type GameView = 'map' | 'battleSelection' | 'arena' | 'toughenemy' | 'battle' | 'shop' | 'rest' | 'worldMap';

const props = defineProps<{
    game: Resource<GameSnapshot>;
}>();

const game = ref(unwrap(props.game));
const currentView = ref<GameView>('map');
const selectedLocation = ref<Location | null>(null);
const selectedBattleLocation = ref<Location | null>(null);
const lastBattleStage = ref<number | null>(null);
const battleResult = ref<BattleResult | null>(null);
const canContinueBattle = computed(() => battleResult.value?.won === true && lastBattleStage.value !== null);
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
const actionPointFlash = ref(false);
const nowTick = ref(Date.now());
let actionPointRefreshTimer: number | undefined;
let actionPointFlashTimer: number | undefined;
let restCountdownTimer: number | undefined;
let restRefreshTimer: number | undefined;
let isGameViewDisposed = false;
const actionPointChannel = `users.${game.value.user.id}`;
const actionPointFallbackGraceMs = 2000;
const restFallbackGraceMs = 1500;

const user = computed(() => game.value.user);
const currentMap = computed(() => game.value.currentMap);
const equipped = computed(() => user.value.equipped);
const inventory = computed(() => user.value.inventory);
const inventoryFiltered = computed(() => inventory.value.filter(Boolean) as Item[]);
const currentShop = computed(() => currentShopId.value ? game.value.shops[currentShopId.value] : null);
const shopName = computed(() => currentShop.value?.name ?? '');
const shopItems = computed(() => currentShop.value?.items ?? []);
const instantRestConfig = computed(() => game.value.rest.instant);
const battleStages = computed(() => selectedBattleLocation.value?.stages?.map((stage) => ({
    ...stage,
    id: stage.stage,
    locked: !stage.unlocked,
})) ?? []);

const mapLocations = computed(() => currentMap.value.locations.map((location) => decorateLocation(location)));
const mapNpcs = computed(() => currentMap.value.npcs.map((npc) => npc));

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

function clearActionPointFlash(): void {
    if (actionPointFlashTimer !== undefined) {
        window.clearTimeout(actionPointFlashTimer);
        actionPointFlashTimer = undefined;
    }
}

function clearRestCountdown(): void {
    if (restCountdownTimer !== undefined) {
        window.clearInterval(restCountdownTimer);
        restCountdownTimer = undefined;
    }
}

function clearRestRefresh(): void {
    if (restRefreshTimer !== undefined) {
        window.clearTimeout(restRefreshTimer);
        restRefreshTimer = undefined;
    }
}

function startRestCountdown(): void {
    clearRestCountdown();
    restCountdownTimer = window.setInterval(() => {
        nowTick.value = Date.now();
    }, 1000);
}

function scheduleRestRefresh(): void {
    clearRestRefresh();

    if (isGameViewDisposed) {
        return;
    }

    const nextRestEndsAt = game.value.rest.options
        .map((option) => option.endsAt ? Date.parse(option.endsAt) : null)
        .filter((timestamp): timestamp is number => timestamp !== null && timestamp > Date.now())
        .sort((left, right) => left - right)[0] ?? null;

    if (!nextRestEndsAt) {
        return;
    }

    restRefreshTimer = window.setTimeout(async () => {
        try {
            await refreshGameState();
        } finally {
            if (!isGameViewDisposed) {
                scheduleRestRefresh();
            }
        }
    }, Math.max(250, nextRestEndsAt - Date.now() + restFallbackGraceMs));
}

function actionPointRefreshDelay(): number {
    if (user.value.paRegeneratesAt) {
        return Math.max(250, Date.parse(user.value.paRegeneratesAt) - Date.now() + actionPointFallbackGraceMs);
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

function flashActionPoints(): void {
    clearActionPointFlash();
    actionPointFlash.value = true;
    actionPointFlashTimer = window.setTimeout(() => {
        actionPointFlash.value = false;
    }, 900);
}

function syncActionPoints(actionPoints: ActionPointState): void {
    const previousActionPoints = user.value.pa;

    game.value = {
        ...game.value,
        user: {
            ...game.value.user,
            ...actionPoints,
        },
    };

    if (actionPoints.pa > previousActionPoints) {
        flashActionPoints();
    }
}

function handleActionPointsChanged(event: ActionPointsChangedEvent): void {
    syncActionPoints(event.actionPoints);
}

function decorateLocation(location: Location): Location {
    const meta = {
        battle: { icon: '⚔', description: 'Expowisko z pięcioma etapami walki.' },
        arena: { icon: '♜', description: 'Arena z losowymi przeciwnikami.' },
        toughenemy: { icon: 'X', description: 'Walka z mocnym przeciwnikiem.' },
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

function restOption(minutes: 1 | 5): RestOption | undefined {
    return game.value.rest.options.find((option) => option.minutes === minutes);
}

function restRemainingSeconds(minutes: 1 | 5): number {
    const option = restOption(minutes);

    if (!option?.endsAt) {
        return 0;
    }

    return Math.max(0, Math.ceil((Date.parse(option.endsAt) - nowTick.value) / 1000));
}

function isRestOptionActive(minutes: 1 | 5): boolean {
    return restRemainingSeconds(minutes) > 0;
}

function formatCountdown(seconds: number): string {
    const minutes = Math.floor(seconds / 60);
    const remainingSeconds = seconds % 60;

    return `${minutes}:${remainingSeconds.toString().padStart(2, '0')}`;
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
        toughenemy: 'Rzuć wyzwanie',
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
    }else if (location.type === 'toughenemy') {
        currentView.value = 'toughenemy';
    }

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
    selectedLocation.value = null;
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


async function startToughFight(enemyType: 'elite' | 'elite2' | 'hero'): Promise<void> {
    if (!selectedLocation.value) {
        return;
    }

    lastBattleStage.value = null;

    try{
        const response = await axios.post('/game/actions/battle/tough', {
            locationId: selectedLocation.value.id,
            enemyType: enemyType,
        });

        battleResult.value = response.data.battle;
        syncGame(response.data.game);
        currentView.value = 'battle';
        await scrollLog();
    } catch (error) {
        showActionError(error);
    }
}

async function startArenaFight(difficulty: 'easy' | 'medium' | 'hard'): Promise<void> {
    lastBattleStage.value = null;
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
    lastBattleStage.value = null;
    currentView.value = 'map';
    selectedLocation.value = null;
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

async function unequip(slot: EquipmentSlot): Promise<void> {
    if (!equipped.value[slot]) {
        return;
    }

    await action('/game/actions/inventory/unequip', { slot });
}

async function rest(minutes: 1 | 5): Promise<void> {
    if (isRestOptionActive(minutes)) {
        return;
    }

    await action('/game/actions/rest', { minutes });
}

async function instantRest(): Promise<void> {
    if (user.value.gold < instantRestConfig.value.goldPrice) {
        return;
    }

    await action('/game/actions/rest/instant');
}

async function addAttribute(attribute: PlayerAttributeKey): Promise<void> {
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

function disposeGameView(): void {
    isGameViewDisposed = true;
    clearActionPointRefresh();
    clearActionPointFlash();
    clearRestCountdown();
    clearRestRefresh();
    echo?.leave(actionPointChannel);
}

onMounted(() => {
    startRestCountdown();
    echo?.private(actionPointChannel)
        .listen('.action-points.changed', handleActionPointsChanged);
});

watch(
    () => [user.value.pa, user.value.paRegenerationLimit, user.value.paRegenerationSeconds, user.value.paRegeneratesAt],
    scheduleActionPointRefresh,
    { immediate: true },
);

watch(
    () => game.value.rest.options.map((option) => option.endsAt).join('|'),
    scheduleRestRefresh,
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
        <GameTopBar active="game" @settings="showSettings = true" @navigating="disposeGameView" />

        <div id="main-content">
            <PlayerSidebar
                :user="user"
                :action-point-flash="actionPointFlash"
                @open-pa-shop="showPAShop = true"
                @add-attribute="addAttribute"
                @unequip="unequip"
                @use-inventory-item="useItem"
                @sell-inventory-item="showItemMenu"
                @show-tooltip="showTooltip"
                @hide-tooltip="hideTooltip"
            />

            <main id="map-area">
                <div v-if="currentView === 'map'" class="view-map">
                    <div class="map-container">
                        <div class="map-image" :style="{ backgroundImage: `url(${currentMap.imageUrl})` }">
                            <div class="map-name-label">{{ currentMap.name }}</div>

                            <div
                                v-for="npc in mapNpcs"
                                :key="npc.id"
                                class="map-npc"
                                :style="{ position: 'absolute', left: npc.x * 32 + 'px', top: npc.y * 32 + 'px', backgroundImage: `url(${npc.imageUrl})`, width: npc.width + 'px', height: npc.height + 'px' }"
                                :alt-text="npc.name"
                                >

                            </div>
                            <div
                                v-for="location in mapLocations"
                                :key="location.id"
                                class="map-location"
                                :class="[location.type, { locked: location.locked }]"
                                :style="{ left: location.x * 32 + 'px', top: location.y * 32 + 'px', width: location.width * 32 + 'px', height: location.height * 32 + 'px', }"
                                
                                @click="enterLocation(location)"
                            >
                                <div class="location-text">
                                    <span class="location-name">{{ location.name }}</span>
                                    <span v-if="location.levelMin !== undefined && location.levelMax !== undefined" class="location-level">
                                        Poziom {{ location.levelMin }}-{{ location.levelMax }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-if="selectedLocation" class="location-info">
                        <h3>{{ selectedLocation.name }}</h3>
                        <p>{{ selectedLocation.description }}</p>
                        <div class="location-details">
                            <span v-if="selectedLocation.paCost">Koszt: {{ selectedLocation.paCost }} PA</span>
                            <span v-if="selectedLocation.levelReq">Wymagany poziom: {{ selectedLocation.levelReq }}</span>
                            <span v-if="selectedLocation.levelMin !== undefined && selectedLocation.levelMax !== undefined">
                                Poziom przeciwników: {{ selectedLocation.levelMin }}-{{ selectedLocation.levelMax }}
                            </span>
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
                            <div class="stage-background" :style="{ backgroundImage: `url(${selectedLocation.imageUrl})`, backgroundPositionX: -(87 * (stage.stage-1)) + `px` }"></div>
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
                <div v-else-if="currentView === 'toughenemy'" class="inline-view arena-inline">
                    <div class="inline-header">Mocny przeciwnik</div>
                    <div class="arena-main-layout">
                        <div class="arena-left-panel">
                            <div class="arena-info-text">
                                <h3>Witaj!</h3>
                                <p>Tutaj możesz zmierzyć się z silnym przeciwnikiem.</p>
                            </div>
                        </div>
                        <div class="arena-right-panel" :style="{ backgroundImage: `url(${selectedLocation.imageUrl})` }">
                            <div class="arena-buttons-container">
                                <button class="arena-difficulty-btn easy" @click="startToughFight('elite')">
                                    <span class="difficulty-name">Walka z elitą</span>
                                    <span class="difficulty-desc">Poziom {{ currentMap.levelRange.min }}</span>
                                </button>
                                <button class="arena-difficulty-btn medium" @click="startToughFight('elite2')">
                                    <span class="difficulty-name">Walka z elitą 2</span>
                                    <span class="difficulty-desc">Poziom {{ currentMap.levelRange.min + 5}}</span>
                                </button>
                                <button class="arena-difficulty-btn hard" @click="startToughFight('hero')">
                                    <span class="difficulty-name">Walka z herosem</span>
                                    <span class="difficulty-desc">Poziom {{ currentMap.levelRange.max }}</span>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="inline-footer">
                        <button class="btn-back" @click="goBackToMap">← Wyjdź</button>
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
                        <div class="arena-right-panel" :style="{ backgroundImage: `url(${selectedLocation.imageUrl})` }">
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
                                <BattleLogEntry
                                    v-for="(log, i) in battleResult?.log ?? []"
                                    :key="i"
                                    :log="log"
                                />
                            </div>
                        </div>

                        <div class="battle-visuals" :style="{ backgroundImage: `url(${selectedLocation.imageUrl})` }">
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
                                    <button v-if="canContinueBattle" class="btn-battle-action btn-next" @click="startNextBattle">Idź dalej ➜</button>
                                    <button class="btn-battle-action" @click="closeBattle">Wróć do mapy</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-else-if="currentView === 'shop'" class="inline-view shop-inline">
                    <div class="inline-header">{{ shopName }}</div>
                    <div class="shop-main-content" :style="{ backgroundImage: `url(${selectedLocation?.imageUrl})`, backgroundPositionY: (60)+`%`, backgroundSize: `100%` }">
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
                    <div class="rest-content" :style="{ 
                        backgroundImage: `url(${selectedLocation?.imageUrl})`, 
                        backgroundPositionY: (60)+`%`, 
                        backgroundSize: `100%` 
                        }">
                    
                        <p class="rest-description">Odpocznij, aby zregenerować PA szybciej.</p>
                        <div class="rest-options">
                            <button
                                class="rest-option"
                                :class="{ active: isRestOptionActive(1) }"
                                type="button"
                                :disabled="isRestOptionActive(1)"
                                @click="rest(1)"
                            >
                                <span class="rest-time">1 minuta</span>
                                <span class="rest-bonus">+2 PA</span>
                                <span class="rest-countdown">
                                    {{ isRestOptionActive(1) ? `Odbiór za ${formatCountdown(restRemainingSeconds(1))}` : 'Rozpocznij' }}
                                </span>
                            </button>
                            <button
                                class="rest-option"
                                :class="{ active: isRestOptionActive(5) }"
                                type="button"
                                :disabled="isRestOptionActive(5)"
                                @click="rest(5)"
                            >
                                <span class="rest-time">5 minut</span>
                                <span class="rest-bonus">+12 PA</span>
                                <span class="rest-countdown">
                                    {{ isRestOptionActive(5) ? `Odbiór za ${formatCountdown(restRemainingSeconds(5))}` : 'Rozpocznij' }}
                                </span>
                            </button>
                            <button
                                class="rest-option premium"
                                type="button"
                                :disabled="user.gold < instantRestConfig.goldPrice"
                                @click="instantRest"
                            >
                                <span class="rest-time">Natychmiast</span>
                                <span class="rest-bonus">Pełne PA do {{ instantRestConfig.targetActionPoints }}</span>
                                <span class="rest-price">💰 {{ formatNumber(instantRestConfig.goldPrice) }}</span>
                            </button>
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
                <i v-if="tooltipItem.rarity !== 'common'" class="rarity">{{ tooltipItem.rarityName }}</i>
                <br/>
                <i v-if="tooltipItem.dmgMin !== undefined" class="idesc">Obrażenia: {{ tooltipItem.dmgMin }}-{{ tooltipItem.dmgMax }}</i>
                <i v-if="tooltipItem.armor !== undefined" class="idesc">Pancerz: {{ tooltipItem.armor }}</i>
                <i v-if="tooltipItem.effect === 'heal'" class="idesc">Leczy {{ tooltipItem.effectValue }} punktów życia</i>
                <i v-if="tooltipItem.effect === 'pa'" class="idesc">Przywraca {{ tooltipItem.effectValue }} PA</i>
                <i v-for="stat in bonusRows(tooltipItem)" :key="stat.key" class="idesc">{{ stat.name }}: {{ stat.value }}{{ stat.suffix }}</i>
                
                <br/>
                <i v-if="(tooltipItem.level ?? 1) > 1" class="idesc">Wymagany poziom: {{ tooltipItem.level }}</i>
                <i v-if="tooltipItem.power" class="idesc">Moc przedmiotu: {{ tooltipItem.power }}</i>
                <i class="idesc">Wartość: {{ tooltipItem.price }}</i>
            
                
            </div>
           
           
            <!--<div class="tipInnerContainer">
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
            </div>-->
        </div>
    </div>
</template>
