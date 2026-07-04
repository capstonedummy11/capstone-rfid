<script setup>
import { router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import Swal from 'sweetalert2';

defineProps({
    onlineClasses: { type: Array, default: () => [] },
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);

const joinClass = async (onlineClass) => {
    if (onlineClass.require_face_recognition) {
        const result = await Swal.fire({
            title: 'Facial recognition required',
            text: 'Use the existing face verification workflow before recording attendance.',
            icon: 'info',
            showCancelButton: true,
            confirmButtonText: 'Mark verified and join',
        });
        if (!result.isConfirmed) return;
    }

    router.post(route('student-parent.online-classes.join', onlineClass.online_class_id), {
        face_verified: onlineClass.require_face_recognition,
    }, { preserveScroll: true });
};
</script>

<template>
    <div class="p-4 sm:p-6">
        <section class="rounded-md border border-slate-200 bg-white p-5 shadow-sm">
            <h1 class="text-xl font-bold text-slate-900">Online Classes</h1>
            <p v-if="flashSuccess" class="mt-3 rounded-md bg-emerald-50 px-3 py-2 text-sm font-semibold text-emerald-700">
                {{ flashSuccess }}
            </p>

            <div class="mt-4 grid gap-3">
                <article v-for="onlineClass in onlineClasses" :key="onlineClass.online_class_id" class="rounded-md border border-slate-200 p-4">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2 class="font-bold text-slate-900">{{ onlineClass.title }}</h2>
                            <p class="text-sm text-slate-500">{{ onlineClass.subject_name }} / {{ onlineClass.instructor_name }}</p>
                            <p class="mt-1 text-sm text-slate-600">{{ onlineClass.scheduled_date }} {{ onlineClass.start_time }}-{{ onlineClass.end_time }}</p>
                            <p class="mt-2 text-sm text-slate-600">{{ onlineClass.description }}</p>
                            <p class="mt-2 text-xs font-semibold text-slate-500">
                                Attendance: {{ onlineClass.attendance_status || 'Not joined' }} · Face: {{ onlineClass.require_face_recognition ? 'Required' : 'Not required' }}
                            </p>
                        </div>
                        <div class="flex gap-2">
                            <a :href="onlineClass.meeting_link" target="_blank" class="rounded-md border border-brand px-3 py-2 text-sm font-bold text-brand">
                                Open Link
                            </a>
                            <button class="rounded-md bg-brand px-3 py-2 text-sm font-bold text-white" @click="joinClass(onlineClass)">
                                Join
                            </button>
                        </div>
                    </div>
                </article>
                <div v-if="onlineClasses.length === 0" class="rounded-md border border-dashed border-slate-300 p-8 text-center text-slate-400">
                    No online classes found for your section.
                </div>
            </div>
        </section>
    </div>
</template>
