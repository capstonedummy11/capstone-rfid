<script setup>
import { router, useForm, usePage } from '@inertiajs/vue3';
import {
    Edit3,
    KeyRound,
    ShieldCheck,
    Trash2,
    UserPlus,
    UsersRound,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps({
    users: { type: Array, default: () => [] },
    roleOptions: { type: Array, default: () => [] },
    canManageAdmins: { type: Boolean, default: false },
    stats: { type: Object, default: () => ({}) },
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);
const editingId = ref(null);

const form = useForm({
    name: '',
    email: '',
    phone: '',
    role: props.roleOptions[0]?.value || 'clinic',
    password: '',
    is_root_admin: false,
});

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

const roleLabel = (role) =>
    roleLabels[String(role || '').toLowerCase()] ||
    String(role || '').replace('_', ' ');

const resetForm = () => {
    editingId.value = null;
    form.reset();
    form.role = props.roleOptions[0]?.value || 'clinic';
    form.is_root_admin = false;
};

const editUser = (user) => {
    editingId.value = user.id;
    form.name = user.name || '';
    form.email = user.email || '';
    form.phone = user.phone || '';
    form.role = user.role || props.roleOptions[0]?.value || 'clinic';
    form.password = '';
    form.is_root_admin = Boolean(user.is_root_admin);
};

const submit = () => {
    if (form.role !== 'admin') {
        form.is_root_admin = false;
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

const deleteUser = (user) => {
    if (!user.can_delete) return;
    if (!confirm(`Delete ${user.email}?`)) return;

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
                                Password is required for new accounts.
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

                        <label
                            v-if="canManageAdmins && form.role === 'admin'"
                            class="flex items-center gap-2 rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-sm font-bold text-slate-700"
                        >
                            <input
                                v-model="form.is_root_admin"
                                type="checkbox"
                                class="h-4 w-4 rounded border-slate-300 text-brand"
                            />
                            Root admin
                        </label>

                        <label class="block">
                            <span class="text-xs font-bold text-slate-500">
                                {{ editingId ? 'New Password' : 'Password' }}
                            </span>
                            <input
                                v-model="form.password"
                                type="password"
                                class="mt-1 w-full rounded-md border border-slate-200 px-3 py-2 text-sm outline-none focus:border-brand"
                                autocomplete="new-password"
                            />
                            <span
                                v-if="form.errors.password"
                                class="mt-1 block text-xs font-semibold text-rose-600"
                            >
                                {{ form.errors.password }}
                            </span>
                        </label>
                    </div>

                    <div class="mt-5 flex gap-2">
                        <button
                            type="submit"
                            :disabled="form.processing"
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
    </div>
</template>
