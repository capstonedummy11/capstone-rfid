<!-- FEATURE:user-management - UI para sa user and role management. -->
<script setup lang="ts">
import { router, useForm, usePage } from '@inertiajs/vue3';
import {
    Check,
    Edit3,
    Eye,
    EyeOff,
    KeyRound,
    ShieldCheck,
    Trash2,
    UserPlus,
    UsersRound,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { confirmActionModal } from '@/lib/feedbackModal';
import {
    MIN_PASSWORD_LENGTH,
    PASSWORD_LENGTH_ERROR,
    evaluatePasswordRequirements,
    isPasswordTooShort,
    meetsPasswordRequirements,
} from '@/lib/passwordPolicy';
import RootOwnershipPanel from '@/pages/Admin/UserManagement/components/RootOwnershipPanel.vue';

const props = defineProps({
    users: { type: Array, default: () => [] },
    roleOptions: { type: Array, default: () => [] },
    canManageAdmins: { type: Boolean, default: false },
    stats: { type: Object, default: () => ({}) },
    rootOwnership: { type: Object, required: true },
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);
const editingId = ref(null);
const passwordResetUser = ref(null);
const passwordResetSuccess = ref(null);
const showPassword = ref(false);
const showPasswordConfirmation = ref(false);
const passwordTouched = ref(false);
const confirmationTouched = ref(false);

const form = useForm({
    name: '',
    email: '',
    phone: '',
    role: props.roleOptions[0]?.value || 'clinic',
    password: '',
    password_confirmation: '',
});
const passwordResetForm = useForm({});
const passwordRequirements = computed(() =>
    evaluatePasswordRequirements(form.password),
);
const passwordRequirementsMet = computed(() =>
    meetsPasswordRequirements(form.password),
);
const passwordRequired = computed(
    () => !editingId.value || form.password.length > 0,
);
const passwordFeedback = computed(() => {
    if (form.errors.password) return form.errors.password;
    if (!passwordTouched.value || !form.password) return '';

    return isPasswordTooShort(form.password)
        ? PASSWORD_LENGTH_ERROR
        : passwordRequirementsMet.value
          ? ''
          : 'Password must meet all requirements below.';
});
const confirmationMismatch = computed(
    () =>
        form.password_confirmation.length > 0 &&
        form.password_confirmation !== form.password,
);
const confirmationFeedback = computed(() => {
    if (form.errors.password_confirmation)
        return form.errors.password_confirmation;
    if (!confirmationTouched.value || !passwordRequired.value) return '';

    return !form.password_confirmation
        ? 'Confirm the password.'
        : confirmationMismatch.value
          ? 'Passwords do not match yet.'
          : '';
});
const passwordStrength = computed(() => {
    if (!form.password) {
        return {
            label: 'Enter a password',
            widthClass: 'w-0',
            colorClass: 'bg-slate-300',
            value: 0,
        };
    }

    const metCount = passwordRequirements.value.filter(
        (requirement) => requirement.met,
    ).length;
    const levels = [
        { label: 'Weak', widthClass: 'w-1/4', colorClass: 'bg-red-500' },
        { label: 'Fair', widthClass: 'w-2/4', colorClass: 'bg-amber-500' },
        { label: 'Strong', widthClass: 'w-3/4', colorClass: 'bg-blue-500' },
        {
            label: 'Very strong',
            widthClass: 'w-full',
            colorClass: 'bg-emerald-600',
        },
    ];

    return {
        ...levels[Math.max(0, metCount - 1)],
        value: metCount,
    };
});
const passwordFormInvalid = computed(
    () =>
        passwordRequired.value &&
        (!passwordRequirementsMet.value ||
            !form.password_confirmation ||
            confirmationMismatch.value),
);

const onPasswordInput = () => {
    passwordTouched.value = true;
    form.clearErrors('password', 'password_confirmation');
};

const onConfirmationInput = () => {
    confirmationTouched.value = true;
    form.clearErrors('password_confirmation');
};

const statCards = computed(() => [
    {
        label: 'Admins',
        value: props.stats.admins || 0,
        helper: `${props.stats.rootAdmins || 0} root`,
        icon: ShieldCheck,
        tone: 'bg-indigo-50 text-indigo-600',
    },
    {
        label: 'Clinic',
        value: props.stats.clinic || 0,
        helper: 'Clinic users',
        icon: UsersRound,
        tone: 'bg-rose-50 text-rose-600',
    },
    {
        label: 'Registrars',
        value: props.stats.registrars || 0,
        helper: 'Enrollment users',
        icon: KeyRound,
        tone: 'bg-emerald-50 text-emerald-600',
    },
]);

const roleLabels = {
    admin: 'Admin',
    clinic: 'Clinic',
    registrar: 'Registrar',
};

// @function roleLabel: Pinoproseso ang role label para sa User Management.
// @useIn roleLabel: resources/js/pages/Admin/UserManagement/UserManagementPage.vue template
const roleLabel = (role) =>
    roleLabels[String(role || '').toLowerCase()] ||
    String(role || '').replace('_', ' ');

// @function resetForm: Nire-reset ang form sa User Management flow.
// @useIn resetForm: resources/js/pages/Admin/UserManagement/UserManagementPage.vue template @click
const resetForm = () => {
    editingId.value = null;
    form.reset();
    form.role = props.roleOptions[0]?.value || 'clinic';
    showPassword.value = false;
    showPasswordConfirmation.value = false;
    passwordTouched.value = false;
    confirmationTouched.value = false;
};

// @function editUser: Pinoproseso ang edit user para sa User Management.
// @useIn editUser: resources/js/pages/Admin/UserManagement/UserManagementPage.vue template @click
const editUser = (user) => {
    editingId.value = user.id;
    form.name = user.name || '';
    form.email = user.email || '';
    form.phone = user.phone || '';
    form.role = user.role || props.roleOptions[0]?.value || 'clinic';
    form.password = '';
    form.password_confirmation = '';
    showPassword.value = false;
    showPasswordConfirmation.value = false;
    passwordTouched.value = false;
    confirmationTouched.value = false;
};

// @function submit: Isinusumite ang user management sa User Management flow.
// @useIn submit: resources/js/pages/Admin/UserManagement/UserManagementPage.vue template
const submit = () => {
    if (passwordRequired.value) {
        passwordTouched.value = true;
        confirmationTouched.value = true;
    }

    form.clearErrors('password', 'password_confirmation');

    if (passwordRequired.value && !passwordRequirementsMet.value) {
        form.setError(
            'password',
            isPasswordTooShort(form.password)
                ? PASSWORD_LENGTH_ERROR
                : 'Password must meet all requirements below.',
        );
        return;
    }

    if (
        passwordRequired.value &&
        (!form.password_confirmation || confirmationMismatch.value)
    ) {
        form.setError(
            'password_confirmation',
            !form.password_confirmation
                ? 'Confirm the password.'
                : 'Passwords do not match.',
        );
        return;
    }

    if (editingId.value) {
        form.put(route('admin.users.update', editingId.value), {
            preserveScroll: true,
            onSuccess: resetForm,
        });
        return;
    }

    form.post(route('admin.users.store'), {
        preserveScroll: true,
        onSuccess: resetForm,
    });
};

// @function defaultPassword: Pinoproseso ang default password para sa User Management.
// @useIn defaultPassword: resources/js/pages/Admin/UserManagement/UserManagementPage.vue template
const defaultPassword = (user) =>
    `${user.name ?? ''}${user.last_name ?? ''}`
        .replace(/\s+/g, '')
        .toLowerCase();

// @function openPasswordReset: Binubuksan ang password reset sa User Management flow.
// @useIn openPasswordReset: resources/js/pages/Admin/UserManagement/UserManagementPage.vue template @click
const openPasswordReset = (user) => {
    if (!user.can_reset_password) return;
    passwordResetForm.clearErrors();
    passwordResetUser.value = user;
};

// @function closePasswordReset: Isinasara ang password reset sa User Management flow.
// @useIn closePasswordReset: resources/js/pages/Admin/UserManagement/UserManagementPage.vue template @click
const closePasswordReset = () => {
    if (passwordResetForm.processing) return;
    passwordResetUser.value = null;
};

// @function confirmPasswordReset: Kinukuha ang confirm password reset result para sa User Management.
// @useIn confirmPasswordReset: resources/js/pages/Admin/UserManagement/UserManagementPage.vue template @click
const confirmPasswordReset = () => {
    const user = passwordResetUser.value;
    if (!user || passwordResetForm.processing) return;

    const password = defaultPassword(user);
    passwordResetForm.put(
        route('admin.users.password.reset-default', user.id),
        {
            preserveScroll: true,
            onSuccess: () => {
                passwordResetSuccess.value = {
                    name: user.name,
                    role: roleLabel(user.role),
                    password,
                };
                passwordResetUser.value = null;
            },
            onError: (errors) => {
                passwordResetForm.setError(
                    'reset',
                    Object.values(errors).flat().join(', ') ||
                        'The password could not be reset. Please try again.',
                );
            },
        },
    );
};

// @function deleteUser: Tinatanggal ang user sa User Management flow.
// @useIn deleteUser: resources/js/pages/Admin/UserManagement/UserManagementPage.vue template @click
const deleteUser = async (user) => {
    if (!user.can_delete) return;
    const confirmed = await confirmActionModal({
        title: 'Delete user?',
        text: `Delete ${user.email}?`,
    });
    if (!confirmed) return;

    router.delete(route('admin.users.destroy', user.id), {
        preserveScroll: true,
    });
};
</script>

<template>
    <div class="min-h-full bg-slate-100 p-4 text-slate-900 sm:p-6">
        <div class="mx-auto flex max-w-7xl flex-col gap-5">
            <header
                class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between"
            >
                <div>
                    <p
                        class="text-xs font-bold tracking-wide text-brand uppercase"
                    >
                        Admin Access
                    </p>
                    <h1 class="mt-1 text-2xl font-black text-slate-950">
                        User Management
                    </h1>
                    <p class="mt-1 text-sm text-slate-500">
                        Manage clinic, registrar, and admin accounts.
                    </p>
                </div>

                <div
                    class="inline-flex items-center gap-2 rounded-md border border-slate-200 bg-white px-3 py-2 text-sm font-bold text-slate-700 shadow-sm"
                >
                    <ShieldCheck class="h-4 w-4 text-brand" />
                    {{
                        canManageAdmins
                            ? 'Root admin access'
                            : 'Standard admin access'
                    }}
                </div>
            </header>

            <div
                v-if="flashSuccess"
                class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700"
            >
                {{ flashSuccess }}
            </div>

            <RootOwnershipPanel :ownership="rootOwnership" />

            <section class="grid gap-3 md:grid-cols-3">
                <article
                    v-for="card in statCards"
                    :key="card.label"
                    class="rounded-md border border-slate-200 bg-white p-4 shadow-sm"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-3xl font-black text-slate-950">
                                {{ card.value }}
                            </p>
                            <p class="mt-1 text-sm font-bold text-slate-700">
                                {{ card.label }}
                            </p>
                            <p
                                class="mt-2 text-xs font-semibold text-slate-400"
                            >
                                {{ card.helper }}
                            </p>
                        </div>
                        <div
                            class="flex h-10 w-10 items-center justify-center rounded-md"
                            :class="card.tone"
                        >
                            <component :is="card.icon" class="h-5 w-5" />
                        </div>
                    </div>
                </article>
            </section>

            <section class="grid gap-5 xl:grid-cols-[360px_1fr]">
                <form
                    class="rounded-md border border-slate-200 bg-white p-4 shadow-sm"
                    @submit.prevent="submit"
                >
                    <div class="mb-4 flex items-center justify-between">
                        <div>
                            <h2 class="font-black text-slate-950">
                                {{
                                    editingId
                                        ? 'Edit Account'
                                        : 'Create Account'
                                }}
                            </h2>
                            <p class="text-sm text-slate-500">
                                {{
                                    editingId
                                        ? 'Leave the password blank to keep the current one.'
                                        : 'Create a secure temporary password for the new account.'
                                }}
                            </p>
                        </div>
                        <UserPlus class="h-5 w-5 text-brand" />
                    </div>

                    <div class="space-y-3">
                        <label class="block">
                            <span class="text-xs font-bold text-slate-500"
                                >Name</span
                            >
                            <input
                                v-model="form.name"
                                type="text"
                                class="mt-1 w-full rounded-md border border-slate-200 px-3 py-2 text-sm outline-none focus:border-brand"
                                autocomplete="name"
                            />
                            <span
                                v-if="form.errors.name"
                                class="mt-1 block text-xs font-semibold text-rose-600"
                            >
                                {{ form.errors.name }}
                            </span>
                        </label>

                        <label class="block">
                            <span class="text-xs font-bold text-slate-500"
                                >Email</span
                            >
                            <input
                                v-model="form.email"
                                type="email"
                                class="mt-1 w-full rounded-md border border-slate-200 px-3 py-2 text-sm outline-none focus:border-brand"
                                autocomplete="email"
                            />
                            <span
                                v-if="form.errors.email"
                                class="mt-1 block text-xs font-semibold text-rose-600"
                            >
                                {{ form.errors.email }}
                            </span>
                        </label>

                        <label class="block">
                            <span class="text-xs font-bold text-slate-500"
                                >Phone</span
                            >
                            <input
                                v-model="form.phone"
                                type="text"
                                class="mt-1 w-full rounded-md border border-slate-200 px-3 py-2 text-sm outline-none focus:border-brand"
                                autocomplete="tel"
                            />
                        </label>

                        <label class="block">
                            <span class="text-xs font-bold text-slate-500"
                                >Role</span
                            >
                            <select
                                v-model="form.role"
                                class="mt-1 w-full rounded-md border border-slate-200 px-3 py-2 text-sm outline-none focus:border-brand"
                            >
                                <option
                                    v-for="option in roleOptions"
                                    :key="option.value"
                                    :value="option.value"
                                >
                                    {{ option.label }}
                                </option>
                            </select>
                            <span
                                v-if="form.errors.role"
                                class="mt-1 block text-xs font-semibold text-rose-600"
                            >
                                {{ form.errors.role }}
                            </span>
                        </label>

                        <div class="block">
                            <label
                                for="managed-user-password"
                                class="text-xs font-bold text-slate-500"
                            >
                                {{ editingId ? 'New Password' : 'Password' }}
                            </label>
                            <div class="relative mt-1">
                                <input
                                    id="managed-user-password"
                                    v-model="form.password"
                                    :type="showPassword ? 'text' : 'password'"
                                    :required="!editingId"
                                    :minlength="MIN_PASSWORD_LENGTH"
                                    aria-describedby="managed-user-password-requirements managed-user-password-error"
                                    :aria-invalid="Boolean(passwordFeedback)"
                                    class="w-full rounded-md border px-3 py-2 pr-10 text-sm outline-none focus:ring-2"
                                    :class="
                                        passwordFeedback
                                            ? 'border-rose-400 focus:border-rose-500 focus:ring-rose-100'
                                            : 'border-slate-200 focus:border-brand focus:ring-blue-100'
                                    "
                                    autocomplete="new-password"
                                    @input="onPasswordInput"
                                />
                                <button
                                    type="button"
                                    class="absolute inset-y-0 right-0 inline-flex w-10 items-center justify-center text-slate-500 hover:text-slate-700 focus-visible:ring-2 focus-visible:ring-brand focus-visible:outline-none"
                                    :aria-label="
                                        showPassword
                                            ? 'Hide password'
                                            : 'Show password'
                                    "
                                    :aria-pressed="showPassword"
                                    @click="showPassword = !showPassword"
                                >
                                    <EyeOff
                                        v-if="showPassword"
                                        class="h-4 w-4"
                                        aria-hidden="true"
                                    />
                                    <Eye
                                        v-else
                                        class="h-4 w-4"
                                        aria-hidden="true"
                                    />
                                </button>
                            </div>
                            <div
                                v-if="passwordRequired"
                                id="managed-user-password-requirements"
                                class="mt-3 rounded-lg border border-slate-200 bg-slate-50 p-3"
                            >
                                <div
                                    class="flex items-center justify-between gap-3 text-xs font-semibold"
                                >
                                    <span class="text-slate-600"
                                        >Password strength</span
                                    >
                                    <span
                                        :class="
                                            passwordStrength.value === 4
                                                ? 'text-emerald-700'
                                                : 'text-slate-700'
                                        "
                                        aria-live="polite"
                                    >
                                        {{ passwordStrength.label }}
                                    </span>
                                </div>
                                <div
                                    class="mt-2 h-2 overflow-hidden rounded-full bg-slate-200"
                                    role="progressbar"
                                    aria-label="Password strength"
                                    :aria-valuenow="passwordStrength.value"
                                    aria-valuemin="0"
                                    aria-valuemax="4"
                                >
                                    <div
                                        class="h-full rounded-full transition-all duration-300"
                                        :class="[
                                            passwordStrength.widthClass,
                                            passwordStrength.colorClass,
                                        ]"
                                    ></div>
                                </div>
                                <p
                                    class="mt-3 text-xs font-semibold text-slate-700"
                                >
                                    Password must contain:
                                </p>
                                <ul class="mt-2 space-y-1.5">
                                    <li
                                        v-for="requirement in passwordRequirements"
                                        :key="requirement.key"
                                        class="flex items-center gap-2 text-xs"
                                        :class="
                                            requirement.met
                                                ? 'text-emerald-700'
                                                : 'text-slate-600'
                                        "
                                    >
                                        <span
                                            class="flex h-4 w-4 shrink-0 items-center justify-center rounded-full"
                                            :class="
                                                requirement.met
                                                    ? 'bg-emerald-600 text-white'
                                                    : 'border border-slate-300 bg-white text-transparent'
                                            "
                                            aria-hidden="true"
                                        >
                                            <Check class="h-3 w-3" />
                                        </span>
                                        {{ requirement.label }}
                                    </li>
                                </ul>
                            </div>
                            <div
                                v-if="passwordFeedback"
                                id="managed-user-password-error"
                                class="mt-2 rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-semibold text-rose-700"
                                role="alert"
                            >
                                {{ passwordFeedback }}
                            </div>
                        </div>

                        <div class="block">
                            <label
                                for="managed-user-password-confirmation"
                                class="text-xs font-bold text-slate-500"
                            >
                                {{
                                    editingId
                                        ? 'Confirm New Password'
                                        : 'Confirm Password'
                                }}
                            </label>
                            <div class="relative mt-1">
                                <input
                                    id="managed-user-password-confirmation"
                                    v-model="form.password_confirmation"
                                    :type="
                                        showPasswordConfirmation
                                            ? 'text'
                                            : 'password'
                                    "
                                    class="w-full rounded-md border border-slate-200 px-3 py-2 pr-10 text-sm outline-none focus:border-brand"
                                    autocomplete="new-password"
                                    :required="passwordRequired"
                                    :aria-invalid="
                                        Boolean(confirmationFeedback)
                                    "
                                    aria-describedby="managed-user-password-confirmation-error"
                                    @input="onConfirmationInput"
                                />
                                <button
                                    type="button"
                                    class="absolute inset-y-0 right-0 inline-flex w-10 items-center justify-center text-slate-500 hover:text-slate-700 focus-visible:ring-2 focus-visible:ring-brand focus-visible:outline-none"
                                    :aria-label="
                                        showPasswordConfirmation
                                            ? 'Hide password confirmation'
                                            : 'Show password confirmation'
                                    "
                                    :aria-pressed="showPasswordConfirmation"
                                    @click="
                                        showPasswordConfirmation =
                                            !showPasswordConfirmation
                                    "
                                >
                                    <EyeOff
                                        v-if="showPasswordConfirmation"
                                        class="h-4 w-4"
                                        aria-hidden="true"
                                    />
                                    <Eye
                                        v-else
                                        class="h-4 w-4"
                                        aria-hidden="true"
                                    />
                                </button>
                            </div>
                            <span
                                v-if="confirmationFeedback"
                                id="managed-user-password-confirmation-error"
                                class="mt-1 block text-xs font-semibold text-rose-600"
                                role="alert"
                            >
                                {{ confirmationFeedback }}
                            </span>
                        </div>
                    </div>

                    <div class="mt-5 flex gap-2">
                        <button
                            type="submit"
                            :disabled="form.processing || passwordFormInvalid"
                            class="inline-flex flex-1 items-center justify-center gap-2 rounded-md bg-brand px-3 py-2 text-sm font-black text-white shadow-sm disabled:opacity-60"
                        >
                            <UserPlus class="h-4 w-4" />
                            {{ editingId ? 'Save' : 'Create' }}
                        </button>
                        <button
                            v-if="editingId"
                            type="button"
                            class="rounded-md border border-slate-200 px-3 py-2 text-sm font-bold text-slate-600"
                            @click="resetForm"
                        >
                            Cancel
                        </button>
                    </div>
                </form>

                <div
                    class="rounded-md border border-slate-200 bg-white shadow-sm"
                >
                    <div
                        class="flex flex-col gap-2 border-b border-slate-100 p-4 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div>
                            <h2 class="font-black text-slate-950">
                                Managed Accounts
                            </h2>
                            <p class="text-sm text-slate-500">
                                Admin accounts are protected unless you are
                                root.
                            </p>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[860px] text-left text-sm">
                            <thead
                                class="bg-slate-50 text-xs text-slate-500 uppercase"
                            >
                                <tr>
                                    <th class="px-4 py-3">User</th>
                                    <th class="px-4 py-3">Role</th>
                                    <th class="px-4 py-3">Phone</th>
                                    <th class="px-4 py-3">Created</th>
                                    <th class="px-4 py-3 text-right">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="user in users" :key="user.id">
                                    <td class="px-4 py-3">
                                        <div class="font-bold text-slate-900">
                                            {{ user.name }}
                                        </div>
                                        <div class="text-xs text-slate-400">
                                            {{ user.email }}
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span
                                            class="inline-flex items-center gap-1 rounded-md px-2 py-1 text-xs font-black"
                                            :class="
                                                user.role === 'admin'
                                                    ? 'bg-indigo-50 text-indigo-700'
                                                    : user.role === 'clinic'
                                                      ? 'bg-rose-50 text-rose-700'
                                                      : 'bg-emerald-50 text-emerald-700'
                                            "
                                        >
                                            {{ roleLabel(user.role) }}
                                            <ShieldCheck
                                                v-if="user.is_root_admin"
                                                class="h-3.5 w-3.5"
                                            />
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-slate-600">
                                        {{ user.phone || 'Not set' }}
                                    </td>
                                    <td class="px-4 py-3 text-slate-500">
                                        {{ user.created_at || 'Not set' }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex justify-end gap-2">
                                            <div
                                                v-if="user.can_reset_password"
                                                class="group relative"
                                            >
                                                <button
                                                    type="button"
                                                    class="inline-flex h-9 w-9 items-center justify-center rounded-md border border-amber-200 text-amber-600 hover:bg-amber-50 focus:ring-2 focus:ring-amber-200 focus:outline-none"
                                                    aria-label="Reset password"
                                                    @click="
                                                        openPasswordReset(user)
                                                    "
                                                >
                                                    <KeyRound class="h-4 w-4" />
                                                </button>
                                                <span
                                                    role="tooltip"
                                                    class="pointer-events-none absolute bottom-full left-1/2 z-20 mb-2 -translate-x-1/2 rounded bg-slate-900 px-2 py-1 text-[11px] font-medium whitespace-nowrap text-white opacity-0 transition-opacity duration-150 group-focus-within:opacity-100 group-hover:opacity-100"
                                                >
                                                    Reset password
                                                </span>
                                            </div>
                                            <button
                                                type="button"
                                                :disabled="!user.can_update"
                                                class="inline-flex h-9 w-9 items-center justify-center rounded-md border border-slate-200 text-slate-600 disabled:cursor-not-allowed disabled:opacity-40"
                                                title="Edit user"
                                                @click="editUser(user)"
                                            >
                                                <Edit3 class="h-4 w-4" />
                                            </button>
                                            <button
                                                type="button"
                                                :disabled="!user.can_delete"
                                                class="inline-flex h-9 w-9 items-center justify-center rounded-md border border-rose-200 text-rose-600 disabled:cursor-not-allowed disabled:opacity-40"
                                                title="Delete user"
                                                @click="deleteUser(user)"
                                            >
                                                <Trash2 class="h-4 w-4" />
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="users.length === 0">
                                    <td
                                        colspan="5"
                                        class="px-4 py-10 text-center text-sm font-semibold text-slate-400"
                                    >
                                        No managed accounts yet.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        </div>

        <div
            v-if="passwordResetUser"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
            role="presentation"
            @click.self="closePasswordReset"
        >
            <div
                class="w-full max-w-md rounded-lg bg-white p-6 shadow-xl"
                role="dialog"
                aria-modal="true"
                aria-labelledby="managed-user-reset-title"
            >
                <h2
                    id="managed-user-reset-title"
                    class="text-xl font-black text-slate-950"
                >
                    Reset {{ roleLabel(passwordResetUser.role) }} password?
                </h2>
                <p class="mt-3 text-sm leading-6 text-slate-600">
                    Reset {{ passwordResetUser.name }}'s password to
                    <strong>{{ defaultPassword(passwordResetUser) }}</strong
                    >? Their active sessions will end, and they must create a
                    private password at the next login.
                </p>
                <p
                    v-if="passwordResetForm.errors.reset"
                    class="mt-3 rounded-md bg-rose-50 p-3 text-sm text-rose-700"
                    role="alert"
                >
                    {{ passwordResetForm.errors.reset }}
                </p>
                <div class="mt-6 flex justify-end gap-3">
                    <button
                        type="button"
                        class="rounded-md border border-slate-300 px-4 py-2 text-sm font-bold text-slate-700 hover:bg-slate-50 disabled:opacity-60"
                        :disabled="passwordResetForm.processing"
                        @click="closePasswordReset"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        class="rounded-md bg-amber-600 px-4 py-2 text-sm font-bold text-white hover:bg-amber-700 disabled:opacity-60"
                        :disabled="passwordResetForm.processing"
                        @click="confirmPasswordReset"
                    >
                        {{
                            passwordResetForm.processing
                                ? 'Resetting...'
                                : 'Reset password'
                        }}
                    </button>
                </div>
            </div>
        </div>

        <div
            v-if="passwordResetSuccess"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
            role="presentation"
        >
            <div
                class="w-full max-w-md rounded-lg bg-white p-6 text-center shadow-xl"
                role="dialog"
                aria-modal="true"
                aria-labelledby="managed-user-reset-success-title"
            >
                <div
                    class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-emerald-100 text-emerald-700"
                >
                    <ShieldCheck class="h-8 w-8" aria-hidden="true" />
                </div>
                <h2
                    id="managed-user-reset-success-title"
                    class="mt-4 text-xl font-black text-slate-950"
                >
                    {{ passwordResetSuccess.role }} password reset
                </h2>
                <p class="mt-3 text-sm leading-6 text-slate-600">
                    {{ passwordResetSuccess.name }}'s temporary password is
                    <strong>{{ passwordResetSuccess.password }}</strong
                    >. They must create a private password at the next login.
                </p>
                <button
                    type="button"
                    class="mt-6 rounded-md bg-emerald-600 px-5 py-2 text-sm font-bold text-white hover:bg-emerald-700"
                    @click="passwordResetSuccess = null"
                >
                    OK
                </button>
            </div>
        </div>
    </div>
</template>
