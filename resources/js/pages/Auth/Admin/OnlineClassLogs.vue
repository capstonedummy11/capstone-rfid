<script setup>
import { router } from '@inertiajs/vue3';
import { reactive } from 'vue';

const props = defineProps({
    logs: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
});

const form = reactive({
    search: props.filters.search || '',
    date_from: props.filters.date_from || '',
    date_to: props.filters.date_to || '',
    instructor: props.filters.instructor || '',
    user: props.filters.user || '',
    user_role: props.filters.user_role || '',
    section: props.filters.section || '',
    action: props.filters.action || '',
});

const applyFilters = () => {
    router.get(route('admin.online-class-logs.index'), form, { preserveState: true, preserveScroll: true });
};

const exportCsv = () => {
    window.location.href = route('admin.online-class-logs.export', form);
};
</script>

<template>
    <div class="p-4 sm:p-6">
        <section class="rounded-md border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h1 class="text-xl font-bold text-slate-900">Online Class Logs</h1>
                <button class="rounded-md bg-brand px-4 py-2 text-sm font-bold text-white" @click="exportCsv">Export CSV</button>
            </div>

            <form class="mt-4 grid gap-3 md:grid-cols-4 lg:grid-cols-8" @submit.prevent="applyFilters">
                <input v-model="form.search" placeholder="Search" class="rounded-md border border-slate-300 px-3 py-2 text-sm" />
                <input v-model="form.date_from" type="date" class="rounded-md border border-slate-300 px-3 py-2 text-sm" />
                <input v-model="form.date_to" type="date" class="rounded-md border border-slate-300 px-3 py-2 text-sm" />
                <input v-model="form.instructor" placeholder="Instructor ID" class="rounded-md border border-slate-300 px-3 py-2 text-sm" />
                <input v-model="form.user" placeholder="User ID" class="rounded-md border border-slate-300 px-3 py-2 text-sm" />
                <input v-model="form.user_role" placeholder="Role" class="rounded-md border border-slate-300 px-3 py-2 text-sm" />
                <input v-model="form.section" placeholder="Section ID" class="rounded-md border border-slate-300 px-3 py-2 text-sm" />
                <input v-model="form.action" placeholder="Action" class="rounded-md border border-slate-300 px-3 py-2 text-sm" />
                <button class="rounded-md border border-slate-300 px-3 py-2 text-sm font-bold text-slate-700">Filter</button>
            </form>

            <div class="mt-4 overflow-x-auto">
                <table class="w-full min-w-[860px] text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-3 py-2">Timestamp</th>
                            <th class="px-3 py-2">User</th>
                            <th class="px-3 py-2">Role</th>
                            <th class="px-3 py-2">Action</th>
                            <th class="px-3 py-2">Class ID</th>
                            <th class="px-3 py-2">Section</th>
                            <th class="px-3 py-2">IP</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="log in logs.data" :key="log.online_class_audit_log_id">
                            <td class="px-3 py-3">{{ log.created_at }}</td>
                            <td class="px-3 py-3">{{ log.user?.name || 'System' }}</td>
                            <td class="px-3 py-3">{{ log.user_role || '-' }}</td>
                            <td class="px-3 py-3 font-semibold text-slate-900">{{ log.action }}</td>
                            <td class="px-3 py-3">{{ log.online_class_id || '-' }}</td>
                            <td class="px-3 py-3">{{ log.section?.section_name || '-' }}</td>
                            <td class="px-3 py-3">{{ log.ip_address || '-' }}</td>
                        </tr>
                        <tr v-if="logs.data.length === 0">
                            <td colspan="7" class="px-3 py-8 text-center text-slate-400">No logs found.</td>
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
                    @click="link.url && router.visit(link.url, { preserveScroll: true })"
                />
            </div>
        </section>
    </div>
</template>
