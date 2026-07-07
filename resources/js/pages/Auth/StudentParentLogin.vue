<script setup>
import logo from '@/assets/images/logo.png';
import schoolPhoto from '@/assets/images/philsca.png';
import { useForm } from '@inertiajs/vue3';
import {
    getSavedStudentParentProfiles,
    removeSavedStudentParentProfile,
} from '@/composables/useSavedStudentParentProfiles';
import { computed, onMounted, ref } from 'vue';

const profiles = ref([]);
const selectedIndex = ref(0);
const useDifferentAccount = ref(true);
const selectedProfile = computed(
    () => profiles.value[selectedIndex.value] ?? null,
);

const form = useForm({
    email: '',
    password: '',
    remember: true,
});

const helperText = computed(() =>
    useDifferentAccount.value || !selectedProfile.value
        ? 'Enter your Student or Parent account credentials.'
        : `Welcome back, ${selectedProfile.value.name}`,
);

const selectProfile = (index) => {
    selectedIndex.value = index;
    useDifferentAccount.value = false;
    form.email = selectedProfile.value.email;
    form.password = '';
    form.clearErrors();
};

const showDifferentAccount = () => {
    useDifferentAccount.value = true;
    form.email = '';
    form.password = '';
    form.clearErrors();
};

const removeProfile = (index) => {
    const profile = profiles.value[index];

    if (!profile) return;

    profiles.value = removeSavedStudentParentProfile(profile.email);
    selectedIndex.value = Math.min(
        index,
        Math.max(profiles.value.length - 1, 0),
    );

    if (profiles.value.length === 0) {
        showDifferentAccount();
        return;
    }

    selectProfile(selectedIndex.value);
};

const login = () => {
    if (!useDifferentAccount.value && selectedProfile.value) {
        form.email = selectedProfile.value.email;
    }

    form.post('/login');
};

onMounted(() => {
    profiles.value = getSavedStudentParentProfiles();

    if (profiles.value.length > 0) {
        selectProfile(0);
    }
});
</script>

<template>
    <div class="min-h-screen bg-white p-2">
        <main
            class="grid min-h-[calc(100vh-1rem)] overflow-hidden bg-white lg:grid-cols-[42%_58%]"
        >
            <section
                class="relative hidden overflow-hidden bg-slate-900 lg:block"
            >
                <img
                    :src="schoolPhoto"
                    alt="School building"
                    class="absolute inset-0 h-full w-full object-cover"
                />
                <div class="absolute inset-0 bg-slate-950/25" />

                <div
                    class="absolute top-10 left-12 flex items-center gap-3 text-white"
                >
                    <img
                        :src="logo"
                        alt="School logo"
                        class="h-11 w-11 rounded-full bg-white/90 p-1"
                    />
                    <span class="text-3xl font-black tracking-tight"
                        >PCSHS</span
                    >
                </div>

                <div
                    class="absolute right-0 bottom-8 left-0 flex justify-center gap-2"
                >
                    <span class="h-3 w-6 rounded-full bg-sky-500" />
                    <span class="h-3 w-3 rounded-full bg-white" />
                    <span class="h-3 w-3 rounded-full bg-white" />
                </div>
            </section>

            <section class="flex items-center justify-center px-6 py-10">
                <div class="w-full max-w-md">
                    <header>
                        <h1 class="text-3xl font-black text-slate-900">
                            Login
                        </h1>
                        <p class="mt-2 text-sm font-semibold text-slate-500">
                            Please select your Profile
                        </p>
                    </header>

                    <div
                        v-if="!useDifferentAccount && selectedProfile"
                        class="mt-9"
                    >
                        <p
                            class="mb-3 text-xs font-black tracking-wide text-slate-400 uppercase"
                        >
                            Account Ready {{ profiles.length }}
                        </p>
                        <button
                            type="button"
                            class="flex w-full items-center justify-between rounded-md border border-slate-200 bg-white p-3 text-left shadow-sm transition hover:border-sky-300"
                            @click="selectProfile(selectedIndex)"
                        >
                            <span class="flex min-w-0 items-center gap-3">
                                <span
                                    class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-slate-200 text-sm font-black text-slate-700"
                                >
                                    {{ selectedProfile.initials }}
                                </span>
                                <span class="min-w-0">
                                    <span
                                        class="block truncate text-sm font-bold text-slate-900"
                                    >
                                        Welcome back, {{ selectedProfile.name }}
                                    </span>
                                    <span
                                        class="block truncate text-xs font-semibold text-slate-400"
                                    >
                                        {{ selectedProfile.role }}
                                    </span>
                                </span>
                            </span>
                            <span
                                class="text-xs font-black text-red-500"
                                @click.stop="removeProfile(selectedIndex)"
                            >
                                Remove
                            </span>
                        </button>

                        <div class="mt-4 flex justify-center gap-2">
                            <button
                                v-for="(_, index) in profiles"
                                :key="index"
                                type="button"
                                class="h-3 rounded-full transition"
                                :class="
                                    selectedIndex === index
                                        ? 'w-7 bg-blue-600'
                                        : 'w-3 bg-slate-300'
                                "
                                :aria-label="`Select ready account ${index + 1}`"
                                @click="selectProfile(index)"
                            />
                        </div>

                        <label
                            class="mt-5 block text-sm font-bold text-slate-700"
                        >
                            Password
                            <input
                                v-model="form.password"
                                type="password"
                                class="mt-2 h-12 w-full rounded-md border border-slate-300 px-3 text-sm outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-100"
                                required
                                autocomplete="current-password"
                            />
                        </label>
                    </div>

                    <form v-else class="mt-9 space-y-4" @submit.prevent="login">
                        <label class="block text-sm font-bold text-slate-700">
                            Email
                            <input
                                v-model="form.email"
                                type="email"
                                class="mt-2 h-12 w-full rounded-md border border-slate-300 px-3 text-sm outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-100"
                                required
                                autocomplete="email"
                            />
                        </label>
                        <label class="block text-sm font-bold text-slate-700">
                            Password
                            <input
                                v-model="form.password"
                                type="password"
                                class="mt-2 h-12 w-full rounded-md border border-slate-300 px-3 text-sm outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-100"
                                required
                                autocomplete="current-password"
                            />
                        </label>
                    </form>

                    <p class="mt-5 text-sm font-semibold text-slate-500">
                        {{ helperText }}
                    </p>
                    <p
                        v-if="form.errors.email"
                        class="mt-3 rounded-md bg-red-50 px-3 py-2 text-sm font-semibold text-red-600"
                    >
                        {{ form.errors.email }}
                    </p>

                    <div class="mt-10 flex items-center justify-between gap-4">
                        <button
                            type="button"
                            class="text-sm font-semibold text-slate-500 hover:text-sky-600"
                            @click="
                                useDifferentAccount && profiles.length > 0
                                    ? selectProfile(selectedIndex)
                                    : showDifferentAccount()
                            "
                        >
                            {{
                                useDifferentAccount && profiles.length > 0
                                    ? 'Back to saved profiles'
                                    : 'Login to a different account'
                            }}
                        </button>
                        <button
                            type="button"
                            :disabled="form.processing"
                            class="inline-flex h-12 items-center gap-3 rounded-md bg-blue-600 px-8 text-sm font-bold text-white shadow-sm transition hover:bg-blue-700 disabled:opacity-60"
                            @click="login"
                        >
                            {{ form.processing ? 'Logging in...' : 'Login' }}
                            <span>&gt;</span>
                        </button>
                    </div>

                    <div class="mt-8 border-t border-slate-300 pt-5">
                        <p class="text-xs font-semibold text-slate-400">
                            This page is only for Student and Parent accounts.
                        </p>
                    </div>
                </div>
            </section>
        </main>
    </div>
</template>
