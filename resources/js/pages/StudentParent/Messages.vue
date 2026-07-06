<script setup>
import LinkedStudentSelector from '@/components/StudentPortal/LinkedStudentSelector.vue';
import { useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    student: { type: Object, default: null },
    linkedStudents: { type: Array, default: () => [] },
    selectedStudentId: { type: [Number, String, null], default: null },
    messages: { type: Array, default: () => [] },
    instructors: { type: Array, default: () => [] },
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);
const currentUserId = computed(() =>
    Number(page.props.auth?.user?.user_id ?? page.props.auth?.user?.id ?? 0),
);
const currentRole = computed(() =>
    String(page.props.auth?.user?.role || '').toLowerCase(),
);
const recipientSearch = ref('');
const selectedConversationKey = ref('');
const selectedRecipient = ref(null);

const form = useForm({
    instructor_user_id: '',
    subject: '',
    body: '',
    attachment: null,
});

const filteredInstructors = computed(() => {
    const term = recipientSearch.value.trim().toLowerCase();
    if (!term) return props.instructors;

    return props.instructors.filter((instructor) =>
        [instructor.name, instructor.email].some((value) =>
            String(value || '')
                .toLowerCase()
                .includes(term),
        ),
    );
});

const conversationPartner = (message) => {
    const senderIsMe = Number(message.sender_user_id) === currentUserId.value;

    return {
        id: senderIsMe ? message.recipient_user_id : message.sender_user_id,
        name: senderIsMe
            ? message.recipient || message.instructor
            : message.sender,
        email: senderIsMe ? message.recipient_email : message.sender_email,
    };
};

const conversations = computed(() => {
    const grouped = new Map();

    props.messages.forEach((message) => {
        const partner = conversationPartner(message);
        if (!partner.id) return;

        const key = `user-${partner.id}`;
        if (!grouped.has(key)) {
            grouped.set(key, {
                key,
                partner,
                messages: [],
                latest: message,
            });
        }

        grouped.get(key).messages.push(message);
    });

    return Array.from(grouped.values()).map((conversation) => {
        const sortedMessages = conversation.messages.sort(
            (a, b) => new Date(a.created_at) - new Date(b.created_at),
        );

        return {
            ...conversation,
            messages: sortedMessages,
            latest: sortedMessages[sortedMessages.length - 1],
        };
    });
});

watch(
    conversations,
    (items) => {
        if (!selectedConversationKey.value && items.length > 0) {
            selectedConversationKey.value = items[0].key;
        }
    },
    { immediate: true },
);

const selectedConversation = computed(
    () =>
        conversations.value.find(
            (conversation) =>
                conversation.key === selectedConversationKey.value,
        ) ?? null,
);

const selectRecipient = (instructor) => {
    selectedRecipient.value = instructor;
    selectedConversationKey.value = '';
    form.instructor_user_id = instructor.user_id;
    form.subject = '';
    form.body = '';
    form.attachment = null;
};

const selectConversation = (conversation) => {
    selectedConversationKey.value = conversation.key;
    selectedRecipient.value = null;
    form.instructor_user_id = conversation.partner.id;
};

const sendMessage = () => {
    form.post(route('student-parent.messages.store'), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            form.reset('subject', 'body', 'attachment');
            selectedRecipient.value = null;
        },
    });
};
</script>

<template>
    <div class="student-portal-page">
        <div class="student-portal-shell grid gap-5 lg:grid-cols-[360px_1fr]">
            <aside
                class="rounded-md border border-slate-200 bg-white shadow-sm"
            >
                <div class="border-b border-slate-100 p-4">
                    <div class="flex items-center justify-between gap-3">
                        <h1 class="text-lg font-bold text-slate-900">
                            Messages
                        </h1>
                        <LinkedStudentSelector
                            :students="linkedStudents"
                            :selected-student-id="selectedStudentId"
                        />
                    </div>
                    <p class="mt-1 text-xs text-slate-500">
                        {{ currentRole }} conversations are private to the
                        sender and recipient.
                    </p>
                </div>

                <div class="border-b border-slate-100 p-4">
                    <label class="text-xs font-bold text-slate-500 uppercase"
                        >Start Conversation</label
                    >
                    <input
                        v-model="recipientSearch"
                        type="search"
                        placeholder="Search recipient first..."
                        class="mt-2 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                    />
                    <div
                        class="mt-2 max-h-44 overflow-y-auto rounded-md border border-slate-100"
                    >
                        <button
                            v-for="instructor in filteredInstructors"
                            :key="instructor.user_id"
                            type="button"
                            class="block w-full border-b border-slate-100 px-3 py-2 text-left text-sm hover:bg-slate-50"
                            @click="selectRecipient(instructor)"
                        >
                            <span class="block font-semibold text-slate-800">{{
                                instructor.name
                            }}</span>
                            <span class="block text-xs text-slate-500">{{
                                instructor.email
                            }}</span>
                        </button>
                        <p
                            v-if="filteredInstructors.length === 0"
                            class="p-3 text-sm text-slate-400"
                        >
                            No recipients found.
                        </p>
                    </div>
                </div>

                <div class="max-h-[520px] overflow-y-auto">
                    <button
                        v-for="conversation in conversations"
                        :key="conversation.key"
                        type="button"
                        class="block w-full border-b border-slate-100 px-4 py-3 text-left hover:bg-slate-50"
                        :class="
                            selectedConversationKey === conversation.key
                                ? 'bg-sky-50'
                                : 'bg-white'
                        "
                        @click="selectConversation(conversation)"
                    >
                        <div class="flex items-center justify-between gap-2">
                            <p
                                class="truncate text-sm font-bold text-slate-900"
                            >
                                {{ conversation.partner.name }}
                            </p>
                            <span class="text-[11px] text-slate-400">{{
                                conversation.latest.created_at
                            }}</span>
                        </div>
                        <p
                            class="mt-1 truncate text-xs font-semibold text-slate-500"
                        >
                            {{ conversation.latest.subject }}
                        </p>
                        <p class="mt-1 line-clamp-2 text-xs text-slate-500">
                            {{ conversation.latest.body }}
                        </p>
                    </button>
                    <p
                        v-if="conversations.length === 0"
                        class="p-6 text-center text-sm text-slate-400"
                    >
                        No conversations yet. Search for a recipient to start a
                        new conversation.
                    </p>
                </div>
            </aside>

            <section
                class="rounded-md border border-slate-200 bg-white p-5 shadow-sm"
            >
                <p
                    v-if="flashSuccess"
                    class="mb-4 rounded-md bg-emerald-50 px-3 py-2 text-sm font-semibold text-emerald-700"
                >
                    {{ flashSuccess }}
                </p>

                <div
                    v-if="selectedConversation"
                    class="flex min-h-[420px] flex-col"
                >
                    <h2 class="text-lg font-bold text-slate-900">
                        {{ selectedConversation.partner.name }}
                    </h2>
                    <div
                        class="mt-4 flex-1 space-y-3 overflow-y-auto rounded-md bg-slate-50 p-4"
                    >
                        <article
                            v-for="message in selectedConversation.messages"
                            :key="message.id"
                            class="max-w-[78%] rounded-md p-3 shadow-sm"
                            :class="
                                Number(message.sender_user_id) === currentUserId
                                    ? 'ml-auto bg-brand text-white'
                                    : 'bg-white text-slate-700'
                            "
                        >
                            <p class="text-xs font-bold opacity-80">
                                {{ message.sender }}
                            </p>
                            <h3 class="mt-1 font-bold">
                                {{ message.subject }}
                            </h3>
                            <p class="mt-2 text-sm whitespace-pre-line">
                                {{ message.body }}
                            </p>
                            <a
                                v-if="message.attachment_url"
                                :href="message.attachment_url"
                                target="_blank"
                                class="mt-2 inline-block text-xs font-semibold underline"
                            >
                                {{ message.attachment_name || 'Attachment' }}
                            </a>
                        </article>
                    </div>
                </div>

                <div
                    v-else-if="selectedRecipient"
                    class="mb-4 rounded-md bg-slate-50 p-4"
                >
                    <p class="text-xs font-bold text-slate-500 uppercase">
                        New Conversation
                    </p>
                    <h2 class="mt-1 text-lg font-bold text-slate-900">
                        {{ selectedRecipient.name }}
                    </h2>
                    <p class="text-sm text-slate-500">
                        {{ selectedRecipient.email }}
                    </p>
                </div>

                <div
                    v-else
                    class="flex min-h-[420px] flex-col items-center justify-center rounded-md bg-slate-50 p-6 text-center text-slate-400"
                >
                    <p class="font-semibold">
                        Select a conversation or search for a recipient to start
                        one.
                    </p>
                </div>

                <form
                    v-if="selectedRecipient || selectedConversation"
                    class="mt-4 grid gap-3 border-t border-slate-100 pt-4"
                    @submit.prevent="sendMessage"
                >
                    <label class="text-sm font-semibold text-slate-700">
                        Subject
                        <input
                            v-model="form.subject"
                            class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                            required
                        />
                    </label>
                    <label class="text-sm font-semibold text-slate-700">
                        Message
                        <textarea
                            v-model="form.body"
                            class="mt-1 h-28 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                            required
                        />
                    </label>
                    <label class="text-sm font-semibold text-slate-700">
                        Attachment
                        <input
                            type="file"
                            class="mt-1 w-full text-sm"
                            @change="
                                form.attachment =
                                    $event.target.files?.[0] || null
                            "
                        />
                    </label>
                    <button
                        class="rounded-md bg-brand px-4 py-2 text-sm font-bold text-white"
                        :disabled="form.processing || !form.instructor_user_id"
                    >
                        {{ form.processing ? 'Sending...' : 'Send Message' }}
                    </button>
                </form>
            </section>
        </div>
    </div>
</template>
