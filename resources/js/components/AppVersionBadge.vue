<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

const page = usePage();
const version = computed(() => String(page.props.appVersion || '1.0'));
</script>

<template>
    <footer class="flex w-full justify-end px-3 py-2">
        <div
            class="pointer-events-none rounded-full border border-slate-200/80 bg-white/90 px-2.5 py-1 text-[11px] font-semibold text-slate-500 shadow-sm"
            :aria-label="`Application version ${version}`"
        >
            v{{ version }}
        </div>
    </footer>
</template>
