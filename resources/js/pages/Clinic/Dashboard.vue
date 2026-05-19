<template>
    <div class="w-full">
        <div class="mx-auto max-w-7xl px-4 py-6">
            <section class="mb-6 rounded-lg bg-white p-6 shadow-lg">
                <h1 class="text-3xl font-bold">Clinic Dashboard</h1>
                <p class="text-sm text-slate-500">
                    Emergency notifications, clinic counts, and editable emergency messages.
                </p>
            </section>

            <section class="mb-6 grid gap-4 md:grid-cols-4">
                <div v-for="card in countCards" :key="card.label" class="rounded-lg bg-white p-5 shadow">
                    <div class="text-xs font-semibold uppercase text-slate-400">{{ card.label }}</div>
                    <div class="mt-2 text-3xl font-bold text-slate-900">{{ card.value }}</div>
                </div>
            </section>

            <section class="grid gap-6 lg:grid-cols-[1.2fr_0.8fr]">
                <div class="rounded-lg bg-white p-6 shadow-lg">
                    <h2 class="mb-4 text-xl font-semibold">Emergency Notifications</h2>
                    <div class="space-y-3">
                        <div v-for="alert in alerts" :key="alert.emergency_alert_id" class="rounded-lg border border-red-100 bg-red-50 p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <div class="font-semibold text-red-800">{{ alert.type }} · {{ alert.room || 'No room' }}</div>
                                    <div class="mt-1 text-sm text-red-700">{{ alert.message }}</div>
                                    <div class="mt-2 text-xs text-slate-500">By {{ alert.triggered_by_name || 'Unknown' }} · {{ alert.created_at }}</div>
                                </div>
                                <form @submit.prevent="updateAlert(alert.emergency_alert_id, alert.status)" class="flex gap-2">
                                    <select v-model="alert.status" class="rounded-md border border-slate-300 px-2 py-1 text-xs">
                                        <option value="open">Open</option>
                                        <option value="acknowledged">Acknowledged</option>
                                        <option value="resolved">Resolved</option>
                                        <option value="cancelled">Cancelled</option>
                                    </select>
                                    <button class="rounded-md bg-slate-900 px-3 py-1 text-xs font-semibold text-white">Save</button>
                                </form>
                            </div>
                        </div>
                        <div v-if="alerts.length === 0" class="rounded-lg border border-dashed border-slate-200 p-6 text-center text-slate-400">
                            No emergency notifications yet.
                        </div>
                    </div>
                </div>

                <div class="rounded-lg bg-white p-6 shadow-lg">
                    <h2 class="mb-4 text-xl font-semibold">Emergency Types</h2>
                    <form @submit.prevent="addType" class="mb-4 space-y-3 rounded-lg border border-slate-200 p-3">
                        <input v-model="typeForm.name" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm" placeholder="Emergency name" required />
                        <select v-model="typeForm.category" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                            <option value="clinic">Clinic</option>
                            <option value="disaster">Disaster</option>
                            <option value="general">General</option>
                        </select>
                        <textarea v-model="typeForm.default_message" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm" rows="3" placeholder="Emergency text message"></textarea>
                        <button class="w-full rounded-md bg-blue-600 px-3 py-2 text-sm font-semibold text-white">Add Emergency Type</button>
                    </form>

                    <div class="space-y-2">
                        <div v-for="type in emergencyTypes" :key="type.emergency_type_id" class="rounded-lg border border-slate-200 p-3">
                            <div class="font-semibold">{{ type.name }}</div>
                            <div class="text-xs uppercase text-slate-400">{{ type.category }}</div>
                            <div class="mt-1 text-sm text-slate-600">{{ type.default_message }}</div>
                            <button @click="deleteType(type.emergency_type_id)" class="mt-2 rounded-md border border-red-200 px-3 py-1 text-xs font-semibold text-red-600">Delete</button>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { router, useForm } from '@inertiajs/vue3';

const props = defineProps({
    counts: { type: Object, default: () => ({}) },
    alerts: { type: Array, default: () => [] },
    emergencyTypes: { type: Array, default: () => [] },
});

const countCards = computed(() => [
    { label: 'Open Alerts', value: props.counts.openAlerts ?? 0 },
    { label: 'Today Alerts', value: props.counts.todayAlerts ?? 0 },
    { label: 'Case Logs', value: props.counts.clinicCases ?? 0 },
    { label: 'Patient History', value: props.counts.patientHistories ?? 0 },
]);

const typeForm = useForm({
    name: '',
    category: 'clinic',
    default_message: '',
    is_active: true,
});

const addType = () => {
    typeForm.post(route('clinic.emergency-types.store'), {
        preserveScroll: true,
        onSuccess: () => typeForm.reset(),
    });
};

const deleteType = (id) => {
    if (!confirm('Delete this emergency type?')) return;
    router.delete(route('clinic.emergency-types.destroy', { id }), { preserveScroll: true });
};

const updateAlert = (id, status) => {
    router.put(route('clinic.emergency-alerts.update', { id }), { status }, { preserveScroll: true });
};
</script>
