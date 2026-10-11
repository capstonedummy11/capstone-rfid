<script setup lang="ts">
import { onMounted, ref } from 'vue';

defineProps<{
    secondsRemaining: number;
    busy?: boolean;
    error?: string;
}>();

const emit = defineEmits<{
    stay: [];
    logout: [];
}>();

const stayButton = ref<HTMLButtonElement | null>(null);
const logoutButton = ref<HTMLButtonElement | null>(null);

const trapFocus = (event: KeyboardEvent) => {
    const first = logoutButton.value;
    const last = stayButton.value;
    if (!first || !last) return;

    if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
    }
};

onMounted(() => stayButton.value?.focus());
</script>

<template>
    <div
        class="fixed inset-0 z-[1000] flex items-center justify-center bg-slate-950/60 p-4 backdrop-blur-sm"
        @keydown.esc.prevent.stop
    >
        <section
            role="dialog"
            aria-modal="true"
            aria-labelledby="idle-timeout-title"
            aria-describedby="idle-timeout-description"
            class="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-6 text-center shadow-2xl"
            @keydown.tab="trapFocus"
        >
            <div
                class="mx-auto flex h-20 w-20 items-center justify-center rounded-full bg-amber-50 text-amber-600"
                aria-hidden="true"
            >
                <svg
                    class="idle-hourglass h-12 w-12"
                    viewBox="0 0 48 48"
                    fill="none"
                    xmlns="http://www.w3.org/2000/svg"
                >
                    <path
                        d="M13 6h22M13 42h22M16 7c0 8 2.8 12.3 8 17-5.2 4.7-8 9-8 17m16-34c0 8-2.8 12.3-8 17 5.2 4.7 8 9 8 17"
                        stroke="currentColor"
                        stroke-width="3"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    />
                    <path
                        d="M19 14h10c-.7 3.2-2.3 5.5-5 8-2.7-2.5-4.3-4.8-5-8Zm0 22c.9-3.5 2.5-5.8 5-8 2.5 2.2 4.1 4.5 5 8H19Z"
                        fill="currentColor"
                        opacity=".75"
                    />
                </svg>
            </div>

            <h2
                id="idle-timeout-title"
                class="mt-5 text-2xl font-bold text-slate-900"
            >
                Are you still there?
            </h2>
            <p
                id="idle-timeout-description"
                class="mt-2 text-sm leading-6 text-slate-600"
            >
                You will be logged out due to inactivity in
                <strong
                    class="whitespace-nowrap text-amber-700"
                    aria-live="assertive"
                    aria-atomic="true"
                    >{{ secondsRemaining }} seconds</strong
                >.
            </p>

            <p
                v-if="error"
                class="mt-4 rounded-lg bg-rose-50 px-3 py-2 text-sm text-rose-700"
                role="alert"
            >
                {{ error }}
            </p>

            <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row">
                <button
                    ref="logoutButton"
                    type="button"
                    class="flex-1 rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 focus:ring-2 focus:ring-slate-400 focus:outline-none"
                    :disabled="busy"
                    @click="emit('logout')"
                >
                    Log out now
                </button>
                <button
                    ref="stayButton"
                    type="button"
                    class="flex-1 rounded-lg bg-brand px-4 py-2.5 text-sm font-semibold text-white hover:brightness-95 focus:ring-2 focus:ring-brand focus:ring-offset-2 focus:outline-none disabled:opacity-60"
                    :disabled="busy"
                    @click="emit('stay')"
                >
                    {{ busy ? 'Keeping session active…' : 'Stay logged in' }}
                </button>
            </div>
        </section>
    </div>
</template>

<style scoped>
.idle-hourglass {
    transform-origin: center;
    animation: idle-hourglass-flip 1.8s ease-in-out infinite;
}

@keyframes idle-hourglass-flip {
    0%,
    42% {
        transform: rotate(0deg);
    }
    58%,
    100% {
        transform: rotate(180deg);
    }
}

@media (prefers-reduced-motion: reduce) {
    .idle-hourglass {
        animation-duration: 4s;
    }
}
</style>
