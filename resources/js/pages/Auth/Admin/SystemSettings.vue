<script setup>
import { router, useForm, usePage } from '@inertiajs/vue3';
import Swal from 'sweetalert2';
import { computed } from 'vue';

const props = defineProps({
    featureSettings: {
        type: Object,
        default: () => ({
            borrowing_enabled: false,
            inventory_enabled: false,
            parent_portal_enabled: false,
            face_recognition_enabled: true,
            demo_attendance_panel_enabled: false,
            online_class_face_recognition_default: true,
        }),
    },
    faceRecognitionAvailability: {
        type: Object,
        default: () => ({
            available: true,
            message: 'AWS Rekognition is configured.',
        }),
    },
    attendanceSettings: {
        type: Object,
        default: () => ({
            absent_default_days: 15,
            late_threshold_minutes: 15,
        }),
    },
    demoAttendancePanelSettings: {
        type: Object,
        default: () => ({
            enabled: false,
            rfids: {
                professor_tap: 'RFID-INSTRUCTOR-SAMPLE',
                student_tap: 'RFID-STUDENT-1101',
                second_student_tap: 'RFID-STUDENT-1102',
                second_professor_tap: 'RFID-INSTRUCTOR-SAMPLE',
            },
        }),
    },
    securitySettings: {
        type: Object,
        default: () => ({
            questions: [],
        }),
    },
    clinicEmergencySoundSettings: {
        type: Object,
        default: () => ({
            selected_id: 'default',
            selected_url: '/sound/emergency-alert.mp3',
            sounds: [],
        }),
    },
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);
const faceAvailable = computed(() =>
    Boolean(props.faceRecognitionAvailability?.available),
);
const faceUnavailableMessage = computed(
    () =>
        props.faceRecognitionAvailability?.message ||
        'Face recognition is unavailable.',
);

const form = useForm({
    borrowing_enabled: Boolean(props.featureSettings.borrowing_enabled),
    inventory_enabled: Boolean(props.featureSettings.inventory_enabled),
    parent_portal_enabled: Boolean(props.featureSettings.parent_portal_enabled),
    face_recognition_enabled:
        faceAvailable.value &&
        Boolean(props.featureSettings.face_recognition_enabled),
    online_class_face_recognition_default:
        faceAvailable.value &&
        Boolean(props.featureSettings.face_recognition_enabled) &&
        Boolean(
            props.featureSettings.online_class_face_recognition_default ?? true,
        ),
    demo_attendance_panel_enabled: Boolean(
        props.demoAttendancePanelSettings.enabled,
    ),
    demo_attendance_panel_rfids: {
        professor_tap:
            props.demoAttendancePanelSettings.rfids?.professor_tap ??
            'RFID-INSTRUCTOR-SAMPLE',
        student_tap:
            props.demoAttendancePanelSettings.rfids?.student_tap ??
            'RFID-STUDENT-1101',
        second_student_tap:
            props.demoAttendancePanelSettings.rfids?.second_student_tap ??
            'RFID-STUDENT-1102',
        second_professor_tap:
            props.demoAttendancePanelSettings.rfids?.second_professor_tap ??
            'RFID-INSTRUCTOR-SAMPLE',
    },
    absent_default_days: Number(
        props.attendanceSettings.absent_default_days ?? 15,
    ),
    late_threshold_minutes: Number(
        props.attendanceSettings.late_threshold_minutes ?? 15,
    ),
    security_questions:
        props.securitySettings.questions?.length >= 3
            ? [...props.securitySettings.questions]
            : [
                  'What was the name of your first school?',
                  "What is your mother's maiden name?",
                  'What was the name of your first pet?',
              ],
});

const soundUploadForm = useForm({
    name: '',
    sound: null,
});

const emergencySounds = computed(
    () => props.clinicEmergencySoundSettings.sounds ?? [],
);
const selectedEmergencySoundId = computed(
    () => props.clinicEmergencySoundSettings.selected_id ?? 'default',
);

const addQuestion = () => {
    form.security_questions.push('');
};

const removeQuestion = (index) => {
    if (form.security_questions.length <= 3) return;
    form.security_questions.splice(index, 1);
};

const saveSettings = () => {
    form.put(route('admin.settings.update'), {
        preserveScroll: true,
        onSuccess: () => {
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'success',
                title: 'Settings updated',
                showConfirmButton: false,
                timer: 1800,
                timerProgressBar: true,
            });
        },
    });
};

const uploadEmergencySound = () => {
    soundUploadForm.post(route('admin.settings.emergency-sounds.store'), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            soundUploadForm.reset();
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'success',
                title: 'Emergency sound uploaded',
                showConfirmButton: false,
                timer: 1800,
                timerProgressBar: true,
            });
        },
    });
};

const selectEmergencySound = (sound) => {
    if (sound.id === selectedEmergencySoundId.value) return;

    router.put(
        route('admin.settings.emergency-sounds.select', { id: sound.id }),
        {},
        { preserveScroll: true },
    );
};

const deleteEmergencySound = (sound) => {
    if (sound.is_default) return;
    if (!confirm(`Delete emergency sound "${sound.name}"?`)) return;

    router.delete(
        route('admin.settings.emergency-sounds.destroy', { id: sound.id }),
        { preserveScroll: true },
    );
};

const formatSoundSize = (size) => {
    const bytes = Number(size ?? 0);
    if (!Number.isFinite(bytes) || bytes <= 0) return 'Built in';
    if (bytes < 1024 * 1024) return `${Math.round(bytes / 1024)} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
};

const toggleFaceSetting = (field) => {
    if (!faceAvailable.value) {
        form[field] = false;
        Swal.fire({
            icon: 'warning',
            title: 'Face recognition unavailable',
            text: faceUnavailableMessage.value,
        });
        return;
    }

    if (
        field === 'face_recognition_enabled' &&
        !form.face_recognition_enabled
    ) {
        form.online_class_face_recognition_default = false;
    }

    if (
        field === 'online_class_face_recognition_default' &&
        form.online_class_face_recognition_default &&
        !form.face_recognition_enabled
    ) {
        form.online_class_face_recognition_default = false;
        Swal.fire({
            icon: 'warning',
            title: 'Face Rekognition is off',
            text: 'Turn on Face Rekognition before enabling it for online classes.',
        });
    }
};
</script>

<template>
    <div class="p-4 sm:p-6">
        <div class="mx-auto flex max-w-4xl flex-col gap-4">
            <section
                class="rounded-md border border-slate-200 bg-white p-5 shadow-sm"
            >
                <div class="flex flex-col gap-2 border-b border-slate-100 pb-4">
                    <h2 class="text-lg font-bold text-slate-900">
                        System Settings
                    </h2>
                    <p class="text-sm text-slate-500">
                        Control which system modules are available to users and
                        the attendance panel.
                    </p>
                    <p
                        v-if="flashSuccess"
                        class="rounded-md bg-emerald-50 px-3 py-2 text-sm font-semibold text-emerald-700"
                    >
                        {{ flashSuccess }}
                    </p>
                    <p
                        v-if="!faceAvailable"
                        class="rounded-md bg-amber-50 px-3 py-2 text-sm font-semibold text-amber-800"
                    >
                        Face recognition settings are locked off:
                        {{ faceUnavailableMessage }}
                    </p>
                </div>

                <form
                    class="mt-4 flex flex-col gap-4"
                    @submit.prevent="saveSettings"
                >
                    <label
                        class="flex items-center justify-between gap-4 rounded-md border border-slate-200 p-4"
                    >
                        <span class="min-w-0">
                            <span
                                class="block text-sm font-bold text-slate-900"
                            >
                                Borrowing
                            </span>
                            <span class="block text-sm text-slate-500">
                                Enables the admin borrowing page and borrowing
                                mode on the attendance panel.
                            </span>
                        </span>
                        <input
                            v-model="form.borrowing_enabled"
                            type="checkbox"
                            class="h-5 w-5 shrink-0 accent-brand"
                        />
                    </label>

                    <label
                        class="flex items-center justify-between gap-4 rounded-md border border-slate-200 p-4"
                    >
                        <span class="min-w-0">
                            <span class="block text-sm font-bold text-slate-900">
                                Parent Portal
                            </span>
                            <span class="block text-sm text-slate-500">
                                Allows linked parent accounts to log in and view connected student information.
                            </span>
                        </span>
                        <input
                            v-model="form.parent_portal_enabled"
                            type="checkbox"
                            class="h-5 w-5 shrink-0 accent-brand"
                        />
                    </label>

                    <div
                        class="rounded-md border border-slate-200 bg-slate-50/40 p-4"
                    >
                        <label class="flex items-center justify-between gap-4">
                            <span class="min-w-0">
                                <span class="flex flex-wrap items-center gap-2">
                                    <span
                                        class="block text-sm font-bold text-slate-900"
                                    >
                                        Demo Attendance Panel
                                    </span>
                                    <span
                                        class="rounded-full px-2 py-0.5 text-[10px] font-black uppercase"
                                        :class="
                                            form.demo_attendance_panel_enabled
                                                ? 'bg-emerald-100 text-emerald-700'
                                                : 'bg-slate-200 text-slate-500'
                                        "
                                    >
                                        {{
                                            form.demo_attendance_panel_enabled
                                                ? 'Enabled'
                                                : 'Disabled'
                                        }}
                                    </span>
                                </span>
                                <span class="block text-sm text-slate-500">
                                    Shows manual demo tap buttons on the
                                    attendance panel only when enabled.
                                </span>
                            </span>
                            <input
                                v-model="form.demo_attendance_panel_enabled"
                                type="checkbox"
                                class="h-5 w-5 shrink-0 accent-brand"
                            />
                        </label>

                        <div
                            v-if="form.demo_attendance_panel_enabled"
                            class="mt-4 rounded-md border border-slate-200 bg-white p-4"
                        >
                            <div class="mb-3">
                                <p class="text-sm font-bold text-slate-900">
                                    Sample RFID Buttons
                                </p>
                                <p class="text-xs text-slate-500">
                                    These values are used by the demo buttons
                                    shown on the attendance panel.
                                </p>
                            </div>
                            <div class="grid gap-3 sm:grid-cols-2">
                                <label class="flex flex-col gap-1">
                                    <span
                                        class="text-xs font-bold text-slate-500 uppercase"
                                    >
                                        Professor Tap RFID
                                    </span>
                                    <input
                                        v-model="
                                            form.demo_attendance_panel_rfids
                                                .professor_tap
                                        "
                                        type="text"
                                        class="rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-800 focus:ring-2 focus:ring-brand focus:outline-none"
                                    />
                                </label>
                                <label class="flex flex-col gap-1">
                                    <span
                                        class="text-xs font-bold text-slate-500 uppercase"
                                    >
                                        Student Tap RFID
                                    </span>
                                    <input
                                        v-model="
                                            form.demo_attendance_panel_rfids
                                                .student_tap
                                        "
                                        type="text"
                                        class="rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-800 focus:ring-2 focus:ring-brand focus:outline-none"
                                    />
                                </label>
                                <label class="flex flex-col gap-1">
                                    <span
                                        class="text-xs font-bold text-slate-500 uppercase"
                                    >
                                        Second Student Tap RFID
                                    </span>
                                    <input
                                        v-model="
                                            form.demo_attendance_panel_rfids
                                                .second_student_tap
                                        "
                                        type="text"
                                        class="rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-800 focus:ring-2 focus:ring-brand focus:outline-none"
                                    />
                                </label>
                                <label class="flex flex-col gap-1">
                                    <span
                                        class="text-xs font-bold text-slate-500 uppercase"
                                    >
                                        Second Professor Tap RFID
                                    </span>
                                    <input
                                        v-model="
                                            form.demo_attendance_panel_rfids
                                                .second_professor_tap
                                        "
                                        type="text"
                                        class="rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-800 focus:ring-2 focus:ring-brand focus:outline-none"
                                    />
                                </label>
                            </div>
                            <p
                                v-if="form.errors.demo_attendance_panel_rfids"
                                class="mt-2 text-xs text-red-600"
                            >
                                {{ form.errors.demo_attendance_panel_rfids }}
                            </p>
                        </div>
                    </div>

                    <div class="rounded-md border border-slate-200 p-4">
                        <div
                            class="flex flex-col gap-2 border-b border-slate-100 pb-4"
                        >
                            <span class="text-sm font-bold text-slate-900">
                                Clinic Emergency Sound
                            </span>
                            <span class="text-sm text-slate-500">
                                Upload multiple alert sounds and choose the one
                                played by the clinic dashboard.
                            </span>
                        </div>

                        <div class="mt-4 grid gap-3 sm:grid-cols-[1fr_1fr_auto]">
                            <input
                                v-model="soundUploadForm.name"
                                type="text"
                                class="rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-800 focus:ring-2 focus:ring-brand focus:outline-none"
                                placeholder="Sound name"
                            />
                            <input
                                type="file"
                                accept=".mp3,.wav,.ogg,.m4a,.aac,audio/*"
                                class="rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-800 file:mr-3 file:rounded-md file:border-0 file:bg-slate-100 file:px-3 file:py-1 file:text-xs file:font-bold file:text-slate-700"
                                @change="
                                    soundUploadForm.sound =
                                        $event.target.files?.[0] ?? null
                                "
                            />
                            <button
                                type="button"
                                :disabled="
                                    soundUploadForm.processing ||
                                    !soundUploadForm.sound
                                "
                                class="rounded-md bg-brand px-4 py-2 text-sm font-bold text-white disabled:cursor-not-allowed disabled:opacity-50"
                                @click="uploadEmergencySound"
                            >
                                Upload
                            </button>
                        </div>
                        <p
                            v-if="soundUploadForm.errors.sound"
                            class="mt-2 text-xs text-red-600"
                        >
                            {{ soundUploadForm.errors.sound }}
                        </p>

                        <div class="mt-4 flex flex-col gap-3">
                            <div
                                v-for="sound in emergencySounds"
                                :key="sound.id"
                                class="grid gap-3 rounded-md border border-slate-200 bg-white p-3 sm:grid-cols-[1fr_auto] sm:items-center"
                            >
                                <label
                                    class="flex min-w-0 items-start gap-3"
                                >
                                    <input
                                        type="radio"
                                        name="clinic_emergency_sound"
                                        class="mt-1 h-4 w-4 accent-brand"
                                        :checked="
                                            sound.id ===
                                            selectedEmergencySoundId
                                        "
                                        @change="selectEmergencySound(sound)"
                                    />
                                    <span class="min-w-0">
                                        <span
                                            class="flex flex-wrap items-center gap-2"
                                        >
                                            <span
                                                class="text-sm font-bold text-slate-900"
                                            >
                                                {{ sound.name }}
                                            </span>
                                            <span
                                                v-if="
                                                    sound.id ===
                                                    selectedEmergencySoundId
                                                "
                                                class="rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-black text-emerald-700 uppercase"
                                            >
                                                Selected
                                            </span>
                                        </span>
                                        <span
                                            class="mt-1 block text-xs text-slate-500"
                                        >
                                            {{ sound.original_name }} ·
                                            {{ formatSoundSize(sound.size) }}
                                        </span>
                                    </span>
                                </label>
                                <div
                                    class="flex flex-wrap items-center gap-2 sm:justify-end"
                                >
                                    <audio
                                        :src="sound.url"
                                        controls
                                        class="h-9 max-w-full"
                                    ></audio>
                                    <button
                                        v-if="!sound.is_default"
                                        type="button"
                                        class="rounded-md border border-red-200 px-3 py-2 text-xs font-bold text-red-600 hover:bg-red-50"
                                        @click="deleteEmergencySound(sound)"
                                    >
                                        Delete
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <label
                        class="flex items-center justify-between gap-4 rounded-md border border-slate-200 p-4"
                    >
                        <span class="min-w-0">
                            <span
                                class="block text-sm font-bold text-slate-900"
                            >
                                Late Threshold
                            </span>
                            <span class="block text-sm text-slate-500">
                                Marks a student late when time-in is this many
                                minutes after the scheduled class start.
                            </span>
                        </span>
                        <span class="flex shrink-0 items-center gap-2">
                            <input
                                v-model.number="form.late_threshold_minutes"
                                type="number"
                                min="0"
                                max="180"
                                required
                                class="w-24 rounded-md border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-800 focus:ring-2 focus:ring-brand focus:outline-none"
                            />
                            <span class="text-sm text-slate-500">minutes</span>
                        </span>
                    </label>

                    <div class="rounded-md border border-slate-200 p-4">
                        <div
                            class="flex flex-wrap items-start justify-between gap-3"
                        >
                            <span class="min-w-0">
                                <span
                                    class="block text-sm font-bold text-slate-900"
                                >
                                    Instructor Security Questions
                                </span>
                                <span class="block text-sm text-slate-500">
                                    Preset questions instructors can choose
                                    during first-login setup.
                                </span>
                            </span>
                            <button
                                type="button"
                                class="rounded-md border border-slate-300 px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50"
                                @click="addQuestion"
                            >
                                Add Question
                            </button>
                        </div>

                        <div class="mt-4 flex flex-col gap-3">
                            <div
                                v-for="(_, index) in form.security_questions"
                                :key="index"
                                class="grid grid-cols-[1fr_auto] gap-2"
                            >
                                <input
                                    v-model="form.security_questions[index]"
                                    type="text"
                                    class="min-w-0 rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-800 focus:ring-2 focus:ring-brand focus:outline-none"
                                    :placeholder="`Security question ${index + 1}`"
                                    required
                                />
                                <button
                                    type="button"
                                    :disabled="
                                        form.security_questions.length <= 3
                                    "
                                    class="rounded-md border border-red-200 px-3 py-2 text-xs font-bold text-red-600 disabled:cursor-not-allowed disabled:opacity-40"
                                    @click="removeQuestion(index)"
                                >
                                    Remove
                                </button>
                            </div>
                        </div>
                        <p
                            v-if="form.errors.security_questions"
                            class="mt-2 text-xs text-red-600"
                        >
                            {{ form.errors.security_questions }}
                        </p>
                    </div>

                    <label
                        class="flex items-center justify-between gap-4 rounded-md border border-slate-200 p-4"
                    >
                        <span class="min-w-0">
                            <span
                                class="block text-sm font-bold text-slate-900"
                            >
                                Absent Attendance Days
                            </span>
                            <span class="block text-sm text-slate-500">
                                Shows default absent status only within this
                                recent day range.
                            </span>
                        </span>
                        <input
                            v-model.number="form.absent_default_days"
                            type="number"
                            min="1"
                            max="365"
                            class="w-24 shrink-0 rounded-md border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-800 focus:ring-2 focus:ring-brand focus:outline-none"
                        />
                    </label>

                    <label
                        class="flex items-center justify-between gap-4 rounded-md border border-slate-200 p-4"
                    >
                        <span class="min-w-0">
                            <span
                                class="block text-sm font-bold text-slate-900"
                            >
                                Inventory
                            </span>
                            <span class="block text-sm text-slate-500">
                                Enables the admin inventory page and item
                                management actions.
                            </span>
                        </span>
                        <input
                            v-model="form.inventory_enabled"
                            type="checkbox"
                            class="h-5 w-5 shrink-0 accent-brand"
                        />
                    </label>

                    <label
                        class="flex items-center justify-between gap-4 rounded-md border border-slate-200 p-4"
                    >
                        <span class="min-w-0">
                            <span
                                class="block text-sm font-bold text-slate-900"
                            >
                                Face Rekognition
                            </span>
                            <span class="block text-sm text-slate-500">
                                Requires camera face verification before
                                attendance is recorded.
                            </span>
                        </span>
                        <input
                            v-model="form.face_recognition_enabled"
                            type="checkbox"
                            class="h-5 w-5 shrink-0 accent-brand"
                            @change="
                                toggleFaceSetting('face_recognition_enabled')
                            "
                        />
                    </label>

                    <label
                        class="flex items-center justify-between gap-4 rounded-md border border-slate-200 p-4"
                    >
                        <span class="min-w-0">
                            <span
                                class="block text-sm font-bold text-slate-900"
                            >
                                Online Class Facial Recognition Enabled by
                                Default
                            </span>
                            <span class="block text-sm text-slate-500">
                                Sets the default facial recognition requirement
                                when instructors create online classes.
                            </span>
                        </span>
                        <input
                            v-model="form.online_class_face_recognition_default"
                            type="checkbox"
                            class="h-5 w-5 shrink-0 accent-brand"
                            @change="
                                toggleFaceSetting(
                                    'online_class_face_recognition_default',
                                )
                            "
                        />
                    </label>

                    <div class="flex justify-end">
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="rounded-md bg-brand px-4 py-2 text-sm font-bold text-white disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            {{
                                form.processing ? 'Saving...' : 'Save Settings'
                            }}
                        </button>
                    </div>
                </form>
            </section>
        </div>
    </div>
</template>
