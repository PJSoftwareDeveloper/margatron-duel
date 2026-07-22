<script setup lang="ts">
import type { BattleLog } from '@/types/game';
import { computed } from 'vue';

const props = defineProps<{
    log: BattleLog;
}>();

const entryClasses: Partial<Record<BattleLog['type'], string>> = {
    'battle-start': 'info',
    'level-up': 'levelup',
    'attribute-points': 'info',
};

const entryClass = computed(() => {
    if (props.log.type === 'attack') {
        return `${props.log.actor}-attack`;
    }

    return entryClasses[props.log.type] ?? props.log.type;
});

const icon = computed(() => {
    if (props.log.type !== 'attack') {
        return null;
    }

    return props.log.actor === 'player'
        ? '/game-assets/items/miecz05_pol.gif'
        : '/game-assets/items/tar_tarcza09.gif';
});
</script>

<template>
    <div
        class="log-entry"
        :class="entryClass"
        :style="log.type === 'drop' ? { color: log.color } : undefined"
    >
        <img v-if="icon" :src="icon" alt="">

        <span class="log-text">
            <template v-if="log.type === 'battle-start'">
                Walka z {{ log.enemyName }} została rozpoczęta!
            </template>

            <template v-else-if="log.type === 'attack' && log.actor === 'player'">
                <b class="player-attack">Zadałeś</b> przeciwnikowi {{ log.damage }} obrażeń.
                <b class="enemy-attack">{{ log.targetName }}</b> otrzymał {{ log.damage }} obrażeń,
                {{ log.remainingHp }} PŻ pozostało.<template v-if="log.critical"> KRYTYK!</template>
            </template>

            <template v-else-if="log.type === 'attack' && log.actor === 'enemy'">
                <b class="enemy-attack">{{ log.actorName }}</b> uderzył z siłą {{ log.attackPower }} obrażeń.
                Obecny pancerz: {{ log.armor }}.
                <b class="player-attack">Otrzymałeś</b> {{ log.damage }} obrażeń,
                {{ log.remainingHp }} PŻ pozostało.
            </template>

            <template v-else-if="log.type === 'dodge'">
                Unikasz ataku przeciwnika {{ log.attackerName }}!
            </template>

            <template v-else-if="log.type === 'reward'">
                Doświadczenie: {{ log.amount }}p
            </template>

            <template v-else-if="log.type === 'level-up'">
                Awansujesz na poziom {{ log.level }}!
            </template>

            <template v-else-if="log.type === 'attribute-points'">
                +{{ log.levelsGained }} poziom, +{{ log.points }} punkty atrybutów
            </template>

            <template v-else-if="log.type === 'drop'">
                Zdobyto: {{ log.itemName }}!
            </template>

            <template v-else-if="log.type === 'defeat'">
                Zostałeś pokonany!
            </template>
        </span>
    </div>
</template>
