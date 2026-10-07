<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

const page = usePage();
const version = computed(() => String(page.props.appVersion || '1.0'));
</script>

<template>
    <footer class="relative h-0 w-full" aria-label="Application information">
        <div
            class="pointer-events-none absolute right-3 bottom-3 rounded-full border border-slate-200/80 bg-white/95 px-2.5 py-1 text-[11px] font-semibold text-slate-500 shadow-md"
            :aria-label="`Application version ${version}`"
        >
            v{{ version }}
        </div>
    </footer>
</template>
