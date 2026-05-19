<template>
    <div class="mx-auto max-w-7xl px-4 py-6">
        <section class="mb-6 rounded-lg bg-white p-6 shadow-lg">
            <h1 class="text-3xl font-bold">Clinic Reports</h1>
            <p class="text-sm text-slate-500">Emergency counts by type and status.</p>
        </section>
        <section class="grid gap-6 md:grid-cols-2">
            <div class="rounded-lg bg-white p-6 shadow-lg">
                <h2 class="mb-4 text-xl font-semibold">Alerts by Type</h2>
                <div v-for="row in alertsByType" :key="row.name" class="flex justify-between border-b py-2"><span>{{ row.name }}</span><strong>{{ row.total }}</strong></div>
            </div>
            <div class="rounded-lg bg-white p-6 shadow-lg">
                <h2 class="mb-4 text-xl font-semibold">Alerts by Status</h2>
                <div v-for="row in alertsByStatus" :key="row.status" class="flex justify-between border-b py-2"><span>{{ row.status }}</span><strong>{{ row.total }}</strong></div>
            </div>
        </section>
    </div>
</template>
<script setup>
defineProps({
    alertsByType: { type: Array, default: () => [] },
    alertsByStatus: { type: Array, default: () => [] },
});
</script>
