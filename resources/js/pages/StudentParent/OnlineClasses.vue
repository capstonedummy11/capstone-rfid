<script setup>
import { router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import CameraCapture from '@/components/CameraCapture.vue';
import LinkedStudentSelector from '@/components/StudentPortal/LinkedStudentSelector.vue';

const props = defineProps({
    student: { type: Object, default: null },
    linkedStudents: { type: Array, default: () => [] },
    selectedStudentId: { type: [Number, String, null], default: null },
    faceRecognitionAvailability: {
        type: Object,
        default: () => ({
            available: true,
            message: 'AWS Rekognition is configured.',
        }),
    },
    onlineClasses: { type: Array, default: () => [] },
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);
const cameraRef = ref(null);
const verifyingClass = ref(null);
const verificationError = ref('');
const verificationMessage = ref('');
const verificationBusy = ref(false);
const faceAvailable = computed(() =>
    Boolean(props.faceRecognitionAvailability?.available),
);
const xsrfToken = () =>
    document.cookie
        .split('; ')
        .find((row) => row.startsWith('XSRF-TOKEN='))
        ?.split('=')[1];

const postJoin = (onlineClass, faceVerified = false, faceImage = null) => {
    router.post(
        route(
            'student-parent.online-classes.join',
            onlineClass.online_class_id,
        ),
        {
            face_verified: faceVerified,
            face_image: faceImage,
            student_id: props.selectedStudentId,
        },
        {
            preserveScroll: true,
            onSuccess: () => closeVerification(),
        },
    );
};

const joinClass = (onlineClass) => {
    if (onlineClass.require_face_recognition) {
        if (!faceAvailable.value) {
            verificationBusy.value = true;
            postJoin(onlineClass, false, null);
            return;
        }

        verifyingClass.value = onlineClass;
        verificationError.value = '';
        verificationMessage.value =
            'Position your face in the camera, then verify to join.';
        return;
    }

    postJoin(onlineClass, false);
};

const closeVerification = () => {
    verifyingClass.value = null;
    verificationError.value = '';
    verificationMessage.value = '';
    verificationBusy.value = false;
    cameraRef.value?.resetCapture();
};

const verifyFaceAndJoin = async () => {
    if (!verifyingClass.value) return;

    const image = cameraRef.value?.captureFrame();
    if (!image) {
        verificationError.value = 'Camera capture is required before joining.';
        return;
    }

    verificationBusy.value = true;
    verificationError.value = '';
    verificationMessage.value = 'Verifying face...';

    try {
        const response = await fetch('/face-recognition/verify-student', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-XSRF-TOKEN': xsrfToken()
                    ? decodeURIComponent(xsrfToken())
                    : '',
            },
            body: JSON.stringify({
                image,
                student_number: props.student?.student_number,
            }),
        });
        const payload = await response.json().catch(() => null);

        if (!response.ok || !payload?.verified) {
            verificationBusy.value = false;
            verificationError.value =
                payload?.message || 'Face verification failed.';
            cameraRef.value?.resetCapture();
            return;
        }

        verificationMessage.value = payload.message || 'Face verified.';
        postJoin(verifyingClass.value, true, image);
    } catch {
        verificationBusy.value = false;
        verificationError.value = 'Connection error during face verification.';
        cameraRef.value?.resetCapture();
    }
};
</script>

<template>
    <div class="student-portal-page">
        <section
            class="student-portal-shell rounded-md border border-slate-200 bg-white p-5 shadow-sm"
        >
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-xl font-bold text-slate-900">
                        Online Classes
                    </h1>
                    <p class="mt-1 text-sm text-slate-500">
                        {{ student?.name || 'Student' }}
                    </p>
                </div>
                <LinkedStudentSelector
                    :students="linkedStudents"
                    :selected-student-id="selectedStudentId"
                />
            </div>
            <p
                v-if="flashSuccess"
                class="mt-3 rounded-md bg-emerald-50 px-3 py-2 text-sm font-semibold text-emerald-700"
            >
                {{ flashSuccess }}
            </p>
            <div class="mt-4 grid gap-3">
                <article
                    v-for="onlineClass in onlineClasses"
                    :key="onlineClass.online_class_id"
                    class="rounded-md border border-slate-200 p-4"
                >
                    <div
                        class="flex flex-wrap items-start justify-between gap-3"
                    >
                        <div>
                            <h2 class="font-bold text-slate-900">
                                {{ onlineClass.title }}
                            </h2>
                            <p class="text-sm text-slate-500">
                                {{ onlineClass.subject_name }} /
                                {{ onlineClass.instructor_name }}
                            </p>
                            <p class="mt-1 text-sm text-slate-600">
                                {{ onlineClass.scheduled_date }}
                                {{ onlineClass.start_time }}-{{
                                    onlineClass.end_time
                                }}
                            </p>
                            <p class="mt-2 text-sm text-slate-600">
                                {{ onlineClass.description }}
                            </p>
                            <p
                                class="mt-2 text-xs font-semibold text-slate-500"
                            >
                                Attendance:
                                {{
                                    onlineClass.attendance_status ||
                                    'Not joined'
                                }}
                                | Face:
                                {{
                                    onlineClass.require_face_recognition
                                        ? 'Required'
                                        : 'Not required'
                                }}
                            </p>
                        </div>
                        <div class="flex gap-2">
                            <a
                                :href="onlineClass.meeting_link"
                                target="_blank"
                                class="rounded-md border border-brand px-3 py-2 text-sm font-bold text-brand"
                            >
                                Open Link
                            </a>
                            <button
                                class="rounded-md bg-brand px-3 py-2 text-sm font-bold text-white"
                                @click="joinClass(onlineClass)"
                            >
                                Join
                            </button>
                        </div>
                    </div>
                </article>
                <div
                    v-if="onlineClasses.length === 0"
                    class="rounded-md border border-dashed border-slate-300 p-8 text-center text-slate-400"
                >
                    No online classes found for your section.
                </div>
            </div>
        </section>

        <div
            v-if="verifyingClass"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4"
        >
            <section class="w-full max-w-md rounded-md bg-white p-5 shadow-xl">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-bold text-slate-900">
                            Face Verification
                        </h2>
                        <p class="mt-1 text-sm text-slate-500">
                            {{ verifyingClass.title }}
                        </p>
                    </div>
                    <button
                        class="rounded-md border border-slate-200 px-3 py-1 text-sm font-bold text-slate-500"
                        @click="closeVerification"
                    >
                        Close
                    </button>
                </div>

                <div class="mt-5 flex flex-col items-center gap-4">
                    <CameraCapture ref="cameraRef" />
                    <p class="text-center text-sm text-slate-600">
                        {{ verificationMessage }}
                    </p>
                    <p
                        v-if="verificationError"
                        class="rounded-md bg-red-50 px-3 py-2 text-center text-sm font-semibold text-red-600"
                    >
                        {{ verificationError }}
                    </p>
                    <button
                        class="w-full rounded-md bg-brand px-4 py-2 text-sm font-bold text-white disabled:opacity-60"
                        :disabled="verificationBusy"
                        @click="verifyFaceAndJoin"
                    >
                        {{
                            verificationBusy
                                ? 'Verifying...'
                                : 'Verify Face and Join'
                        }}
                    </button>
                </div>
            </section>
        </div>
    </div>
</template>
