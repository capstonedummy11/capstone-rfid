<script setup>
defineProps({
    attendance: { type: Array, default: () => [] },
});
</script>

<template>
    <div class="p-4 sm:p-6">
        <section class="rounded-md border border-slate-200 bg-white p-5 shadow-sm">
            <h1 class="text-xl font-bold text-slate-900">My Attendance</h1>
            <div class="mt-4 overflow-x-auto">
                <table class="w-full min-w-[720px] text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-3 py-2">Date</th>
                            <th class="px-3 py-2">Subject</th>
                            <th class="px-3 py-2">Room</th>
                            <th class="px-3 py-2">Time In</th>
                            <th class="px-3 py-2">Time Out</th>
                            <th class="px-3 py-2">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="record in attendance" :key="record.attendance_id">
                            <td class="px-3 py-3">{{ record.date }}</td>
                            <td class="px-3 py-3">{{ record.subject }}</td>
                            <td class="px-3 py-3">{{ record.room || '-' }}</td>
                            <td class="px-3 py-3">{{ record.time_in || '-' }}</td>
                            <td class="px-3 py-3">{{ record.time_out || '-' }}</td>
                            <td class="px-3 py-3 font-semibold text-slate-800">{{ record.status }}</td>
                        </tr>
                        <tr v-if="attendance.length === 0">
                            <td colspan="6" class="px-3 py-8 text-center text-slate-400">No attendance records found.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</template>
