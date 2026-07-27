<script setup>
import { router } from '@inertiajs/vue3';

const props = defineProps({
    students: { type: Array, default: () => [] },
    selectedStudentId: { type: [Number, String, null], default: null },
});

const selectStudent = (event) => {
    const studentId = event.target.value;
    const params = new URLSearchParams(window.location.search);

    if (studentId) {
        params.set('student_id', studentId);
    } else {
        params.delete('student_id');
    }

    router.get(
        `${window.location.pathname}?${params.toString()}`,
        {},
        {
            preserveScroll: true,
            preserveState: false,
        },
    );
};
</script>

<template>
    <div
        v-if="students.length > 1"
        class="flex w-full flex-wrap items-center gap-2 rounded-md border border-slate-200 bg-white px-3 py-2 shadow-sm sm:w-auto"
    >
        <label
            for="linked-student"
            class="shrink-0 text-xs font-black text-slate-500 uppercase"
            >Student</label
        >
        <select
            id="linked-student"
            class="min-w-0 flex-1 rounded-md border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700 outline-none focus:border-sky-400 sm:min-w-56"
            :value="selectedStudentId || ''"
            @change="selectStudent"
        >
            <option
                v-for="student in students"
                :key="student.student_id"
                :value="student.student_id"
            >
                {{ student.name }} - {{ student.section || 'No section' }}
            </option>
        </select>
    </div>
</template>
