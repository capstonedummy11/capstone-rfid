<script setup>
defineProps({
    show: { type: Boolean, default: false },
    title: { type: String, default: 'Success' },
    message: { type: String, default: '' },
    primaryHref: { type: String, default: '' },
    primaryText: { type: String, default: '' },
    secondaryHref: { type: String, default: '' },
    secondaryText: { type: String, default: '' },
});
</script>

<template>
    <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center bg-black/35 p-4">
        <div class="w-full max-w-xs rounded-xl bg-white p-6 text-center shadow-2xl">
            <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-emerald-50">
                <svg viewBox="0 0 24 24" class="h-10 w-10 text-emerald-500" fill="none" stroke="currentColor" stroke-width="2.5">
                    <path d="M20 6 9 17l-5-5" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
            </div>
            <h2 class="mt-4 text-2xl font-black text-slate-950">{{ title }}</h2>
            <p v-if="message" class="mt-1 text-sm text-slate-500">{{ message }}</p>
            <a
                v-if="primaryHref"
                :href="primaryHref"
                target="_blank"
                class="mt-4 block rounded-md bg-sky-500 px-4 py-2 text-sm font-bold text-white"
            >
                {{ primaryText }}
            </a>
            <a
                v-if="secondaryHref"
                :href="secondaryHref"
                class="mt-2 block rounded-md border border-sky-200 px-4 py-2 text-sm font-bold text-sky-600"
            >
                {{ secondaryText }}
            </a>
        </div>
    </div>
</template>
