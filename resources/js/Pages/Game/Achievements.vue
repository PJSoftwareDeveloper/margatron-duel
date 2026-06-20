<script setup lang="ts">
import GameTopBar from '@/Components/Game/GameTopBar.vue';
import PlayerSidebar from '@/Components/Game/PlayerSidebar.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import type { GameSnapshot, PlayerAchievements } from '@/types/game';

type Resource<T> = T | { data: T };

const props = defineProps<{
    game: Resource<GameSnapshot>;
    achievements: PlayerAchievements;
}>();

const game = computed(() => unwrap(props.game));
const user = computed(() => game.value.user);
const showSettings = ref(false);
const settings = ref({ sound: true, music: false, notifications: true });

function unwrap<T extends object>(resource: Resource<T>): T {
    return 'data' in resource ? resource.data : resource;
}

function closeAchievements(): void {
    router.get('/game');
}
</script>

<template>
    <Head title="Osiągnięcia" />

    <div id="game-container">
        <GameTopBar active="achievements" @settings="showSettings = true" />

        <div id="main-content">
            <PlayerSidebar :user="user" read-only />

            <main id="map-area">
                <div class="achievements-view">
                    <header class="achievements-header">
                        <div>
                            <h1>Osiągnięcia</h1>
                            <p>Postęp całkowity: {{ props.achievements.overallPercent }}%</p>
                        </div>
                        <button class="achievements-close" type="button" @click="closeAchievements">Zamknij</button>
                    </header>

                    <div class="achievements-scroll">
                        <article
                            v-for="achievement in props.achievements.entries"
                            :key="achievement.id"
                            class="achievement-card"
                            :class="{ completed: achievement.completed }"
                        >
                            <div class="achievement-body">
                                <strong>{{ achievement.label }}</strong>
                                <span>{{ achievement.progressLabel }}</span>
                                <div class="achievement-progress">
                                    <div :style="{ width: `${achievement.percent}%` }"></div>
                                </div>
                            </div>
                            <div class="achievement-icon">{{ achievement.icon }}</div>
                        </article>
                    </div>
                </div>
            </main>
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
                <button class="btn-close" type="button" @click="showSettings = false">Zamknij</button>
            </div>
        </div>

        <footer id="game-footer"></footer>
    </div>
</template>
