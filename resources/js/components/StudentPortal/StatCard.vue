<script setup>
defineProps({
    value: { type: [Number, String], default: 0 },
    label: { type: String, required: true },
    helper: { type: String, default: '' },
    icon: { type: Object, default: null },
});
</script>

<template>
    <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-100">
        <div class="flex items-start justify-between">
            <div>
                <p class="text-3xl font-black">{{ value }}</p>
                <p class="mt-1 text-xs font-bold text-slate-700">{{ label }}</p>
            </div>
            <component :is="icon" v-if="icon" class="h-5 w-5 text-sky-500" />
        </div>
        <p v-if="helper" class="mt-3 text-[10px] font-bold uppercase text-slate-400">{{ helper }}</p>
    </div>
</template>
