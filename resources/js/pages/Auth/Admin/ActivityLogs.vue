<script setup>
import { Head, router } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';

const props = defineProps({
    logs: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    options: { type: Object, default: () => ({}) },
});

const emptyFilters = {
    search: '',
    date_from: '',
    date_to: '',
    module: '',
    action: '',
    user: '',
    user_role: '',
    outcome: '',
    severity: '',
    subject_type: '',
    subject_id: '',
    ip_address: '',
    academic_year_id: '',
    semester: '',
};
const form = reactive({ ...emptyFilters, ...props.filters });
const activeFilterCount = computed(
    () => Object.values(form).filter(Boolean).length,
);

const applyFilters = () =>
    router.get(route('admin.activity-logs.index'), form, {
        preserveState: true,
        preserveScroll: true,
    });
const resetFilters = () => {
    Object.assign(form, emptyFilters);
    applyFilters();
};
const exportCsv = () => {
    const query = new URLSearchParams(
        Object.entries(form).filter(([, value]) => value !== ''),
    ).toString();
    window.location.href = `${route('admin.activity-logs.export')}?${query}`;
};
const titleCase = (value) =>
    String(value || '-')
        .replaceAll('_', ' ')
        .replace(/\b\w/g, (letter) => letter.toUpperCase());
</script>

<template>
    <Head title="System Activity Logs" />
    <div class="p-4 sm:p-6">
        <section
            class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm"
        >
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h1 class="text-xl font-bold text-slate-900">
                        System Activity Logs
                    </h1>
                    <p class="mt-1 text-sm text-slate-500">
                        Admin-only, system-wide audit trail of changes and
                        operational actions.
                    </p>
                </div>
                <button
                    class="rounded-md bg-brand px-4 py-2 text-sm font-bold text-white"
                    @click="exportCsv"
                >
                    Export CSV
                </button>
            </div>

            <form class="mt-5 space-y-3" @submit.prevent="applyFilters">
                <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                    <label
                        class="text-xs font-semibold text-slate-600 xl:col-span-2"
                        >Search
                        <input
                            v-model="form.search"
                            placeholder="Description, action, user, email, event ID, or route"
                            class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                        />
                    </label>
                    <label class="text-xs font-semibold text-slate-600"
                        >From date
                        <input
                            v-model="form.date_from"
                            type="date"
                            class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                        />
                    </label>
                    <label class="text-xs font-semibold text-slate-600"
                        >To date
                        <input
                            v-model="form.date_to"
                            type="date"
                            class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                        />
                    </label>
                </div>
                <div class="grid gap-3 md:grid-cols-3 xl:grid-cols-6">
                    <label class="text-xs font-semibold text-slate-600">Academic year
                        <select v-model="form.academic_year_id" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                            <option value="">All academic years</option>
                            <option v-for="year in options.academicYears" :key="year.academic_year_id" :value="year.academic_year_id">{{ year.name }} ({{ year.status }})</option>
                        </select>
                    </label>
                    <label class="text-xs font-semibold text-slate-600">Semester
                        <select v-model="form.semester" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                            <option value="">All semesters</option>
                            <option value="1st Semester">1st Semester</option>
                            <option value="2nd Semester">2nd Semester</option>
                        </select>
                    </label>
                    <label class="text-xs font-semibold text-slate-600"
                        >Module
                        <select
                            v-model="form.module"
                            class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                        >
                            <option value="">All modules</option>
                            <option
                                v-for="item in options.modules"
                                :key="item"
                                :value="item"
                            >
                                {{ titleCase(item) }}
                            </option>
                        </select>
                    </label>
                    <label class="text-xs font-semibold text-slate-600"
                        >Action
                        <select
                            v-model="form.action"
                            class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                        >
                            <option value="">All actions</option>
                            <option
                                v-for="item in options.actions"
                                :key="item"
                                :value="item"
                            >
                                {{ titleCase(item) }}
                            </option>
                        </select>
                    </label>
                    <label class="text-xs font-semibold text-slate-600"
                        >Role
                        <select
                            v-model="form.user_role"
                            class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                        >
                            <option value="">All roles</option>
                            <option
                                v-for="item in options.roles"
                                :key="item"
                                :value="item"
                            >
                                {{ titleCase(item) }}
                            </option>
                        </select>
                    </label>
                    <label class="text-xs font-semibold text-slate-600"
                        >Outcome
                        <select
                            v-model="form.outcome"
                            class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                        >
                            <option value="">All outcomes</option>
                            <option
                                v-for="item in options.outcomes"
                                :key="item"
                                :value="item"
                            >
                                {{ titleCase(item) }}
                            </option>
                        </select>
                    </label>
                    <label class="text-xs font-semibold text-slate-600"
                        >Severity
                        <select
                            v-model="form.severity"
                            class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                        >
                            <option value="">All severities</option>
                            <option
                                v-for="item in options.severities"
                                :key="item"
                                :value="item"
                            >
                                {{ titleCase(item) }}
                            </option>
                        </select>
                    </label>
                    <label class="text-xs font-semibold text-slate-600"
                        >User ID
                        <input
                            v-model="form.user"
                            inputmode="numeric"
                            placeholder="Any user"
                            class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                        />
                    </label>
                </div>
                <details class="rounded-md border border-slate-200 p-3">
                    <summary
                        class="cursor-pointer text-sm font-semibold text-slate-700"
                    >
                        Advanced filters
                    </summary>
                    <div class="mt-3 grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                        <label class="text-xs font-semibold text-slate-600"
                            >Affected record type
                            <select
                                v-model="form.subject_type"
                                class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                            >
                                <option value="">All record types</option>
                                <option
                                    v-for="item in options.subjectTypes"
                                    :key="item"
                                    :value="item"
                                >
                                    {{ titleCase(item) }}
                                </option>
                            </select>
                        </label>
                        <label class="text-xs font-semibold text-slate-600"
                            >Affected record ID
                            <input
                                v-model="form.subject_id"
                                placeholder="Any record"
                                class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                            />
                        </label>
                        <label class="text-xs font-semibold text-slate-600"
                            >IP address
                            <input
                                v-model="form.ip_address"
                                placeholder="Exact IP address"
                                class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                            />
                        </label>
                    </div>
                </details>
                <div class="flex flex-wrap items-center gap-2">
                    <button
                        class="rounded-md bg-slate-900 px-4 py-2 text-sm font-bold text-white"
                    >
                        Apply filters
                    </button>
                    <button
                        type="button"
                        class="rounded-md border border-slate-300 px-4 py-2 text-sm font-bold text-slate-700"
                        @click="resetFilters"
                    >
                        Reset
                    </button>
                    <span
                        v-if="activeFilterCount"
                        class="text-xs text-slate-500"
                        >{{ activeFilterCount }} active filter{{
                            activeFilterCount === 1 ? '' : 's'
                        }}</span
                    >
                </div>
            </form>

            <div
                class="mt-5 overflow-x-auto rounded-md border border-slate-200"
            >
                <table class="w-full min-w-[1100px] text-left text-sm">
                    <thead class="bg-slate-50 text-xs text-slate-500 uppercase">
                        <tr>
                            <th class="px-3 py-2">Timestamp</th>
                            <th class="px-3 py-2">Actor</th>
                            <th class="px-3 py-2">Module / Action</th>
                            <th class="px-3 py-2">Outcome</th>
                            <th class="px-3 py-2">Affected record</th>
                            <th class="px-3 py-2">Request</th>
                            <th class="px-3 py-2">Description</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr
                            v-for="log in logs.data"
                            :key="log.logs_id"
                            class="align-top hover:bg-slate-50"
                        >
                            <td
                                class="px-3 py-3 text-xs whitespace-nowrap text-slate-600"
                            >
                                {{ log.created_at }}
                            </td>
                            <td class="px-3 py-3">
                                <div class="font-semibold text-slate-900">
                                    {{
                                        log.user?.name ||
                                        log.user_name ||
                                        'System / Guest'
                                    }}
                                </div>
                                <div class="text-xs text-slate-500">
                                    {{ titleCase(log.user_role)
                                    }}<span v-if="log.user_id">
                                        · #{{ log.user_id }}</span
                                    >
                                </div>
                            </td>
                            <td class="px-3 py-3">
                                <div class="font-semibold text-slate-900">
                                    {{
                                        titleCase(log.module || log.table_name)
                                    }}
                                </div>
                                <div class="text-xs text-slate-500">
                                    {{ titleCase(log.action) }}
                                </div>
                            </td>
                            <td class="px-3 py-3">
                                <span
                                    class="rounded-full px-2 py-1 text-xs font-bold"
                                    :class="
                                        log.outcome === 'failure'
                                            ? 'bg-red-100 text-red-700'
                                            : 'bg-emerald-100 text-emerald-700'
                                    "
                                    >{{
                                        titleCase(log.outcome || 'success')
                                    }}</span
                                >
                                <div class="mt-1 text-xs text-slate-500">
                                    {{ titleCase(log.severity || 'info') }}
                                </div>
                            </td>
                            <td class="px-3 py-3 text-xs text-slate-600">
                                {{ titleCase(log.subject_type)
                                }}<span v-if="log.subject_id">
                                    #{{ log.subject_id }}</span
                                >
                            </td>
                            <td class="px-3 py-3 text-xs text-slate-600">
                                <div>
                                    {{ log.http_method || '-' }}
                                    <span v-if="log.status_code"
                                        >· {{ log.status_code }}</span
                                    >
                                </div>
                                <div>{{ log.ip_address || '-' }}</div>
                                <div
                                    class="max-w-44 truncate"
                                    :title="log.route_name"
                                >
                                    {{ log.route_name || '-' }}
                                </div>
                            </td>
                            <td class="max-w-sm px-3 py-3 text-slate-600">
                                <p>{{ log.description || '-' }}</p>
                                <p
                                    v-if="log.event_id"
                                    class="mt-1 truncate text-[11px] text-slate-400"
                                    :title="log.event_id"
                                >
                                    Event {{ log.event_id }}
                                </p>
                            </td>
                        </tr>
                        <tr v-if="logs.data.length === 0">
                            <td
                                colspan="7"
                                class="px-3 py-10 text-center text-slate-400"
                            >
                                No activity logs match these filters.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="mt-4 flex flex-wrap gap-2">
                <button
                    v-for="link in logs.links"
                    :key="link.label"
                    :disabled="!link.url"
                    class="rounded-md border border-slate-300 px-3 py-1 text-xs font-bold disabled:opacity-40"
                    :class="{ 'bg-brand text-white': link.active }"
                    v-html="link.label"
                    @click="
                        link.url &&
                        router.visit(link.url, { preserveScroll: true })
                    "
                />
            </div>
        </section>
    </div>
</template>
