<script setup>
import { useForm, usePage } from '@inertiajs/vue3';
import { Camera, UserRound, X } from 'lucide-vue-next';
import { computed, onBeforeUnmount, ref } from 'vue';

const page = usePage();
const user = computed(() => page.props.auth.user);
const flashSuccess = computed(() => page.props.flash?.success);
const photoInput = ref(null);
const localPhotoUrl = ref(null);
const isSubmitting = ref(false);

const initials = computed(() =>
    String(user.value?.name || '?')
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part.charAt(0).toUpperCase())
        .join(''),
);

const form = useForm({
    _method: 'patch',
    name: user.value?.name || '',
    middle_name: user.value?.middle_name || '',
    last_name: user.value?.last_name || '',
    email: user.value?.email || '',
    phone: user.value?.phone || '',
    gender: user.value?.gender || '',
    profile_photo: null,
    remove_profile_photo: false,
});

const displayedPhotoUrl = computed(() => {
    if (form.remove_profile_photo) return null;
    return localPhotoUrl.value || user.value?.profile_photo_url || null;
});

// @function clearLocalPhotoUrl: Nililinis ang local photo url sa Profile flow.
// @useIn clearLocalPhotoUrl: resources/js/pages/settings/Profile.vue:46
const clearLocalPhotoUrl = () => {
    if (localPhotoUrl.value) URL.revokeObjectURL(localPhotoUrl.value);
    localPhotoUrl.value = null;
};

// @function selectPhoto: Pinipili ang photo sa Profile flow.
// @useIn selectPhoto: resources/js/pages/settings/Profile.vue template @change
const selectPhoto = (event) => {
    const file = event.target.files?.[0] || null;
    clearLocalPhotoUrl();
    form.profile_photo = file;
    form.remove_profile_photo = false;
    form.clearErrors('profile_photo');

    if (file) localPhotoUrl.value = URL.createObjectURL(file);
};

// @function removePhoto: Tinatanggal ang photo sa Profile flow.
// @useIn removePhoto: resources/js/pages/settings/Profile.vue template @click
const removePhoto = () => {
    clearLocalPhotoUrl();
    form.profile_photo = null;
    form.remove_profile_photo = true;
    if (photoInput.value) photoInput.value.value = '';
};

// @function saveProfile: Sine-save ang profile sa Profile flow.
// @useIn saveProfile: resources/js/pages/settings/Profile.vue template
const saveProfile = () => {
    if (isSubmitting.value) return;

    isSubmitting.value = true;
    form.post(route('profile.update'), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            clearLocalPhotoUrl();
            form.profile_photo = null;
            form.remove_profile_photo = false;
            if (photoInput.value) photoInput.value.value = '';
        },
        onFinish: () => {
            isSubmitting.value = false;
        },
    });
};

onBeforeUnmount(clearLocalPhotoUrl);
</script>

<template>
    <section class="mx-auto w-full max-w-4xl py-2">
        <div class="rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-5 py-4 sm:px-6">
                <h1 class="text-xl font-bold text-slate-900">My Profile</h1>
                <p class="mt-1 text-sm text-slate-500">
                    Manage your account picture and personal contact details.
                </p>
            </div>

            <form class="p-5 sm:p-6" @submit.prevent="saveProfile">
                <p
                    v-if="flashSuccess"
                    class="mb-5 rounded-md bg-emerald-50 px-3 py-2 text-sm font-semibold text-emerald-700"
                >
                    {{ flashSuccess }}
                </p>

                <div
                    class="mb-6 flex flex-col gap-4 rounded-lg border border-slate-200 bg-slate-50 p-4 sm:flex-row sm:items-center"
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
                                class="inline-flex cursor-pointer items-center gap-2 rounded-md bg-brand px-3 py-2 text-sm font-bold text-white hover:opacity-90"
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

                <div class="grid gap-4 md:grid-cols-2">
                    <label class="text-sm font-semibold text-slate-700">
                        First or display name
                        <input
                            v-model="form.name"
                            required
                            autocomplete="given-name"
                            class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                        />
                        <span
                            v-if="form.errors.name"
                            class="mt-1 block text-xs text-red-600"
                            >{{ form.errors.name }}</span
                        >
                    </label>

                    <label class="text-sm font-semibold text-slate-700">
                        Middle name
                        <input
                            v-model="form.middle_name"
                            autocomplete="additional-name"
                            class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                        />
                        <span
                            v-if="form.errors.middle_name"
                            class="mt-1 block text-xs text-red-600"
                            >{{ form.errors.middle_name }}</span
                        >
                    </label>

                    <label class="text-sm font-semibold text-slate-700">
                        Last name
                        <input
                            v-model="form.last_name"
                            autocomplete="family-name"
                            class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                        />
                        <span
                            v-if="form.errors.last_name"
                            class="mt-1 block text-xs text-red-600"
                            >{{ form.errors.last_name }}</span
                        >
                    </label>

                    <label class="text-sm font-semibold text-slate-700">
                        Email address
                        <input
                            v-model="form.email"
                            type="email"
                            required
                            autocomplete="email"
                            class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                        />
                        <span
                            v-if="form.errors.email"
                            class="mt-1 block text-xs text-red-600"
                            >{{ form.errors.email }}</span
                        >
                    </label>

                    <label class="text-sm font-semibold text-slate-700">
                        Phone number
                        <input
                            v-model="form.phone"
                            type="tel"
                            autocomplete="tel"
                            class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                        />
                        <span
                            v-if="form.errors.phone"
                            class="mt-1 block text-xs text-red-600"
                            >{{ form.errors.phone }}</span
                        >
                    </label>

                    <label class="text-sm font-semibold text-slate-700">
                        Gender
                        <select
                            v-model="form.gender"
                            class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                        >
                            <option value="">Prefer not to say</option>
                            <option value="male">Male</option>
                            <option value="female">Female</option>
                        </select>
                        <span
                            v-if="form.errors.gender"
                            class="mt-1 block text-xs text-red-600"
                            >{{ form.errors.gender }}</span
                        >
                    </label>

                    <div class="md:col-span-2">
                        <p class="text-xs text-slate-500">
                            Account role:
                            <span class="font-bold text-slate-700 capitalize">{{
                                user.role
                            }}</span>
                        </p>
                    </div>
                </div>

                <div class="mt-6 flex justify-end">
                    <button
                        type="submit"
                        :disabled="isSubmitting || form.processing"
                        class="inline-flex items-center gap-2 rounded-md bg-brand px-5 py-2.5 text-sm font-bold text-white disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        <UserRound class="h-4 w-4" />
                        {{
                            isSubmitting || form.processing
                                ? 'Saving...'
                                : 'Save Profile'
                        }}
                    </button>
                </div>
            </form>
        </div>
    </section>
</template>
