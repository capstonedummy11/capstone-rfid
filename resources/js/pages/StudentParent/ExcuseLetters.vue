<script setup>
import { useForm, usePage } from '@inertiajs/vue3';
import SuccessModal from '@/components/StudentPortal/SuccessModal.vue';
import LinkedStudentSelector from '@/components/StudentPortal/LinkedStudentSelector.vue';
import { computed, reactive, ref } from 'vue';

const props = defineProps({
    student: { type: Object, default: null },
    linkedStudents: { type: Array, default: () => [] },
    selectedStudentId: { type: [Number, String, null], default: null },
    currentUserRole: { type: String, default: '' },
    letters: { type: Array, default: () => [] },
    recipientSuggestions: { type: Array, default: () => [] },
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);
const showSuccessModal = computed(() => Boolean(flashSuccess.value));
const isParent = computed(() => props.currentUserRole === 'parent');
const recipientSearch = ref('');

const form = useForm({
    subject: '',
    from_date: '',
    to_date: '',
    reason: '',
    parent_signature: '',
    recipient_user_ids: [],
    attachment: null,
});

const submitLetter = () => {
    form.post(
        route(
            'student-parent.excuse-letters.store',
            selectedStudentQuery.value,
        ),
        {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                recipientSearch.value = '';
            },
        },
    );
};

const selectedStudentQuery = computed(() =>
    props.selectedStudentId ? { student_id: props.selectedStudentId } : {},
);

const approvalForms = reactive({});

const approvalFormFor = (letter) => {
    if (!approvalForms[letter.id]) {
        approvalForms[letter.id] = useForm({
            parent_signature: '',
            parent_approval_notes: '',
        });
    }

    return approvalForms[letter.id];
};

const approveLetter = (letter) => {
    const approveForm = approvalFormFor(letter);
    approveForm.put(
        route('student-parent.excuse-letters.approve', {
            letter: letter.id,
            ...selectedStudentQuery.value,
        }),
        {
            preserveScroll: true,
            onSuccess: () => approveForm.reset(),
        },
    );
};

const statusLabel = (status) =>
    String(status || '')
        .split('_')
        .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
        .join(' ');

const selectedRecipients = computed(() =>
    props.recipientSuggestions.filter((recipient) =>
        form.recipient_user_ids.includes(recipient.user_id),
    ),
);

const availableRecipientSuggestions = computed(() =>
    props.recipientSuggestions.filter(
        (recipient) => !form.recipient_user_ids.includes(recipient.user_id),
    ),
);

const addRecipient = () => {
    const search = recipientSearch.value.trim().toLowerCase();
    const recipient = availableRecipientSuggestions.value.find((suggestion) =>
        [suggestion.name, suggestion.email, suggestion.label].some(
            (value) => String(value || '').toLowerCase() === search,
        ),
    );

    if (!recipient) {
        return;
    }

    form.recipient_user_ids = [...form.recipient_user_ids, recipient.user_id];
    recipientSearch.value = '';
};

const addRecipientById = (userId) => {
    if (form.recipient_user_ids.includes(userId)) {
        return;
    }

    form.recipient_user_ids = [...form.recipient_user_ids, userId];
    recipientSearch.value = '';
};

const removeRecipient = (userId) => {
    form.recipient_user_ids = form.recipient_user_ids.filter(
        (selectedId) => selectedId !== userId,
    );
};

const letterRecipients = (letter) => {
    const selectedIds = letter.recipient_user_ids || [];

    if (selectedIds.length === 0) {
        return 'All assigned teachers';
    }

    return props.recipientSuggestions
        .filter((recipient) => selectedIds.includes(recipient.user_id))
        .map((recipient) => recipient.name)
        .join(', ');
};
</script>

<template>
    <div class="student-portal-page">
        <div class="student-portal-shell grid gap-5 lg:grid-cols-[360px_1fr]">
            <section
                class="rounded-md border border-slate-200 bg-white p-5 shadow-sm"
            >
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h1 class="text-lg font-bold text-slate-900">
                        Excuse Letter
                    </h1>
                    <LinkedStudentSelector
                        :students="linkedStudents"
                        :selected-student-id="selectedStudentId"
                    />
                </div>
                <form
                    class="mt-4 flex flex-col gap-3"
                    @submit.prevent="submitLetter"
                >
                    <label class="text-xs font-bold text-slate-500 uppercase">
                        Recipient
                        <div class="mt-1 flex gap-2">
                            <input
                                v-model="recipientSearch"
                                list="excuse-letter-recipient-suggestions"
                                class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm normal-case"
                                placeholder="Search assigned teacher"
                                @keydown.enter.prevent="addRecipient"
                            />
                            <button
                                type="button"
                                class="rounded-md border border-slate-300 px-3 py-2 text-sm font-bold text-slate-600"
                                @click="addRecipient"
                            >
                                Add
                            </button>
                        </div>
                        <datalist id="excuse-letter-recipient-suggestions">
                            <option
                                v-for="recipient in availableRecipientSuggestions"
                                :key="recipient.user_id"
                                :value="recipient.label"
                            />
                        </datalist>
                    </label>
                    <div
                        v-if="selectedRecipients.length"
                        class="flex flex-wrap gap-2"
                    >
                        <span
                            v-for="recipient in selectedRecipients"
                            :key="recipient.user_id"
                            class="inline-flex items-center gap-2 rounded-full bg-sky-50 px-3 py-1 text-xs font-bold text-sky-700"
                        >
                            {{ recipient.name }}
                            <button
                                type="button"
                                class="text-sky-500 hover:text-sky-800"
                                @click="removeRecipient(recipient.user_id)"
                            >
                                x
                            </button>
                        </span>
                    </div>
                    <div
                        v-else
                        class="rounded-md bg-slate-50 px-3 py-2 text-xs text-slate-500"
                    >
                        Leave blank to send to all assigned teachers after
                        parent approval.
                    </div>
                    <div
                        v-if="availableRecipientSuggestions.length"
                        class="flex flex-wrap gap-2"
                    >
                        <button
                            v-for="recipient in availableRecipientSuggestions"
                            :key="recipient.user_id"
                            type="button"
                            class="rounded-full border border-slate-200 px-3 py-1 text-xs font-semibold text-slate-600 hover:bg-slate-50"
                            @click="addRecipientById(recipient.user_id)"
                        >
                            {{ recipient.name }}
                        </button>
                    </div>
                    <label class="text-xs font-bold text-slate-500 uppercase">
                        Subject
                        <input
                            v-model="form.subject"
                            class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm normal-case"
                            required
                        />
                    </label>
                    <div class="grid grid-cols-2 gap-2">
                        <label
                            class="text-xs font-bold text-slate-500 uppercase"
                        >
                            From
                            <input
                                v-model="form.from_date"
                                type="date"
                                class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                                required
                            />
                        </label>
                        <label
                            class="text-xs font-bold text-slate-500 uppercase"
                        >
                            To
                            <input
                                v-model="form.to_date"
                                type="date"
                                class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                                required
                            />
                        </label>
                    </div>
                    <label class="text-xs font-bold text-slate-500 uppercase">
                        Reason
                        <textarea
                            v-model="form.reason"
                            class="mt-1 h-36 w-full rounded-md border border-slate-300 px-3 py-2 text-sm normal-case"
                            required
                        />
                    </label>
                    <label
                        v-if="isParent"
                        class="text-xs font-bold text-slate-500 uppercase"
                    >
                        Parent Signature
                        <input
                            v-model="form.parent_signature"
                            class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm normal-case"
                            placeholder="Type your full name"
                            required
                        />
                    </label>
                    <label class="text-xs font-bold text-slate-500 uppercase">
                        Attachment
                        <input
                            type="file"
                            class="mt-1 w-full text-sm normal-case"
                            @change="
                                form.attachment =
                                    $event.target.files?.[0] || null
                            "
                        />
                    </label>
                    <button
                        class="rounded-md bg-brand px-4 py-2 text-sm font-bold text-white"
                        :disabled="form.processing"
                    >
                        {{
                            form.processing ? 'Submitting...' : 'Submit Letter'
                        }}
                    </button>
                </form>

                <div class="mt-6">
                    <h2 class="text-sm font-bold text-slate-900">
                        Submitted Letters
                    </h2>
                    <div class="mt-3 space-y-2">
                        <div
                            v-for="letter in letters"
                            :key="letter.id"
                            class="rounded-md border border-slate-200 p-3 text-sm"
                        >
                            <div class="font-semibold text-slate-900">
                                {{ letter.subject }}
                            </div>
                            <div class="text-xs text-slate-500">
                                {{ letter.from_date }} to {{ letter.to_date }} |
                                {{ statusLabel(letter.status) }}
                            </div>
                            <div class="mt-1 text-xs text-slate-500">
                                Recipient: {{ letterRecipients(letter) }}
                            </div>
                            <div
                                v-if="letter.parent_signature"
                                class="mt-1 text-xs text-slate-500"
                            >
                                Signed by parent:
                                {{ letter.parent_signature }}
                            </div>
                            <div
                                v-else-if="
                                    letter.status === 'pending_parent_approval'
                                "
                                class="mt-1 text-xs font-semibold text-amber-600"
                            >
                                Waiting for parent approval and signature.
                            </div>
                            <a
                                v-if="letter.can_download"
                                :href="letter.download_url"
                                class="mt-2 inline-block text-xs font-bold text-brand"
                            >
                                Download PDF
                            </a>
                            <a
                                v-if="letter.attachment_url"
                                :href="letter.attachment_url"
                                class="mt-2 ml-3 inline-block text-xs font-bold text-slate-600 underline"
                            >
                                Attachment
                            </a>
                            <p
                                v-else
                                class="mt-2 text-xs font-semibold text-slate-400"
                            >
                                PDF available after parent approval.
                            </p>
                            <form
                                v-if="letter.can_parent_approve"
                                class="mt-3 space-y-2 rounded-md bg-amber-50 p-3"
                                @submit.prevent="approveLetter(letter)"
                            >
                                <label
                                    class="text-xs font-bold text-slate-500 uppercase"
                                >
                                    Parent Signature
                                    <input
                                        v-model="
                                            approvalFormFor(letter)
                                                .parent_signature
                                        "
                                        class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm normal-case"
                                        placeholder="Type your full name"
                                        required
                                    />
                                </label>
                                <label
                                    class="text-xs font-bold text-slate-500 uppercase"
                                >
                                    Notes
                                    <textarea
                                        v-model="
                                            approvalFormFor(letter)
                                                .parent_approval_notes
                                        "
                                        class="mt-1 h-20 w-full rounded-md border border-slate-300 px-3 py-2 text-sm normal-case"
                                    />
                                </label>
                                <button
                                    class="rounded-md bg-emerald-600 px-3 py-2 text-xs font-bold text-white"
                                    :disabled="
                                        approvalFormFor(letter).processing
                                    "
                                >
                                    Approve and Sign
                                </button>
                            </form>
                        </div>
                        <p
                            v-if="letters.length === 0"
                            class="text-sm text-slate-400"
                        >
                            No excuse letters yet.
                        </p>
                    </div>
                </div>
            </section>

            <section
                class="min-h-[720px] rounded-md border border-slate-200 bg-white p-10 shadow-sm"
            >
                <div class="mx-auto max-w-3xl text-sm leading-6 text-slate-800">
                    <h2 class="text-center text-xl font-bold text-slate-950">
                        Excuse Letter
                    </h2>
                    <div class="mt-14">
                        <p>Date: {{ form.from_date || '[Date Submitted]' }}</p>
                        <p>Dear Instructor,</p>
                    </div>
                    <p class="mt-12">
                        Good day. I am
                        {{ student?.name || '[Student Name]' }} from
                        {{ student?.section || '[Section]' }}. I would like to
                        request consideration for my absence or late attendance
                        for {{ form.subject || '[Subject]' }} from
                        {{ form.from_date || '[Start Date]' }} to
                        {{ form.to_date || '[End Date]' }}.
                    </p>
                    <p class="mt-5 whitespace-pre-line">
                        {{ form.reason || '[Reason]' }}
                    </p>
                    <p class="mt-8">
                        I respectfully ask for your consideration regarding this
                        matter. I will make sure to catch up on any missed
                        requirements.
                    </p>
                    <div class="mt-16">
                        <p>Sincerely,</p>
                        <p>{{ student?.name || '[Student Name]' }}</p>
                    </div>
                    <div v-if="isParent" class="mt-12">
                        <p>Parent Signature:</p>
                        <p>
                            {{ form.parent_signature || '[Parent Signature]' }}
                        </p>
                    </div>
                </div>
            </section>
        </div>

        <SuccessModal
            :show="showSuccessModal"
            title="Success"
            message="Take Care of yourself."
            :primary-href="
                letters.find((letter) => letter.can_download)?.download_url ||
                ''
            "
            primary-text="Download PDF"
            :secondary-href="route('student-parent.attendance')"
            secondary-text="Back to Attendance Page"
        />
    </div>
</template>
