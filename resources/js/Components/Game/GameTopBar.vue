<script setup lang="ts">
import { useAppVersion } from '@/Composables/useAppVersion';
import { router } from '@inertiajs/vue3';

const props = withDefaults(defineProps<{
    active?: 'game' | 'rankings' | 'achievements';
}>(), {
    active: 'game',
});

const emit = defineEmits<{
    settings: [];
    navigating: [];
}>();

const appVersion = useAppVersion();

function visit(url: string): void {
    emit('navigating');
    router.get(url);
}

function logout(): void {
    emit('navigating');
    router.post('/logout');
}
</script>

<template>
    <header id="top-bar">
        <div id="logo-container">
            <span class="version">v.{{ appVersion }}</span>
        </div>

        <nav id="top-nav">
            <button class="nav-btn" type="button" :style="{ width: '62px', height: '42px', backgroundPositionX: '-539px'}" disabled>FORUM</button>
            <button
                class="nav-btn"
                :class="{ active: props.active === 'rankings' }"
                type="button"
                 :style="{ width: '81px', height: '42px', backgroundPositionX: '-601px'}"
                @click="visit('/rankings')"
            >
                RANKINGI
            </button>
            <button
                class="nav-btn"
                :class="{ active: props.active === 'achievements' }"
                type="button"
                 :style="{ width: '104px', height: '42px', backgroundPositionX: '-682px'}"
                @click="visit('/achievements')"
            >
                OSIĄGNIĘCIA
            </button>
            <button class="nav-btn" type="button" :style="{ width: '116px', height: '42px', backgroundPositionX: '-786px'}" @click="emit('settings')">KONFIGURACJA</button>
            <button class="nav-btn logout" type="button" :style="{ width: '78px', height: '42px', backgroundPositionX: '-902px'}" @click="logout">WYLOGUJ</button>
        </nav>
    </header>
</template>
