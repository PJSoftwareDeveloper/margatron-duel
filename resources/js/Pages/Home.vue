<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const alertMessage = ref('');
const showRecovery = ref(false);
const remail = ref('');
const online = ref(0);

const registerForm = useForm({
    nick: '',
    email: '',
    password: '',
    password_confirmation: '',
});

const loginForm = useForm({
    email: '',
    password: '',
});

const isLoading = computed(() => registerForm.processing || loginForm.processing);
const nickValid = computed(() => registerForm.nick.length >= 4 && /^[a-z0-9]+$/i.test(registerForm.nick));
const emailValid = computed(() => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(registerForm.email));
const passValid = computed(() => registerForm.password.length >= 6);
const pass2Valid = computed(() => registerForm.password_confirmation.length >= 6 && registerForm.password_confirmation === registerForm.password);
const lemailValid = computed(() => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(loginForm.email));
const lpassValid = computed(() => loginForm.password.length >= 6);

function firstError(errors: Record<string, string>): string {
    return Object.values(errors)[0] ?? '';
}

function register(): void {
    if (!pass2Valid.value) {
        alertMessage.value = 'Hasła nie są identyczne';
        return;
    }

    if (!nickValid.value || !emailValid.value || !passValid.value) {
        alertMessage.value = 'Wypełnij poprawnie wszystkie pola';
        return;
    }

    registerForm.post('/register', {
        onError: (errors) => {
            alertMessage.value = firstError(errors);
        },
    });
}

function login(): void {
    if (!lemailValid.value || !lpassValid.value) {
        alertMessage.value = 'Wypełnij poprawnie wszystkie pola';
        return;
    }

    loginForm.post('/login', {
        onError: (errors) => {
            alertMessage.value = firstError(errors);
        },
    });
}

function recoverPassword(): void {
    alertMessage.value = `Link resetujący został wysłany na: ${remail.value}`;
    showRecovery.value = false;
}
</script>

<template>
    <Head title="Start" />

    <div id="centerbox">
        <header id="logo">
            <div id="ver">v. 0.1</div>
        </header>

        <div v-if="alertMessage" id="alert" @click="alertMessage = ''">
            <div id="box">
                <header>Alert</header>
                <section>{{ alertMessage }}</section>
                <button @click="alertMessage = ''">OK</button>
            </div>
        </div>

        <div id="content">
            <div id="welcome">
                <section id="signup">
                    <label>Nick:</label>
                    <input v-model="registerForm.nick" maxlength="20" :class="{ good: nickValid }">

                    <label>Email:</label>
                    <input v-model="registerForm.email" maxlength="40" :class="{ good: emailValid }">

                    <label>Hasło:</label>
                    <input v-model="registerForm.password" type="password" maxlength="20" :class="{ good: passValid }">

                    <label>Potwierdź hasło:</label>
                    <input v-model="registerForm.password_confirmation" type="password" maxlength="20" :class="{ good: pass2Valid }">

                    <button :disabled="isLoading" @click="register">
                        {{ registerForm.processing ? 'Rejestracja...' : 'Załóż konto' }}
                    </button>
                </section>

                <section id="login">
                    <label>Email:</label>
                    <input v-model="loginForm.email" maxlength="40" :class="{ good: lemailValid }">

                    <label>Hasło:</label>
                    <input
                        v-model="loginForm.password"
                        type="password"
                        maxlength="20"
                        :class="{ good: lpassValid }"
                        @keyup.enter="login"
                    >

                    <button :disabled="isLoading" @click="login">
                        {{ loginForm.processing ? 'Logowanie...' : 'Logowanie' }}
                    </button>
                    <small @click="showRecovery = true">Zapomniałeś hasła?</small>
                </section>
            </div>

            <section v-if="showRecovery" id="passrecover">
                <h2>Odzyskiwanie hasła</h2>
                <label>Email:</label>
                <input v-model="remail" maxlength="40">
                <button @click="recoverPassword">Resetuj hasło</button>
                <button @click="showRecovery = false">Anuluj</button>
            </section>
        </div>

        <footer>
            &copy; 2026 <a href="#">Pan z Margo</a> |
            online: <span id="online">{{ online }}</span> |
            lang:pl | not logged
        </footer>
    </div>
</template>
