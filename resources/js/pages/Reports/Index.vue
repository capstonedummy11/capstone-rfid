<script setup>
import { router } from '@inertiajs/vue3';
import { BarChart3, Download, RefreshCcw } from 'lucide-vue-next';
import { computed, reactive } from 'vue';

const props = defineProps({
    role: { type: String, default: '' },
    title: { type: String, default: 'Reports' },
    filters: { type: Object, default: () => ({}) },
    summaryCards: { type: Array, default: () => [] },
    charts: { type: Array, default: () => [] },
    tableRows: { type: Array, default: () => [] },
    exportUrl: { type: String, default: '' },
    academicYears: { type: Array, default: () => [] },
    selectedAcademicYear: { type: Object, default: null },
    allowAllYears: { type: Boolean, default: false },
});

const form = reactive({
    academic_year_id:
        props.filters.academic_year_id === 'all'
            ? 'all'
            : props.filters.academic_year_id || '',
    date_from: props.filters.date_from || '',
    date_to: props.filters.date_to || '',
});

const pageDescription = computed(() => {
    const labels = {
        admin: 'Monitor campus-wide users, attendance, borrowing, inventory, and clinic activity.',
        clinic: 'Track clinic cases, emergency alerts, patient history, and response workload.',
        registrar:
            'Review student population, sections, strands, and enrollment activity.',
        instructor:
            'Review your assigned schedules, attendance records, and online class activity.',
        student:
            'Review your attendance, online class participation, excuse letters, and messages.',
        parent: 'Review linked student attendance, online class participation, and excuse letters.',
    };

    return (
        labels[props.role] ||
        'Review report summaries, charts, and downloadable records.'
    );
});

const totalFor = (chart) =>
    chart.data.reduce((sum, datum) => sum + Number(datum.value || 0), 0);

const chartType = (chart) => chart.type || 'bar';

const widthFor = (datum, chart) => {
    const max = Math.max(
        ...chart.data.map((item) => Number(item.value || 0)),
        0,
    );

    if (!max) return '0%';

    return `${Math.max(6, Math.round((Number(datum.value || 0) / max) * 100))}%`;
};

const percentFor = (datum, chart) => {
    const total = totalFor(chart);
    if (!total) return 0;

    return Math.round((Number(datum.value || 0) / total) * 100);
};

const chartColors = [
    '#2563eb',
    '#16a34a',
    '#dc2626',
    '#9333ea',
    '#ca8a04',
    '#0891b2',
    '#db2777',
    '#475569',
];

const donutStyle = (chart) => {
    const total = totalFor(chart);
    if (!total) {
        return { background: '#e2e8f0' };
    }

    let current = 0;
    const slices = chart.data.map((datum, index) => {
        const value = Number(datum.value || 0);
        const start = current;
        const end = current + (value / total) * 100;
        current = end;

        return `${chartColors[index % chartColors.length]} ${start}% ${end}%`;
    });

    return { background: `conic-gradient(${slices.join(', ')})` };
};

const trendHeight = (datum, chart) => {
    const max = Math.max(
        ...chart.data.map((item) => Number(item.value || 0)),
        0,
    );
    if (!max) return '8%';

    return `${Math.max(8, Math.round((Number(datum.value || 0) / max) * 100))}%`;
};

const applyFilters = () => {
    router.get(route('reports.index'), form, {
        preserveState: true,
        preserveScroll: true,
    });
};

const resetFilters = () => {
    form.academic_year_id = props.academicYears.find((year) => year.status === 'active')?.academic_year_id || (props.allowAllYears ? 'all' : '');
    form.date_from = '';
    form.date_to = '';
    applyFilters();
};
</script>

<template>
    <div class="min-h-screen bg-slate-100 px-4 py-6 text-slate-950">
        <div class="mx-auto max-w-7xl space-y-5">
            <section
                class="flex flex-col gap-4 rounded-md bg-white p-5 shadow-sm lg:flex-row lg:items-center lg:justify-between"
            >
                <div>
                    <p class="text-xs font-bold text-brand uppercase">
                        {{ role }} reporting
                    </p>
                    <h1 class="mt-1 text-2xl font-black">
                        {{ title }}
                    </h1>
                    <p class="mt-1 max-w-3xl text-sm text-slate-600">
                        {{ pageDescription }}
                    </p>
                </div>
                <a
                    :href="exportUrl"
                    class="inline-flex items-center justify-center gap-2 rounded-md bg-brand px-4 py-2 text-sm font-bold text-white shadow-sm transition hover:bg-brand/90"
                >
                    <Download class="h-4 w-4" />
                    Download CSV
                </a>
            </section>

            <section class="rounded-md bg-white p-5 shadow-sm">
                <div class="grid gap-3 md:grid-cols-[1fr_1fr_1fr_auto_auto]">
                    <label class="grid gap-1 text-sm font-semibold text-slate-700">
                        Academic Year
                        <select v-model="form.academic_year_id" class="rounded-md border border-slate-300 px-3 py-2 text-sm">
                            <option v-if="allowAllYears" value="all">All years</option>
                            <option v-for="year in academicYears" :key="year.academic_year_id" :value="year.academic_year_id">
                                {{ year.name }} ({{ year.status }})
                            </option>
                        </select>
                    </label>
                    <label
                        class="grid gap-1 text-sm font-semibold text-slate-700"
                    >
                        From
                        <input
                            v-model="form.date_from"
                            type="date"
                            class="rounded-md border border-slate-300 px-3 py-2 text-sm"
                        />
                    </label>
                    <label
                        class="grid gap-1 text-sm font-semibold text-slate-700"
                    >
                        To
                        <input
                            v-model="form.date_to"
                            type="date"
                            class="rounded-md border border-slate-300 px-3 py-2 text-sm"
                        />
                    </label>
                    <button
                        type="button"
                        class="self-end rounded-md bg-slate-950 px-4 py-2 text-sm font-bold text-white"
                        @click="applyFilters"
                    >
                        Apply
                    </button>
                    <button
                        type="button"
                        class="inline-flex items-center justify-center gap-2 self-end rounded-md border border-slate-300 px-4 py-2 text-sm font-bold text-slate-700"
                        @click="resetFilters"
                    >
                        <RefreshCcw class="h-4 w-4" />
                        Reset
                    </button>
                </div>
            </section>

            <section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <article
                    v-for="card in summaryCards"
                    :key="card.label"
                    class="rounded-md bg-white p-4 shadow-sm"
                >
                    <p class="text-sm font-semibold text-slate-500">
                        {{ card.label }}
                    </p>
                    <p class="mt-2 text-3xl font-black text-slate-950">
                        {{ Number(card.value || 0).toLocaleString() }}
                    </p>
                    <p v-if="card.detail" class="mt-1 text-xs text-slate-500">
                        {{ card.detail }}
                    </p>
                </article>
            </section>

            <section class="grid gap-4 lg:grid-cols-2">
                <article
                    v-for="chart in charts"
                    :key="chart.title"
                    class="rounded-md bg-white p-5 shadow-sm"
                >
                    <div class="mb-4 flex items-center justify-between gap-3">
                        <div class="flex min-w-0 items-center gap-2">
                            <BarChart3 class="h-5 w-5 text-brand" />
                            <h2 class="truncate text-base font-black">
                                {{ chart.title }}
                            </h2>
                        </div>
                        <span
                            class="rounded-md bg-slate-100 px-2 py-1 text-xs font-bold text-slate-600"
                        >
                            {{ totalFor(chart).toLocaleString() }}
                        </span>
                    </div>

                    <div
                        v-if="chart.data.length && chartType(chart) === 'donut'"
                        class="grid gap-4 sm:grid-cols-[140px_1fr]"
                    >
                        <div class="flex items-center justify-center">
                            <div
                                class="relative h-32 w-32 rounded-full"
                                :style="donutStyle(chart)"
                            >
                                <div
                                    class="absolute inset-5 flex items-center justify-center rounded-full bg-white text-lg font-black text-slate-950"
                                >
                                    {{ totalFor(chart).toLocaleString() }}
                                </div>
                            </div>
                        </div>
                        <div class="space-y-2">
                            <div
                                v-for="(datum, index) in chart.data"
                                :key="`${chart.title}-${datum.label}`"
                                class="flex items-center justify-between gap-3 rounded-md bg-slate-50 px-3 py-2 text-sm"
                            >
                                <span class="flex min-w-0 items-center gap-2">
                                    <span
                                        class="h-3 w-3 shrink-0 rounded-sm"
                                        :style="{
                                            backgroundColor:
                                                chartColors[
                                                    index % chartColors.length
                                                ],
                                        }"
                                    />
                                    <span class="truncate font-semibold">
                                        {{ datum.label }}
                                    </span>
                                </span>
                                <span class="font-black">
                                    {{ percentFor(datum, chart) }}%
                                </span>
                            </div>
                        </div>
                    </div>

                    <div
                        v-else-if="
                            chart.data.length && chartType(chart) === 'trend'
                        "
                        class="flex h-48 items-end gap-2 border-b border-slate-200 pt-4"
                    >
                        <div
                            v-for="datum in chart.data"
                            :key="`${chart.title}-${datum.label}`"
                            class="flex min-w-0 flex-1 flex-col items-center justify-end gap-2"
                        >
                            <div
                                class="w-full rounded-t-md bg-brand"
                                :style="{ height: trendHeight(datum, chart) }"
                                :title="`${datum.label}: ${Number(datum.value || 0).toLocaleString()}`"
                            />
                            <span
                                class="max-w-full truncate text-[10px] font-semibold text-slate-500"
                            >
                                {{ datum.label }}
                            </span>
                        </div>
                    </div>

                    <div
                        v-else-if="
                            chart.data.length && chartType(chart) === 'list'
                        "
                        class="divide-y divide-slate-100 rounded-md border border-slate-100"
                    >
                        <div
                            v-for="datum in chart.data"
                            :key="`${chart.title}-${datum.label}`"
                            class="flex items-center justify-between gap-3 px-3 py-3 text-sm"
                        >
                            <span class="min-w-0 truncate font-semibold">
                                {{ datum.label }}
                            </span>
                            <span class="font-black text-slate-950">
                                {{ Number(datum.value || 0).toLocaleString() }}
                            </span>
                        </div>
                    </div>

                    <div v-else-if="chart.data.length" class="space-y-3">
                        <div
                            v-for="datum in chart.data"
                            :key="`${chart.title}-${datum.label}`"
                            class="grid gap-1"
                        >
                            <div
                                class="flex items-center justify-between gap-3 text-sm"
                            >
                                <span
                                    class="min-w-0 truncate font-semibold text-slate-700"
                                >
                                    {{ datum.label }}
                                </span>
                                <span class="font-black text-slate-950">
                                    {{
                                        Number(
                                            datum.value || 0,
                                        ).toLocaleString()
                                    }}
                                </span>
                            </div>
                            <div
                                class="h-3 overflow-hidden rounded-md bg-slate-100"
                            >
                                <div
                                    class="h-full rounded-md bg-brand"
                                    :style="{ width: widthFor(datum, chart) }"
                                />
                            </div>
                        </div>
                    </div>
                    <div
                        v-else
                        class="rounded-md border border-dashed border-slate-300 p-6 text-center text-sm font-semibold text-slate-500"
                    >
                        No report data yet
                    </div>
                </article>
            </section>

            <section class="overflow-hidden rounded-md bg-white shadow-sm">
                <div class="border-b border-slate-200 p-5">
                    <h2 class="text-base font-black">Report Details</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead
                            class="bg-slate-50 text-left text-xs font-black text-slate-500 uppercase"
                        >
                            <tr>
                                <th class="px-4 py-3">Category</th>
                                <th class="px-4 py-3">Metric</th>
                                <th class="px-4 py-3">Value</th>
                                <th class="px-4 py-3">Group</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr
                                v-for="row in tableRows"
                                :key="`${row.category}-${row.metric}`"
                            >
                                <td
                                    class="px-4 py-3 font-semibold text-slate-700"
                                >
                                    {{ row.category }}
                                </td>
                                <td class="px-4 py-3 text-slate-600">
                                    {{ row.metric }}
                                </td>
                                <td class="px-4 py-3 font-black text-slate-950">
                                    {{
                                        Number(row.value || 0).toLocaleString()
                                    }}
                                </td>
                                <td class="px-4 py-3 text-slate-500">
                                    {{ row.group }}
                                </td>
                            </tr>
                            <tr v-if="!tableRows.length">
                                <td
                                    class="px-4 py-8 text-center text-slate-500"
                                    colspan="4"
                                >
                                    No report rows available.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>
</template>
