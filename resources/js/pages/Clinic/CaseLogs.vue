<template>
    <div class="mx-auto max-w-7xl px-4 py-6">
        <section class="mb-6 rounded-lg bg-white p-6 shadow-lg">
            <h1 class="text-3xl font-bold">Case Logs</h1>
            <p class="text-sm text-slate-500">Clinic case records connected to emergency notifications.</p>
        </section>
        <section class="rounded-lg bg-white p-6 shadow-lg">
            <table class="w-full border-collapse text-sm">
                <thead><tr class="bg-slate-50"><th class="border p-3 text-left">Patient</th><th class="border p-3 text-left">Case</th><th class="border p-3 text-left">Alert</th><th class="border p-3 text-left">Status</th><th class="border p-3 text-left">Occurred</th><th class="border p-3 text-left">Notes</th></tr></thead>
                <tbody><tr v-for="row in cases" :key="row.id"><td class="border p-3">{{ row.patient_name || 'N/A' }}</td><td class="border p-3">{{ row.case_type || 'N/A' }}</td><td class="border p-3">{{ row.alert || 'N/A' }}</td><td class="border p-3">{{ row.status }}</td><td class="border p-3">{{ row.occurred_at || 'N/A' }}</td><td class="border p-3">{{ row.notes || '' }}</td></tr></tbody>
            </table>
            <div v-if="cases.length === 0" class="py-8 text-center text-slate-400">No case logs yet.</div>
        </section>
    </div>
</template>
<script setup>
defineProps({ cases: { type: Array, default: () => [] } });
</script>
