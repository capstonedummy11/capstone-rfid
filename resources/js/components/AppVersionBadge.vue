<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

const page = usePage();
const version = computed(() => String(page.props.appVersion || '1.0'));
</script>

<template>
    <div
        class="pointer-events-none fixed right-3 bottom-3 z-50 rounded-full border border-slate-200/80 bg-white/90 px-2.5 py-1 text-[11px] font-semibold text-slate-500 shadow-sm backdrop-blur"
        :aria-label="`Application version ${version}`"
    >
        v{{ version }}
    </div>
</template>
