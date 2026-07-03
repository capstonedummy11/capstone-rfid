<template>
    <div class="space-y-4 p-4">
        <!-- Profile Header -->
        <div
            class="flex flex-col gap-4 rounded-lg border border-slate-100 bg-white p-6 shadow-sm md:flex-row md:items-center md:justify-between"
        >
            <div class="flex items-center gap-4">
                <div
                    class="flex h-16 w-16 shrink-0 items-center justify-center rounded-full bg-brand text-2xl font-bold text-white"
                >
                    {{ userInitial }}
                </div>
                <div>
                    <h1 class="text-2xl font-bold text-slate-900">
                        {{ user.name }}
                    </h1>
                    <p class="text-sm text-slate-500">{{ user.role }}</p>
                    <p class="text-sm text-slate-500">{{ user.email }}</p>
                </div>
            </div>
            <button
                @click="editMode = !editMode"
                :class="[
                    'rounded-lg px-4 py-2 font-semibold transition-colors',
                    editMode
                        ? 'bg-slate-200 text-slate-800 hover:bg-slate-300'
                        : 'bg-brand text-white hover:bg-blue-700',
                ]"
            >
                {{ editMode ? 'Cancel' : 'Edit Profile' }}
            </button>
        </div>

        <!-- Profile Form -->
        <div
            v-if="editMode"
            class="rounded-lg border border-slate-100 bg-white p-6 shadow-sm"
        >
            <form @submit.prevent="updateProfile" class="space-y-4">
                <div class="grid gap-4 md:grid-cols-2">
                    <!-- Name -->
                    <div class="flex flex-col gap-2">
                        <label class="text-sm font-semibold text-slate-700"
                            >Full Name</label
                        >
                        <input
                            v-model="form.name"
                            type="text"
                            class="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand focus:outline-none"
                        />
                        <span
                            v-if="form.errors?.name"
                            class="text-xs text-red-600"
                            >{{ form.errors.name }}</span
                        >
                    </div>

                    <!-- Email -->
                    <div class="flex flex-col gap-2">
                        <label class="text-sm font-semibold text-slate-700"
                            >Email</label
                        >
                        <input
                            v-model="form.email"
                            type="email"
                            disabled
                            class="rounded-lg border border-slate-300 bg-slate-100 px-3 py-2 text-sm text-slate-500"
                        />
                    </div>

                    <!-- Phone -->
                    <div class="flex flex-col gap-2">
                        <label class="text-sm font-semibold text-slate-700"
                            >Phone</label
                        >
                        <input
                            v-model="form.phone"
                            type="tel"
                            class="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand focus:outline-none"
                        />
                    </div>

                    <!-- Gender -->
                    <div class="flex flex-col gap-2">
                        <label class="text-sm font-semibold text-slate-700"
                            >Gender</label
                        >
                        <select
                            v-model="form.gender"
                            class="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand focus:outline-none"
                        >
                            <option value="">Select Gender</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                </div>

                <!-- Submit Button -->
                <div class="flex gap-2 pt-4">
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="rounded-lg bg-brand px-4 py-2 font-semibold text-white hover:bg-blue-700 disabled:opacity-50"
                    >
                        {{ form.processing ? 'Saving...' : 'Save Changes' }}
                    </button>
                </div>
            </form>
        </div>

        <!-- Profile Info Display -->
        <div v-else class="grid gap-4 md:grid-cols-2">
            <InfoCard label="Phone" :value="user.phone || 'Not provided'" />
            <InfoCard label="Gender" :value="user.gender || 'Not provided'" />
            <InfoCard label="Email" :value="user.email" />
            <InfoCard label="Role" :value="user.role" />
        </div>

        <!-- Change Password Section -->
        <div class="rounded-lg border border-slate-100 bg-white p-6 shadow-sm">
            <h2 class="mb-4 text-lg font-bold text-slate-900">
                Change Password
            </h2>
            <form @submit.prevent="changePassword" class="space-y-4">
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-semibold text-slate-700"
                        >Current Password</label
                    >
                    <input
                        v-model="passwordForm.current_password"
                        type="password"
                        class="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand focus:outline-none"
                    />
                    <span
                        v-if="passwordForm.errors?.current_password"
                        class="text-xs text-red-600"
                    >
                        {{ passwordForm.errors.current_password }}
                    </span>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div class="flex flex-col gap-2">
                        <label class="text-sm font-semibold text-slate-700"
                            >New Password</label
                        >
                        <input
                            v-model="passwordForm.password"
                            type="password"
                            class="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand focus:outline-none"
                        />
                        <span
                            v-if="passwordForm.errors?.password"
                            class="text-xs text-red-600"
                        >
                            {{ passwordForm.errors.password }}
                        </span>
                    </div>

                    <div class="flex flex-col gap-2">
                        <label class="text-sm font-semibold text-slate-700"
                            >Confirm Password</label
                        >
                        <input
                            v-model="passwordForm.password_confirmation"
                            type="password"
                            class="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand focus:outline-none"
                        />
                    </div>
                </div>

                <button
                    type="submit"
                    :disabled="passwordForm.processing"
                    class="rounded-lg bg-brand px-4 py-2 font-semibold text-white hover:bg-blue-700 disabled:opacity-50"
                >
                    {{
                        passwordForm.processing
                            ? 'Updating...'
                            : 'Change Password'
                    }}
                </button>
            </form>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';

const page = usePage();
const user = computed(() => page.props.auth.user || {});
const userInitial = computed(
    () => user.value.name?.charAt(0)?.toUpperCase() || '?',
);
const editMode = ref(false);

const form = useForm({
    name: user.value.name || '',
    email: user.value.email || '',
    phone: user.value.phone || '',
    gender: user.value.gender || '',
});

const passwordForm = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

function updateProfile() {
    form.put(route('student-parent.profile.update'), {
        preserveScroll: true,
        onSuccess: () => {
            editMode.value = false;
        },
    });
}

function changePassword() {
    passwordForm.put(route('student-parent.password.update'), {
        preserveScroll: true,
        onSuccess: () => {
            passwordForm.reset();
        },
    });
}
</script>
