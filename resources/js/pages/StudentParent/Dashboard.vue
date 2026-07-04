<script setup>
defineProps({
    student: { type: Object, default: null },
    stats: { type: Object, default: () => ({}) },
    recentAttendance: { type: Array, default: () => [] },
    recentMessages: { type: Array, default: () => [] },
});
</script>

<template>
    <div class="p-4 sm:p-6">
        <div class="mx-auto flex max-w-7xl flex-col gap-5">
            <section class="rounded-md border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-sm font-semibold text-slate-500">Welcome back</p>
                <h1 class="mt-1 text-2xl font-bold text-slate-900">
                    {{ student?.name || 'Student' }}
                </h1>
                <p class="mt-1 text-sm text-slate-500">
                    {{ student?.student_number || 'No student record linked' }}
                    <span v-if="student"> | {{ student.section }} | {{ student.school_year }}</span>
                </p>
            </section>

            <section class="grid gap-4 md:grid-cols-4">
                <div class="rounded-md border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="text-xs font-bold uppercase text-slate-400">Present</p>
                    <p class="mt-2 text-3xl font-extrabold text-emerald-600">{{ stats.present || 0 }}</p>
                </div>
                <div class="rounded-md border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="text-xs font-bold uppercase text-slate-400">Late</p>
                    <p class="mt-2 text-3xl font-extrabold text-amber-600">{{ stats.late || 0 }}</p>
                </div>
                <div class="rounded-md border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="text-xs font-bold uppercase text-slate-400">Excuse Letters</p>
                    <p class="mt-2 text-3xl font-extrabold text-blue-600">{{ stats.excuse_letters || 0 }}</p>
                </div>
                <div class="rounded-md border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="text-xs font-bold uppercase text-slate-400">Online Classes</p>
                    <p class="mt-2 text-3xl font-extrabold text-brand">{{ stats.online_classes || 0 }}</p>
                </div>
            </section>

            <section class="grid gap-5 lg:grid-cols-2">
                <div class="rounded-md border border-slate-200 bg-white p-5 shadow-sm">
                    <h2 class="font-bold text-slate-900">Recent Attendance</h2>
                    <div class="mt-4 divide-y divide-slate-100">
                        <div v-for="record in recentAttendance" :key="record.attendance_id" class="py-3 text-sm">
                            <div class="flex justify-between gap-3">
                                <span class="font-semibold text-slate-800">{{ record.subject || 'Subject' }}</span>
                                <span class="text-slate-500">{{ record.status }}</span>
                            </div>
                            <p class="mt-1 text-xs text-slate-500">{{ record.date }} {{ record.time_in || '' }}</p>
                        </div>
                        <p v-if="recentAttendance.length === 0" class="py-6 text-center text-sm text-slate-400">No attendance records yet.</p>
                    </div>
                </div>

                <div class="rounded-md border border-slate-200 bg-white p-5 shadow-sm">
                    <h2 class="font-bold text-slate-900">Recent Messages</h2>
                    <div class="mt-4 divide-y divide-slate-100">
                        <div v-for="message in recentMessages" :key="message.id" class="py-3 text-sm">
                            <div class="flex justify-between gap-3">
                                <span class="font-semibold text-slate-800">{{ message.subject }}</span>
                                <span class="text-slate-500">{{ message.sender_role }}</span>
                            </div>
                            <p class="mt-1 text-xs text-slate-500">{{ message.created_at }}</p>
                        </div>
                        <p v-if="recentMessages.length === 0" class="py-6 text-center text-sm text-slate-400">No messages yet.</p>
                    </div>
                </div>
            </section>
        </div>
    </div>
</template>
