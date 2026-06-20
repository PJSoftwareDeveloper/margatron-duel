export type Item = {
    id: string | number;
    shopItemId?: string | number;
    name: string;
    icon?: string;
    image?: string;
    imageUrl?: string;
    type: string;
    itemType?: string;
    itemTypeName?: string;
    rarity?: string;
    rarityName?: string;
    rarityColor?: string;
    rarityCss?: string;
    level?: number;
    stats?: Record<string, number>;
    bonusStats?: Record<string, number | { value: number; name: string; suffix: string }>;
    effect?: string | null;
    effectValue?: number | null;
    power?: number;
    price: number;
    quantity?: number;
    dmgMin?: number;
    dmgMax?: number;
    armor?: number;
};

export type Player = {
    id: number;
    profileId: number;
    nick: string;
    email: string;
    level: number;
    exp: number;
    expMax: number;
    gold: number;
    pa: number;
    paMax: number;
    paLimit: number;
    paRegenerationLimit: number;
    paRegenerationSeconds: number;
    paRegeneratesAt: string | null;
    vitality: number;
    strength: number;
    luck: number;
    attributePoints: number;
    hp: number;
    hpMax: number;
    dmgMin: number;
    dmgMax: number;
    armor: number;
    critChance: number;
    critPower: number;
    dodge: number;
    stun: number;
    currentMapId: number;
    inventory: Array<Item | null>;
    equipped: {
        weapon: Item | null;
        armor: Item | null;
        accessory: Item | null;
    };
};

export type EquipmentSlot = keyof Player['equipped'];
export type PlayerAttributeKey = 'vitality' | 'strength' | 'luck';

export type ActionPointState = Pick<Player, 'pa' | 'paMax' | 'paLimit' | 'paRegenerationLimit' | 'paRegenerationSeconds' | 'paRegeneratesAt'>;

export type ActionPointsChangedEvent = {
    actionPoints: ActionPointState;
};

export type RestOption = {
    minutes: 1 | 5;
    durationSeconds: number;
    actionPoints: number;
    active: boolean;
    endsAt: string | null;
    remainingSeconds: number;
};

export type RestState = {
    options: RestOption[];
    instant: {
        goldPrice: number;
        targetActionPoints: number;
    };
};

export type Stage = {
    stage: number;
    id?: number;
    level: number;
    unlocked: boolean;
    completed: boolean;
    locked?: boolean;
};

export type Location = {
    id: string;
    name: string;
    type: 'battle' | 'arena' | 'shop' | 'rest' | 'worldmap';
    x: number;
    y: number;
    pa?: number;
    paCost?: number;
    levelReq?: number;
    levelMin?: number;
    levelMax?: number;
    shopId?: string;
    enemies?: string[];
    stages?: Stage[];
    unlockedStage?: number;
    icon?: string;
    description?: string;
    locked?: boolean;
};

export type GameMap = {
    id: number;
    name: string;
    image: string;
    imageUrl: string;
    requiredLevel: number;
    levelRange: {
        min: number;
        max: number;
    };
    locations: Location[];
};

export type WorldMap = {
    id: number;
    x: number;
    y: number;
    name: string;
    requiredLevel: number;
    locked: boolean;
    current: boolean;
};

export type Shop = {
    id: string;
    name: string;
    items: Item[];
};

export type GameSnapshot = {
    user: Player;
    currentMap: GameMap;
    worldMaps: WorldMap[];
    shops: Record<string, Shop>;
    rest: RestState;
};

export type RankingEntry = {
    position: number;
    profileId: number;
    userId: number;
    nick: string;
    level: number;
    currentUser: boolean;
};

export type PlayerRanking = {
    entries: RankingEntry[];
    currentPosition: number;
    activeSort: 'level';
};

export type AchievementEntry = {
    id: string;
    label: string;
    icon: string;
    value: number;
    target: number;
    percent: number;
    progressLabel: string;
    completed: boolean;
};

export type PlayerAchievements = {
    entries: AchievementEntry[];
    completedCount: number;
    totalCount: number;
    overallPercent: number;
};

export type BattleLog = {
    text: string;
    type: string;
    color?: string;
};

export type BattleResult = {
    name: string;
    enemy: {
        name: string;
        image?: string;
        imageUrl?: string;
        hp: number;
        level: number;
    };
    won: boolean;
    playerHp: number;
    enemyHp: number;
    arenaDifficulty: string | null;
    log: BattleLog[];
    rewards: {
        exp: number;
        gold: number;
        level: unknown;
        drop: Item | null;
        dropAdded: boolean;
    };
};
