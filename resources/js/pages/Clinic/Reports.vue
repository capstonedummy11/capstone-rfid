<script setup>
import { router } from '@inertiajs/vue3';
import { Activity, Download, FileBarChart, HeartPulse } from 'lucide-vue-next';
import { reactive } from 'vue';

const props = defineProps({
    filters: { type: Object, default: () => ({}) },
    summary: { type: Object, default: () => ({}) },
    alertsByType: { type: Array, default: () => [] },
    alertsByStatus: { type: Array, default: () => [] },
    caseBreakdown: { type: Array, default: () => [] },
    caseTrends: { type: Array, default: () => [] },
    recentCases: { type: Array, default: () => [] },
});

const form = reactive({
    date_from: props.filters.date_from || '',
    date_to: props.filters.date_to || '',
    status: props.filters.status || '',
    case_type: props.filters.case_type || '',
});

const applyFilters = () => {
    router.get(route('clinic.reports'), form, {
        preserveState: true,
        preserveScroll: true,
    });
};

const resetFilters = () => {
    form.date_from = '';
    form.date_to = '';
    form.status = '';
    form.case_type = '';
    applyFilters();
};

const exportUrl = () => route('clinic.reports.export', form);
</script>

<template>
    <div class="mx-auto max-w-7xl px-4 py-6">
        <section
            class="mb-5 flex flex-col gap-3 rounded-md bg-white p-5 shadow-sm lg:flex-row lg:items-center lg:justify-between"
        >
            <div>
                <h1 class="text-2xl font-black text-slate-950">
                    Clinic Reports
                </h1>
                <p class="text-sm text-slate-500">
                    Filter clinic activity, review trends, and export case
                    records.
                </p>
            </div>
            <a
                :href="exportUrl()"
                class="inline-flex items-center justify-center gap-2 rounded-md bg-brand px-4 py-2 text-sm font-bold text-white"
            >
                <Download class="h-4 w-4" />
                Export CSV
            </a>
        </section>

        <section class="mb-5 rounded-md bg-white p-5 shadow-sm">
            <div class="grid gap-3 md:grid-cols-5">
                <input
                    v-model="form.date_from"
                    type="date"
                    class="rounded-md border border-slate-300 px-3 py-2 text-sm"
                />
                <input
                    v-model="form.date_to"
                    type="date"
                    class="rounded-md border border-slate-300 px-3 py-2 text-sm"
                />
                <select
                    v-model="form.status"
                    class="rounded-md border border-slate-300 px-3 py-2 text-sm"
                >
                    <option value="">All status</option>
                    <option value="open">Open</option>
                    <option value="monitoring">Monitoring</option>
                    <option value="resolved">Resolved</option>
                    <option value="referred">Referred</option>
                    <option value="acknowledged">Acknowledged</option>
                    <option value="cancelled">Cancelled</option>
                </select>
                <input
                    v-model="form.case_type"
                    placeholder="Case type"
                    class="rounded-md border border-slate-300 px-3 py-2 text-sm"
                />
                <div class="flex gap-2">
                    <button
                        class="flex-1 rounded-md bg-slate-900 px-3 py-2 text-sm font-bold text-white"
                        @click="applyFilters"
                    >
                        Apply
                    </button>
                    <button
                        class="rounded-md border border-slate-300 px-3 py-2 text-sm font-bold text-slate-600"
                        @click="resetFilters"
                    >
                        Reset
                    </button>
                </div>
            </div>
        </section>

        <section class="mb-5 grid gap-3 md:grid-cols-4">
            <article class="rounded-md bg-white p-4 shadow-sm">
                <FileBarChart class="h-5 w-5 text-brand" />
                <p class="mt-3 text-3xl font-black">
                    {{ summary.alerts || 0 }}
                </p>
                <p class="text-sm font-bold text-slate-600">Alerts</p>
            </article>
            <article class="rounded-md bg-white p-4 shadow-sm">
                <HeartPulse class="h-5 w-5 text-rose-500" />
                <p class="mt-3 text-3xl font-black">{{ summary.cases || 0 }}</p>
                <p class="text-sm font-bold text-slate-600">Cases</p>
            </article>
            <article class="rounded-md bg-white p-4 shadow-sm">
                <Activity class="h-5 w-5 text-amber-500" />
                <p class="mt-3 text-3xl font-black">
                    {{ summary.openCases || 0 }}
                </p>
                <p class="text-sm font-bold text-slate-600">
                    Open / Monitoring
                </p>
            </article>
            <article class="rounded-md bg-white p-4 shadow-sm">
                <Activity class="h-5 w-5 text-emerald-500" />
                <p class="mt-3 text-3xl font-black">
                    {{ summary.averageResponseMinutes ?? '-' }}
                </p>
                <p class="text-sm font-bold text-slate-600">
                    Avg Response Minutes
                </p>
            </article>
        </section>

        <section class="grid gap-5 lg:grid-cols-2">
            <div class="rounded-md bg-white p-5 shadow-sm">
                <h2 class="mb-4 font-black text-slate-900">Alerts by Type</h2>
                <div
                    v-for="row in alertsByType"
                    :key="row.name"
                    class="flex justify-between border-b border-slate-100 py-2 text-sm"
                >
                    <span>{{ row.name }}</span>
                    <strong>{{ row.total }}</strong>
                </div>
                <p
                    v-if="alertsByType.length === 0"
                    class="text-sm text-slate-400"
                >
                    No alert type data.
                </p>
            </div>
            <div class="rounded-md bg-white p-5 shadow-sm">
                <h2 class="mb-4 font-black text-slate-900">Alerts by Status</h2>
                <div
                    v-for="row in alertsByStatus"
                    :key="row.status"
                    class="flex justify-between border-b border-slate-100 py-2 text-sm"
                >
                    <span>{{ row.status }}</span>
                    <strong>{{ row.total }}</strong>
                </div>
                <p
                    v-if="alertsByStatus.length === 0"
                    class="text-sm text-slate-400"
                >
                    No alert status data.
                </p>
            </div>
            <div class="rounded-md bg-white p-5 shadow-sm">
                <h2 class="mb-4 font-black text-slate-900">Case Breakdown</h2>
                <div
                    v-for="row in caseBreakdown"
                    :key="row.name"
                    class="flex justify-between border-b border-slate-100 py-2 text-sm"
                >
                    <span>{{ row.name }}</span>
                    <strong>{{ row.total }}</strong>
                </div>
                <p
                    v-if="caseBreakdown.length === 0"
                    class="text-sm text-slate-400"
                >
                    No case breakdown data.
                </p>
            </div>
            <div class="rounded-md bg-white p-5 shadow-sm">
                <h2 class="mb-4 font-black text-slate-900">Case Trend</h2>
                <div
                    v-for="row in caseTrends"
                    :key="row.date"
                    class="flex items-center gap-3 border-b border-slate-100 py-2 text-sm"
                >
                    <span class="w-28 text-slate-500">{{ row.date }}</span>
                    <div class="h-2 flex-1 rounded-full bg-slate-100">
                        <div
                            class="h-2 rounded-full bg-brand"
                            :style="{
                                width: `${Math.min(100, Number(row.total) * 12)}%`,
                            }"
                        ></div>
                    </div>
                    <strong>{{ row.total }}</strong>
                </div>
                <p
                    v-if="caseTrends.length === 0"
                    class="text-sm text-slate-400"
                >
                    No case trend data.
                </p>
            </div>
        </section>

        <section class="mt-5 rounded-md bg-white p-5 shadow-sm">
            <h2 class="mb-4 font-black text-slate-900">Recent Cases</h2>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[860px] text-left text-sm">
                    <thead class="bg-slate-50 text-xs text-slate-500 uppercase">
                        <tr>
                            <th class="px-3 py-3">Patient</th>
                            <th class="px-3 py-3">Case</th>
                            <th class="px-3 py-3">Status</th>
                            <th class="px-3 py-3">Occurred</th>
                            <th class="px-3 py-3">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr
                            v-for="clinicCase in recentCases"
                            :key="clinicCase.id"
                        >
                            <td class="px-3 py-3 font-bold text-slate-900">
                                {{ clinicCase.patient_name }}
                            </td>
                            <td class="px-3 py-3 text-slate-600">
                                {{ clinicCase.case_type || '-' }}
                            </td>
                            <td class="px-3 py-3 text-slate-600">
                                {{ clinicCase.status }}
                            </td>
                            <td class="px-3 py-3 text-slate-500">
                                {{ clinicCase.occurred_at || '-' }}
                            </td>
                            <td class="px-3 py-3 text-slate-500">
                                {{ clinicCase.action_taken || '-' }}
                            </td>
                        </tr>
                        <tr v-if="recentCases.length === 0">
                            <td
                                colspan="5"
                                class="px-3 py-10 text-center text-slate-400"
                            >
                                No cases match the current filters.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</template>
