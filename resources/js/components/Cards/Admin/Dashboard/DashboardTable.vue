<script setup>
defineProps({
    table_header: {
        type: String,
        required: true,
    },
    rows: {
        type: Array,
        default: () => [],
    },
});
</script>

<template>
    <div class="h-[400px] w-full rounded-[10px] bg-white p-5">
        <header class="border-b-2">
            <h1 class="pb-5 text-[20px] font-medium">{{ table_header }}</h1>
        </header>

        <!-- Column Headers -->
        <div class="mt-4 flex px-4 text-sm text-gray-400">
            <span class="w-[120px]">Time</span>
            <span class="w-[150px]">Room</span>
            <span class="flex-1"></span>
            <span class="w-[80px] text-right">Active ▾</span>
        </div>

        <!-- Rows -->
        <div class="mt-2 flex flex-col gap-3">
            <div
                v-for="(row, index) in rows"
                :key="index"
                class="flex items-center rounded-[10px] border p-4"
            >
                <span class="w-[120px] font-medium">{{ row.time }}</span>
                <span class="w-[150px] font-medium">{{ row.room }}</span>
                <span class="flex-1 text-center text-gray-400">
                    {{ row.details ?? '---' }}
                </span>
                <span
                    class="h-3 w-3 rounded-full"
                    :class="row.active ? 'bg-green-500' : 'bg-red-400'"
                />
            </div>
        </div>
    </div>
</template>
