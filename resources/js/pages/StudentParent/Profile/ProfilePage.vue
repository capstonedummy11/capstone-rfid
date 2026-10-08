<script setup>
import { useForm, usePage } from '@inertiajs/vue3';
import LinkedStudentSelector from '@/components/StudentPortal/LinkedStudentSelector.vue';
import { Camera, X } from 'lucide-vue-next';
import { computed, onBeforeUnmount, ref } from 'vue';
import {
    MIN_PASSWORD_LENGTH,
    PASSWORD_LENGTH_ERROR,
    PASSWORD_LENGTH_HELPER,
    isPasswordTooShort,
} from '@/lib/passwordPolicy';

const props = defineProps({
    student: { type: Object, default: null },
    linkedStudents: { type: Array, default: () => [] },
    selectedStudentId: { type: [Number, String, null], default: null },
    user: { type: Object, required: true },
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);
const photoInput = ref(null);
const localPhotoUrl = ref(null);
const isProfileSubmitting = ref(false);

const form = useForm({
    _method: 'put',
    name: props.user.name || '',
    phone: props.user.phone || props.student?.phone || '',
    gender: props.user.gender || props.student?.gender || '',
    profile_photo: null,
    remove_profile_photo: false,
});

const initials = computed(() =>
    String(props.user.name || '?')
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part.charAt(0).toUpperCase())
        .join(''),
);

const displayedPhotoUrl = computed(() => {
    if (form.remove_profile_photo) return null;
    return localPhotoUrl.value || props.user.profile_photo_url || null;
});

// @function clearLocalPhotoUrl: Nililinis ang local photo url sa Profile flow.
// @useIn clearLocalPhotoUrl: resources/js/pages/StudentParent/Profile/ProfilePage.vue:56
const clearLocalPhotoUrl = () => {
    if (localPhotoUrl.value) URL.revokeObjectURL(localPhotoUrl.value);
    localPhotoUrl.value = null;
};

// @function selectPhoto: Pinipili ang photo sa Profile flow.
// @useIn selectPhoto: resources/js/pages/StudentParent/Profile/ProfilePage.vue template @change
const selectPhoto = (event) => {
    const file = event.target.files?.[0] || null;
    clearLocalPhotoUrl();
    form.profile_photo = file;
    form.remove_profile_photo = false;
    form.clearErrors('profile_photo');

    if (file) localPhotoUrl.value = URL.createObjectURL(file);
};

// @function removePhoto: Tinatanggal ang photo sa Profile flow.
// @useIn removePhoto: resources/js/pages/StudentParent/Profile/ProfilePage.vue template @click
const removePhoto = () => {
    clearLocalPhotoUrl();
    form.profile_photo = null;
    form.remove_profile_photo = true;
    if (photoInput.value) photoInput.value.value = '';
};

const passwordForm = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});
const isPasswordSubmitting = ref(false);

// @function saveProfile: Sine-save ang profile sa Profile flow.
// @useIn saveProfile: resources/js/pages/StudentParent/Profile/ProfilePage.vue template
const saveProfile = () => {
    if (isProfileSubmitting.value) return;

    isProfileSubmitting.value = true;
    form.post(route('student-parent.profile.update'), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            clearLocalPhotoUrl();
            form.profile_photo = null;
            form.remove_profile_photo = false;
            if (photoInput.value) photoInput.value.value = '';
        },
        onFinish: () => {
            isProfileSubmitting.value = false;
        },
    });
};

// @function savePassword: Sine-save ang password sa Profile flow.
// @useIn savePassword: resources/js/pages/StudentParent/Profile/ProfilePage.vue template
const savePassword = () => {
    if (isPasswordSubmitting.value) return;

    if (isPasswordTooShort(passwordForm.password)) {
        passwordForm.setError('password', PASSWORD_LENGTH_ERROR);
        return;
    }

    passwordForm.clearErrors('password');
    isPasswordSubmitting.value = true;
    passwordForm.put(route('student-parent.password.update'), {
        preserveScroll: true,
        onSuccess: () => passwordForm.reset(),
        onFinish: () => {
            isPasswordSubmitting.value = false;
        },
    });
};

onBeforeUnmount(clearLocalPhotoUrl);
</script>

<template>
    <div class="student-portal-page">
        <div class="student-portal-shell grid gap-5 lg:grid-cols-[1fr_360px]">
            <section
                class="rounded-md border border-slate-200 bg-white p-5 shadow-sm"
            >
                <h1 class="text-xl font-bold text-slate-900">Profile</h1>
                <p
                    v-if="flashSuccess"
                    class="mt-3 rounded-md bg-emerald-50 px-3 py-2 text-sm font-semibold text-emerald-700"
                >
                    {{ flashSuccess }}
                </p>

                <form
                    class="mt-4 grid gap-4 md:grid-cols-2"
                    @submit.prevent="saveProfile"
                >
                    <div
                        class="flex flex-col gap-4 rounded-lg border border-slate-200 bg-slate-50 p-4 sm:flex-row sm:items-center md:col-span-2"
                    >
                        <img
                            v-if="displayedPhotoUrl"
                            :src="displayedPhotoUrl"
                            alt="Current profile picture"
                            class="h-24 w-24 rounded-full border-4 border-white object-cover shadow-sm"
                        />
                        <div
                            v-else
                            class="flex h-24 w-24 items-center justify-center rounded-full bg-brand text-2xl font-bold text-white shadow-sm"
                            aria-label="Profile picture placeholder"
                        >
                            {{ initials }}
                        </div>
                        <div class="min-w-0 flex-1">
                            <h2 class="font-bold text-slate-900">
                                Profile picture
                            </h2>
                            <p class="mt-1 text-xs text-slate-500">
                                JPEG, PNG, or WebP. Maximum file size: 2 MB.
                            </p>
                            <div class="mt-3 flex flex-wrap gap-2">
                                <label
                                    class="inline-flex cursor-pointer items-center gap-2 rounded-md bg-brand px-3 py-2 text-sm font-bold text-white"
                                >
                                    <Camera class="h-4 w-4" />
                                    Choose picture
                                    <input
                                        ref="photoInput"
                                        type="file"
                                        accept="image/jpeg,image/png,image/webp"
                                        class="sr-only"
                                        @change="selectPhoto"
                                    />
                                </label>
                                <button
                                    v-if="displayedPhotoUrl"
                                    type="button"
                                    class="inline-flex items-center gap-2 rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-bold text-slate-700"
                                    @click="removePhoto"
                                >
                                    <X class="h-4 w-4" />
                                    Remove
                                </button>
                            </div>
                            <p
                                v-if="form.errors.profile_photo"
                                class="mt-2 text-xs font-semibold text-red-600"
                            >
                                {{ form.errors.profile_photo }}
                            </p>
                        </div>
                    </div>
                    <label class="text-sm font-semibold text-slate-700">
                        Name
                        <input
                            v-model="form.name"
                            class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                            required
                        />
                    </label>
                    <label class="text-sm font-semibold text-slate-700">
                        Phone
                        <input
                            v-model="form.phone"
                            class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                        />
                    </label>
                    <label class="text-sm font-semibold text-slate-700">
                        Gender
                        <select
                            v-model="form.gender"
                            class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                        >
                            <option value="">Select gender</option>
                            <option value="male">Male</option>
                            <option value="female">Female</option>
                        </select>
                    </label>
                    <div class="md:col-span-2">
                        <button
                            class="rounded-md bg-brand px-4 py-2 text-sm font-bold text-white"
                            :disabled="isProfileSubmitting || form.processing"
                        >
                            {{
                                isProfileSubmitting || form.processing
                                    ? 'Saving...'
                                    : 'Save Profile'
                            }}
                        </button>
                    </div>
                </form>
            </section>

            <aside
                class="rounded-md border border-slate-200 bg-white p-5 shadow-sm"
            >
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h2 class="font-bold text-slate-900">Student Record</h2>
                    <LinkedStudentSelector
                        :students="linkedStudents"
                        :selected-student-id="selectedStudentId"
                    />
                </div>
                <dl class="mt-4 space-y-3 text-sm">
                    <div>
                        <dt class="font-semibold text-slate-500">
                            Student No.
                        </dt>
                        <dd>{{ student?.student_number || '-' }}</dd>
                    </div>
                    <div>
                        <dt class="font-semibold text-slate-500">Section</dt>
                        <dd>{{ student?.section || '-' }}</dd>
                    </div>
                    <div>
                        <dt class="font-semibold text-slate-500">Strand</dt>
                        <dd>{{ student?.strand || '-' }}</dd>
                    </div>
                    <div>
                        <dt class="font-semibold text-slate-500">
                            School Year
                        </dt>
                        <dd>{{ student?.school_year || '-' }}</dd>
                    </div>
                </dl>

                <form
                    class="mt-6 flex flex-col gap-3"
                    novalidate
                    @submit.prevent="savePassword"
                >
                    <h2 class="font-bold text-slate-900">Change Password</h2>
                    <input
                        v-model="passwordForm.current_password"
                        type="password"
                        placeholder="Current password"
                        class="rounded-md border border-slate-300 px-3 py-2 text-sm"
                        required
                    />
                    <label class="text-sm font-semibold text-slate-700">
                        New password
                        <input
                            v-model="passwordForm.password"
                            type="password"
                            required
                            :minlength="MIN_PASSWORD_LENGTH"
                            autocomplete="new-password"
                            aria-describedby="portal-password-requirement"
                            :aria-invalid="
                                Boolean(passwordForm.errors.password) ||
                                isPasswordTooShort(passwordForm.password)
                            "
                            class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                            @input="passwordForm.clearErrors('password')"
                        />
                        <span
                            id="portal-password-requirement"
                            class="mt-1 block text-xs font-normal"
                            :class="
                                passwordForm.errors.password ||
                                isPasswordTooShort(passwordForm.password)
                                    ? 'text-red-600'
                                    : 'text-slate-500'
                            "
                            aria-live="polite"
                        >
                            {{
                                passwordForm.errors.password ||
                                (isPasswordTooShort(passwordForm.password)
                                    ? PASSWORD_LENGTH_ERROR
                                    : PASSWORD_LENGTH_HELPER)
                            }}
                        </span>
                    </label>
                    <input
                        v-model="passwordForm.password_confirmation"
                        type="password"
                        placeholder="Confirm password"
                        class="rounded-md border border-slate-300 px-3 py-2 text-sm"
                        required
                    />
                    <p
                        v-if="passwordForm.errors.password_confirmation"
                        class="text-xs text-red-600"
                    >
                        {{ passwordForm.errors.password_confirmation }}
                    </p>
                    <button
                        class="rounded-md border border-slate-300 px-4 py-2 text-sm font-bold text-slate-700"
                        :disabled="
                            isPasswordSubmitting || passwordForm.processing
                        "
                    >
                        {{
                            isPasswordSubmitting || passwordForm.processing
                                ? 'Updating...'
                                : 'Update Password'
                        }}
                    </button>
                </form>
            </aside>
        </div>
    </div>
</template>
