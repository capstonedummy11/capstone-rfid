<template>
    <div class="mx-auto max-w-7xl px-4 py-6">
        <section class="mb-6 rounded-lg bg-white p-6 shadow-lg">
            <h1 class="text-3xl font-bold">Patient History</h1>
            <p class="text-sm text-slate-500">Historical clinic records for students, instructors, or visitors.</p>
        </section>
        <section class="rounded-lg bg-white p-6 shadow-lg">
            <table class="w-full border-collapse text-sm">
                <thead><tr class="bg-slate-50"><th class="border p-3 text-left">Patient</th><th class="border p-3 text-left">Type</th><th class="border p-3 text-left">Summary</th><th class="border p-3 text-left">Occurred</th><th class="border p-3 text-left">Notes</th></tr></thead>
                <tbody><tr v-for="row in histories" :key="row.id"><td class="border p-3">{{ row.patient_name || 'N/A' }}</td><td class="border p-3">{{ row.patient_type || 'N/A' }}</td><td class="border p-3">{{ row.summary }}</td><td class="border p-3">{{ row.occurred_at || 'N/A' }}</td><td class="border p-3">{{ row.notes || '' }}</td></tr></tbody>
            </table>
            <div v-if="histories.length === 0" class="py-8 text-center text-slate-400">No patient history yet.</div>
        </section>
    </div>
</template>
<script setup>
defineProps({ histories: { type: Array, default: () => [] } });
</script>
