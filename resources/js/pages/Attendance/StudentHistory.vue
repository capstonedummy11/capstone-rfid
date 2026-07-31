<script setup>
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    subject: { type: Object, required: true },
    student: { type: Object, required: true },
    history: { type: Array, default: () => [] },
});
</script>

<template>
    <Head :title="`${student.name} Attendance History`" />
    <main class="min-h-screen bg-slate-50 px-4 py-6 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-5xl">
            <Link
                :href="route('admin.attendance.summary', subject.id)"
                class="text-sm font-bold text-blue-700"
                >← Student summary</Link
            >
            <header
                class="mt-4 rounded-3xl bg-gradient-to-r from-slate-950 to-blue-800 p-7 text-white"
            >
                <p
                    class="text-xs font-bold tracking-widest text-blue-200 uppercase"
                >
                    {{ subject.code }} · {{ subject.name }}
                </p>
                <h1 class="mt-2 text-3xl font-black">{{ student.name }}</h1>
                <p class="mt-2 text-sm text-blue-100">
                    {{ student.student_number }} · Complete attendance history
                </p>
            </header>
            <div
                class="mt-5 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"
            >
                <table class="min-w-full text-sm">
                    <thead
                        class="bg-slate-100 text-left text-xs text-slate-600 uppercase"
                    >
                        <tr>
                            <th class="px-4 py-3">Date</th>
                            <th class="px-4 py-3">Schedule</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Time in</th>
                            <th class="px-4 py-3">Time out</th>
                            <th class="px-4 py-3">Remarks</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr
                            v-for="item in history"
                            :key="item.session_id"
                            class="hover:bg-slate-50"
                        >
                            <td class="px-4 py-3 font-bold text-slate-900">
                                {{ item.date_label }}
                            </td>
                            <td class="px-4 py-3 text-slate-600">
                                {{ item.schedule }}<br /><span
                                    class="text-xs text-slate-400"
                                    >{{ item.room }}</span
                                >
                            </td>
                            <td class="px-4 py-3">
                                <span
                                    class="rounded-full bg-blue-50 px-3 py-1 text-xs font-bold text-blue-700"
                                    >{{ item.status }}</span
                                >
                            </td>
                            <td class="px-4 py-3">{{ item.time_in || '—' }}</td>
                            <td class="px-4 py-3">
                                {{ item.time_out || '—' }}
                            </td>
                            <td class="px-4 py-3 text-slate-500">
                                {{ item.remarks || '—' }}
                            </td>
                        </tr>
                    </tbody>
                </table>
                <p
                    v-if="!history.length"
                    class="p-10 text-center text-sm text-slate-500"
                >
                    No attendance sessions are available.
                </p>
            </div>
        </div>
    </main>
</template>
