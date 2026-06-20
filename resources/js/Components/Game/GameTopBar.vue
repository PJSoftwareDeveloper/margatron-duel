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
            <button class="nav-btn" type="button" disabled>FORUM</button>
            <button
                class="nav-btn"
                :class="{ active: props.active === 'rankings' }"
                type="button"
                @click="visit('/rankings')"
            >
                RANKINGI
            </button>
            <button
                class="nav-btn"
                :class="{ active: props.active === 'achievements' }"
                type="button"
                @click="visit('/achievements')"
            >
                OSIĄGNIĘCIA
            </button>
            <button class="nav-btn" type="button" @click="emit('settings')">KONFIGURACJA</button>
            <button class="nav-btn logout" type="button" @click="logout">WYLOGUJ</button>
        </nav>
    </header>
</template>
