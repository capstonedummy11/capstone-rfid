<script setup>
import { computed } from 'vue';
import { CheckCircle2, IdCard, ScanFace, Users } from 'lucide-vue-next';

const props = defineProps({
    stats: {
        type: Object,
        default: () => ({
            total: 0,
            students: 0,
            faculty: 0,
            missing_face: 0,
            missing_rfid: 0,
            complete: 0,
        }),
    },
    charts: {
        type: Object,
        default: () => ({
            dailyEnrollment: [],
        }),
    },
    recentLogs: { type: Array, default: () => [] },
});

const completionRate = computed(() =>
    props.stats.total > 0 ? Math.round((props.stats.complete / props.stats.total) * 100) : 0,
);

const maxDaily = computed(() =>
    Math.max(
        ...(props.charts.dailyEnrollment || []).map((row) => Math.max(row.face, row.rfid)),
        1,
    ),
);
</script>

<template>
    <div class="bg-slate-50 p-4">
        <div class="space-y-4">
            <section class="rounded-md border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 class="text-2xl font-bold text-slate-900">Registrar Dashboard</h1>
                        <p class="text-sm text-slate-500">Daily facial enrollment and RFID assignment logs.</p>
                    </div>
                    <div class="rounded-md bg-brand px-4 py-2 text-sm font-bold text-white">
                        {{ completionRate }}% complete
                    </div>
                </div>
            </section>

            <section class="grid grid-cols-1 gap-3 md:grid-cols-3 xl:grid-cols-6">
                <div class="rounded-md border border-slate-200 bg-white p-4 shadow-sm">
                    <Users class="h-5 w-5 text-brand" />
                    <p class="mt-3 text-xs font-semibold uppercase text-slate-400">Total Users</p>
                    <p class="mt-1 text-2xl font-bold text-slate-900">{{ stats.total }}</p>
                </div>
                <div class="rounded-md border border-slate-200 bg-white p-4 shadow-sm">
                    <Users class="h-5 w-5 text-brand" />
                    <p class="mt-3 text-xs font-semibold uppercase text-slate-400">Students</p>
                    <p class="mt-1 text-2xl font-bold text-brand">{{ stats.students }}</p>
                </div>
                <div class="rounded-md border border-slate-200 bg-white p-4 shadow-sm">
                    <Users class="h-5 w-5 text-slate-600" />
                    <p class="mt-3 text-xs font-semibold uppercase text-slate-400">Faculty</p>
                    <p class="mt-1 text-2xl font-bold text-slate-700">{{ stats.faculty }}</p>
                </div>
                <div class="rounded-md border border-red-200 bg-red-50 p-4">
                    <ScanFace class="h-5 w-5 text-red-600" />
                    <p class="mt-3 text-xs font-semibold uppercase text-red-500">Missing Face</p>
                    <p class="mt-1 text-2xl font-bold text-red-700">{{ stats.missing_face }}</p>
                </div>
                <div class="rounded-md border border-amber-200 bg-amber-50 p-4">
                    <IdCard class="h-5 w-5 text-amber-600" />
                    <p class="mt-3 text-xs font-semibold uppercase text-amber-600">Missing RFID</p>
                    <p class="mt-1 text-2xl font-bold text-amber-700">{{ stats.missing_rfid }}</p>
                </div>
                <div class="rounded-md border border-emerald-200 bg-emerald-50 p-4">
                    <CheckCircle2 class="h-5 w-5 text-emerald-600" />
                    <p class="mt-3 text-xs font-semibold uppercase text-emerald-600">Complete</p>
                    <p class="mt-1 text-2xl font-bold text-emerald-700">{{ stats.complete }}</p>
                </div>
            </section>

            <section class="grid grid-cols-1 gap-4 xl:grid-cols-[minmax(0,1fr)_360px]">
                <div class="rounded-md border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h2 class="text-sm font-bold text-slate-900">Daily Enrollment Logs</h2>
                            <p class="text-xs text-slate-500">Face uploads and RFID assignments per day.</p>
                        </div>
                        <div class="flex items-center gap-3 text-xs font-semibold text-slate-500">
                            <span class="flex items-center gap-1"><span class="h-2 w-2 rounded-full bg-red-500"></span>Face</span>
                            <span class="flex items-center gap-1"><span class="h-2 w-2 rounded-full bg-amber-500"></span>RFID</span>
                        </div>
                    </div>

                    <div
                        class="mt-6 grid min-h-[280px] items-end gap-2 overflow-x-auto"
                        style="grid-template-columns: repeat(14, minmax(52px, 1fr))"
                    >
                        <div
                            v-for="day in charts.dailyEnrollment"
                            :key="day.date"
                            class="flex min-w-[52px] flex-col items-center justify-end gap-2"
                        >
                            <div class="flex h-52 items-end gap-1">
                                <div
                                    class="w-4 rounded-t bg-red-500"
                                    :style="{ height: `${Math.max(day.face > 0 ? 10 : 0, (day.face / maxDaily) * 200)}px` }"
                                    :title="`${day.face} face enrollment(s)`"
                                ></div>
                                <div
                                    class="w-4 rounded-t bg-amber-500"
                                    :style="{ height: `${Math.max(day.rfid > 0 ? 10 : 0, (day.rfid / maxDaily) * 200)}px` }"
                                    :title="`${day.rfid} RFID assignment(s)`"
                                ></div>
                            </div>
                            <div class="text-center">
                                <p class="text-[11px] font-bold text-slate-600">{{ day.label }}</p>
                                <p class="text-[10px] text-slate-400">{{ day.face }}/{{ day.rfid }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="rounded-md border border-slate-200 bg-white p-5 shadow-sm">
                    <h2 class="text-sm font-bold text-slate-900">Recent Logs</h2>
                    <div class="mt-4 space-y-3">
                        <div
                            v-for="log in recentLogs"
                            :key="log.id"
                            class="rounded-md border border-slate-100 p-3"
                        >
                            <div class="flex items-center justify-between gap-2">
                                <span
                                    class="rounded-md px-2 py-1 text-xs font-bold uppercase"
                                    :class="log.action === 'face' ? 'bg-red-50 text-red-700' : 'bg-amber-50 text-amber-700'"
                                >
                                    {{ log.action === 'face' ? 'Face' : 'RFID' }}
                                </span>
                                <span class="text-xs text-slate-400">{{ log.time }}</span>
                            </div>
                            <p class="mt-2 text-sm font-bold text-slate-800">{{ log.person_name }}</p>
                            <p class="text-xs text-slate-500">{{ log.person_type }} | {{ log.identifier || 'No identifier' }}</p>
                            <p class="mt-1 text-xs text-slate-400">{{ log.date }}</p>
                        </div>
                        <div v-if="recentLogs.length === 0" class="rounded-md bg-slate-50 p-4 text-sm text-slate-500">
                            No enrollment logs yet.
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </div>
</template>
