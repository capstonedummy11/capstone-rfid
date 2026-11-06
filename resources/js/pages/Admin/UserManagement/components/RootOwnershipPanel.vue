<!-- FEATURE:root-ownership - UI para sa ownership transfer and override. -->
<script setup>
import { router, useForm } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { ShieldAlert, ShieldCheck } from 'lucide-vue-next';
import { confirmActionModal } from '@/lib/feedbackModal';

const props = defineProps({ ownership: { type: Object, required: true } });
const now = ref(Date.now());
let timer;
onMounted(() => {
    timer = window.setInterval(() => (now.value = Date.now()), 1000);
});
onBeforeUnmount(() => window.clearInterval(timer));

const transfer = useForm({
    to_user_id: '',
    password: '',
    two_factor_code: '',
    confirmed: false,
});
const override = useForm({ to_user_id: '', reason: '' });
const decision = useForm({ decision: '', comment: '' });
const showReauth = ref(false);
const responseMessage = ref('');
const query = new URLSearchParams(window.location.search);
const auditFilters = ref({
    root_action: query.get('root_action') || '',
    root_actor: query.get('root_actor') || '',
    root_from: query.get('root_from') || '',
    root_to: query.get('root_to') || '',
});

const countdown = computed(() => {
    const date = props.ownership.active_transfer?.effective_at;
    if (!date) return '';
    const seconds = Math.max(
        0,
        Math.floor((new Date(date).getTime() - now.value) / 1000),
    );
    const days = Math.floor(seconds / 86400);
    const hours = Math.floor((seconds % 86400) / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);
    return `${days}d ${hours}h ${minutes}m`;
});

// @function submitTransfer: Isinusumite ang transfer sa Root Ownership Panel flow.
// @useIn submitTransfer: resources/js/pages/Admin/UserManagement/components/RootOwnershipPanel.vue template
const submitTransfer = () => {
    responseMessage.value = '';
    transfer.post(route('admin.root-ownership.transfers.store'), {
        preserveScroll: true,
        onSuccess: () => {
            showReauth.value = false;
            transfer.reset();
        },
        onError: () => {
            responseMessage.value =
                'Check the highlighted fields. If your session expired, sign in again.';
        },
    });
};

// @function cancelTransfer: Kina-cancel ang transfer sa Root Ownership Panel flow.
// @useIn cancelTransfer: resources/js/pages/Admin/UserManagement/components/RootOwnershipPanel.vue template @click
const cancelTransfer = async () => {
    if (
        !(await confirmActionModal({
            title: 'Cancel ownership transfer?',
            text: 'The selected owner will be notified that this request was cancelled.',
        }))
    )
        return;
    router.delete(
        route(
            'admin.root-ownership.transfers.cancel',
            props.ownership.active_transfer.id,
        ),
        { preserveScroll: true },
    );
};

// @function submitOverride: Isinusumite ang override sa Root Ownership Panel flow.
// @useIn submitOverride: resources/js/pages/Admin/UserManagement/components/RootOwnershipPanel.vue template
const submitOverride = () =>
    override.post(route('admin.root-ownership.overrides.store'), {
        preserveScroll: true,
        onSuccess: () => override.reset(),
    });
// @function decideOverride: Pinoproseso ang decide override para sa Root Ownership Panel.
// @useIn decideOverride: resources/js/pages/Admin/UserManagement/components/RootOwnershipPanel.vue template @click
const decideOverride = (value) => {
    decision.decision = value;
    decision.post(
        route(
            'admin.root-ownership.overrides.decide',
            props.ownership.active_override.id,
        ),
        { preserveScroll: true, onSuccess: () => decision.reset() },
    );
};

// @function filterAudit: Pinoproseso ang filter audit para sa Root Ownership Panel.
// @useIn filterAudit: resources/js/pages/Admin/UserManagement/components/RootOwnershipPanel.vue template
const filterAudit = () => {
    router.get(route('admin.users.index'), auditFilters.value, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};
</script>

<template>
    <section class="rounded-lg border border-indigo-200 bg-white p-5 shadow-sm">
        <div class="flex items-start gap-3">
            <ShieldCheck class="mt-1 h-6 w-6 text-indigo-700" />
            <div>
                <h2 class="text-xl font-black text-slate-950">
                    Root Admin ownership
                </h2>
                <p class="mt-1 text-sm text-slate-600">
                    Protected transfer, emergency recovery, and immutable audit
                    history.
                </p>
            </div>
        </div>

        <div
            v-if="ownership.active_transfer"
            class="mt-5 rounded-lg border border-amber-200 bg-amber-50 p-4"
        >
            <p class="font-black text-amber-950">
                Transfer {{ ownership.active_transfer.status }}
            </p>
            <p class="mt-1 text-sm text-amber-900">
                {{ ownership.active_transfer.from.email }} →
                {{ ownership.active_transfer.to.email }}
            </p>
            <p class="mt-2 text-sm font-bold text-amber-800">
                Effective countdown: {{ countdown }}
            </p>
            <p class="mt-1 text-xs text-amber-800">
                Acceptance:
                {{
                    ownership.active_transfer.accepted_at
                        ? 'Accepted'
                        : 'Waiting for the new owner'
                }}
            </p>
            <button
                v-if="ownership.can_transfer"
                type="button"
                class="mt-3 rounded-md bg-rose-700 px-4 py-2 text-sm font-bold text-white"
                @click="cancelTransfer"
            >
                Cancel transfer
            </button>
        </div>

        <div
            v-else-if="ownership.can_transfer"
            class="mt-5 rounded-lg border border-slate-200 p-4"
        >
            <h3 class="font-black">Transfer ownership</h3>
            <p class="mt-1 text-sm text-slate-500">
                The new owner must accept. Completion occurs after
                {{ ownership.config.transfer_days }} days, followed by a
                {{ ownership.config.cooldown_days }}-day transfer cooldown.
            </p>
            <div class="mt-3 flex flex-col gap-3 sm:flex-row">
                <select
                    v-model="transfer.to_user_id"
                    class="flex-1 rounded-md border border-slate-300 px-3 py-2 text-sm"
                >
                    <option value="">Select an active Admin</option>
                    <option
                        v-for="admin in ownership.admin_options"
                        :key="admin.id"
                        :value="admin.id"
                    >
                        {{ admin.name }} — {{ admin.email }}
                    </option>
                </select>
                <button
                    type="button"
                    :disabled="!transfer.to_user_id"
                    class="rounded-md bg-indigo-700 px-4 py-2 text-sm font-bold text-white disabled:opacity-50"
                    @click="showReauth = true"
                >
                    Continue securely
                </button>
            </div>
            <p
                v-if="transfer.errors.to_user_id"
                class="mt-2 text-sm text-rose-700"
            >
                {{ transfer.errors.to_user_id }}
            </p>
        </div>

        <div class="mt-5 rounded-lg border border-rose-200 p-4">
            <div class="flex items-center gap-2">
                <ShieldAlert class="h-5 w-5 text-rose-700" />
                <h3 class="font-black">Emergency override</h3>
            </div>
            <template v-if="ownership.active_override">
                <p class="mt-2 text-sm">
                    <strong>Status:</strong>
                    {{ ownership.active_override.status }}
                </p>
                <p class="text-sm">
                    <strong>Proposed owner:</strong>
                    {{ ownership.active_override.to.email }}
                </p>
                <p class="text-sm">
                    <strong>Approvals:</strong>
                    {{ ownership.active_override.approvals_count }} /
                    {{ ownership.active_override.required_approvals }}
                </p>
                <p class="mt-2 text-sm whitespace-pre-wrap text-slate-600">
                    {{ ownership.active_override.reason }}
                </p>
                <ul class="mt-3 space-y-1 text-xs text-slate-500">
                    <li
                        v-for="item in ownership.active_override.approvals"
                        :key="item.approver_id"
                    >
                        {{ item.approver }} — {{ item.decision }}
                    </li>
                </ul>
                <div
                    v-if="
                        ownership.can_approve_override &&
                        ownership.current_user_id !==
                            ownership.active_override.requester_id
                    "
                    class="mt-3 flex gap-2"
                >
                    <button
                        type="button"
                        class="rounded bg-emerald-700 px-3 py-2 text-sm font-bold text-white"
                        :disabled="decision.processing"
                        @click="decideOverride('approve')"
                    >
                        Approve
                    </button>
                    <button
                        type="button"
                        class="rounded bg-rose-700 px-3 py-2 text-sm font-bold text-white"
                        :disabled="decision.processing"
                        @click="decideOverride('reject')"
                    >
                        Reject
                    </button>
                </div>
            </template>
            <form
                v-else-if="ownership.can_request_override"
                class="mt-3 grid gap-3"
                @submit.prevent="submitOverride"
            >
                <select
                    v-model="override.to_user_id"
                    class="rounded-md border border-slate-300 px-3 py-2 text-sm"
                >
                    <option value="">Select proposed owner</option>
                    <option
                        v-for="admin in ownership.admin_options"
                        :key="admin.id"
                        :value="admin.id"
                    >
                        {{ admin.name }} — {{ admin.email }}
                    </option>
                </select>
                <textarea
                    v-model="override.reason"
                    minlength="20"
                    required
                    rows="3"
                    class="rounded-md border border-slate-300 px-3 py-2 text-sm"
                    placeholder="Written emergency reason (minimum 20 characters)"
                ></textarea>
                <p v-if="override.errors.reason" class="text-sm text-rose-700">
                    {{ override.errors.reason }}
                </p>
                <button
                    type="submit"
                    :disabled="override.processing"
                    class="justify-self-start rounded-md bg-rose-700 px-4 py-2 text-sm font-bold text-white disabled:opacity-50"
                >
                    Request emergency override
                </button>
            </form>
        </div>

        <div v-if="ownership.audit_logs" class="mt-5 overflow-x-auto">
            <h3 class="mb-3 font-black">Ownership audit log</h3>
            <form
                class="mb-3 grid gap-2 md:grid-cols-5"
                @submit.prevent="filterAudit"
            >
                <input
                    v-model="auditFilters.root_action"
                    class="rounded border px-3 py-2 text-sm"
                    placeholder="Action"
                />
                <input
                    v-model="auditFilters.root_actor"
                    class="rounded border px-3 py-2 text-sm"
                    placeholder="Actor email"
                />
                <input
                    v-model="auditFilters.root_from"
                    type="date"
                    class="rounded border px-3 py-2 text-sm"
                    aria-label="Audit start date"
                />
                <input
                    v-model="auditFilters.root_to"
                    type="date"
                    class="rounded border px-3 py-2 text-sm"
                    aria-label="Audit end date"
                />
                <button
                    type="submit"
                    class="rounded bg-slate-800 px-3 py-2 text-sm font-bold text-white"
                >
                    Filter audit
                </button>
            </form>
            <table class="w-full min-w-[700px] text-left text-sm">
                <thead class="bg-slate-50 text-xs text-slate-500 uppercase">
                    <tr>
                        <th class="p-3">Time</th>
                        <th class="p-3">Action</th>
                        <th class="p-3">Actor</th>
                        <th class="p-3">Target</th>
                        <th class="p-3">IP</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <tr
                        v-for="entry in ownership.audit_logs.data"
                        :key="entry.id"
                    >
                        <td class="p-3">{{ entry.created_at }}</td>
                        <td class="p-3 font-bold">{{ entry.action }}</td>
                        <td class="p-3">{{ entry.actor || 'System' }}</td>
                        <td class="p-3">{{ entry.target || '—' }}</td>
                        <td class="p-3">{{ entry.ip_address || '—' }}</td>
                    </tr>
                </tbody>
            </table>
            <div class="mt-3 flex flex-wrap gap-2">
                <a
                    v-for="link in ownership.audit_logs.meta?.links ||
                    ownership.audit_logs.links ||
                    []"
                    :key="link.label"
                    :href="link.url || '#'"
                    class="rounded border px-2 py-1 text-xs"
                    :class="{
                        'bg-indigo-700 text-white': link.active,
                        'pointer-events-none opacity-40': !link.url,
                    }"
                    v-html="link.label"
                ></a>
            </div>
        </div>

        <div
            v-if="showReauth"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
            @click.self="showReauth = false"
        >
            <form
                class="w-full max-w-md rounded-lg bg-white p-6 shadow-xl"
                @submit.prevent="submitTransfer"
            >
                <h3 class="text-xl font-black">Confirm ownership transfer</h3>
                <p class="mt-2 text-sm text-slate-600">
                    Re-enter your password. This request cannot complete until
                    the new owner accepts and the safety delay ends.
                </p>
                <input
                    v-model="transfer.password"
                    type="password"
                    required
                    autocomplete="current-password"
                    class="mt-4 w-full rounded-md border px-3 py-2"
                    placeholder="Current password"
                />
                <p
                    v-if="transfer.errors.password"
                    class="mt-1 text-sm text-rose-700"
                >
                    {{ transfer.errors.password }}
                </p>
                <input
                    v-if="ownership.requires_two_factor"
                    v-model="transfer.two_factor_code"
                    inputmode="numeric"
                    maxlength="6"
                    required
                    class="mt-3 w-full rounded-md border px-3 py-2"
                    placeholder="6-digit two-factor code"
                />
                <p
                    v-if="transfer.errors.two_factor_code"
                    class="mt-1 text-sm text-rose-700"
                >
                    {{ transfer.errors.two_factor_code }}
                </p>
                <label class="mt-4 flex gap-2 text-sm"
                    ><input
                        v-model="transfer.confirmed"
                        type="checkbox"
                        required
                    />I understand ownership changes after
                    {{ ownership.config.transfer_days }} days and another
                    transfer is blocked during cooldown.</label
                >
                <p v-if="responseMessage" class="mt-2 text-sm text-rose-700">
                    {{ responseMessage }}
                </p>
                <div class="mt-5 flex justify-end gap-2">
                    <button
                        type="button"
                        class="rounded border px-4 py-2 text-sm font-bold"
                        @click="showReauth = false"
                    >
                        Back</button
                    ><button
                        type="submit"
                        :disabled="transfer.processing"
                        class="rounded bg-indigo-700 px-4 py-2 text-sm font-bold text-white disabled:opacity-50"
                    >
                        Submit transfer
                    </button>
                </div>
            </form>
        </div>
    </section>
</template>
