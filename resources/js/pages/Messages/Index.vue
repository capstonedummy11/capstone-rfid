<script setup>
import LinkedStudentSelector from '@/components/StudentPortal/LinkedStudentSelector.vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { FileText, Image, Search, Send } from 'lucide-vue-next';

const props = defineProps({
    messages: { type: Array, default: () => [] },
    recipients: { type: Array, default: () => [] },
    linkedStudents: { type: Array, default: () => [] },
    selectedStudentId: { type: [Number, String, null], default: null },
    currentUserRole: { type: String, default: '' },
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);
const currentUserId = computed(() =>
    Number(page.props.auth?.user?.user_id ?? page.props.auth?.user?.id ?? 0),
);
const currentRole = computed(() =>
    String(
        props.currentUserRole || page.props.auth?.user?.role || '',
    ).toLowerCase(),
);
const search = ref('');
const selectedConversationKey = ref('');
const selectedRecipient = ref(null);

const selectedStudentQuery = computed(() =>
    props.selectedStudentId ? { student_id: props.selectedStudentId } : {},
);

const form = useForm({
    recipient_user_id: '',
    body: '',
    attachment: null,
});

const filteredRecipients = computed(() => {
    const term = search.value.trim().toLowerCase();
    const list = props.recipients.filter(
        (recipient) => Number(recipient.user_id) !== currentUserId.value,
    );

    if (!term) return list;

    return list.filter((recipient) =>
        [recipient.name, recipient.email, recipient.role].some((value) =>
            String(value || '')
                .toLowerCase()
                .includes(term),
        ),
    );
});

const partnerFor = (message) => {
    const senderIsMe = Number(message.sender_user_id) === currentUserId.value;

    return {
        id: senderIsMe ? message.recipient_user_id : message.sender_user_id,
        name: senderIsMe ? message.recipient : message.sender,
        email: senderIsMe ? message.recipient_email : message.sender_email,
        role: senderIsMe ? message.recipient_role : message.sender_role,
    };
};

const conversations = computed(() => {
    const grouped = new Map();

    props.messages.forEach((message) => {
        const partner = partnerFor(message);
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

    return Array.from(grouped.values())
        .map((conversation) => {
            const sortedMessages = conversation.messages.sort(
                (a, b) => new Date(a.created_at) - new Date(b.created_at),
            );

            return {
                ...conversation,
                messages: sortedMessages,
                latest: sortedMessages[sortedMessages.length - 1],
            };
        })
        .sort(
            (a, b) =>
                new Date(b.latest.created_at) - new Date(a.latest.created_at),
        );
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

const conversationForUser = (userId) =>
    conversations.value.find(
        (conversation) => Number(conversation.partner.id) === Number(userId),
    ) ?? null;

watch(
    () => selectedConversation.value?.messages,
    (messages) => {
        if (!messages) return;

        messages.forEach((message) => {
            if (
                Number(message.recipient_user_id) === currentUserId.value &&
                !message.read_at
            ) {
                router.put(
                    route('messages.read', { message: message.id }),
                    {},
                    {
                        preserveScroll: true,
                        preserveState: true,
                        replace: true,
                    },
                );
            }
        });
    },
    { immediate: true },
);

const selectRecipient = (recipient) => {
    const existingConversation = conversationForUser(recipient.user_id);
    if (existingConversation) {
        selectConversation(existingConversation);
        return;
    }

    selectedRecipient.value = recipient;
    selectedConversationKey.value = '';
    form.recipient_user_id = recipient.user_id;
    form.body = '';
    form.attachment = null;
};

const selectConversation = (conversation) => {
    selectedConversationKey.value = conversation.key;
    selectedRecipient.value = null;
    form.recipient_user_id = conversation.partner.id;
};

const sendMessage = () => {
    const recipientId = form.recipient_user_id;

    form.transform((data) => ({
        ...data,
        ...selectedStudentQuery.value,
    })).post(route('messages.conversation.store'), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            form.reset('body', 'attachment');
            selectedRecipient.value = null;
            form.recipient_user_id = recipientId;
            selectedConversationKey.value = `user-${recipientId}`;
            router.reload({
                only: ['messages'],
                onSuccess: () => {
                    selectedConversationKey.value = `user-${recipientId}`;
                    form.recipient_user_id = recipientId;
                },
            });
        },
    });
};

const roleLabel = (role) =>
    String(role || '')
        .split('_')
        .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
        .join(' ');
</script>

<template>
    <div class="h-full bg-slate-50 p-4">
        <div class="grid h-full min-h-[680px] gap-4 lg:grid-cols-[340px_1fr]">
            <aside
                class="flex min-h-0 flex-col overflow-hidden rounded-md border border-slate-200 bg-white shadow-sm"
            >
                <div class="border-b border-slate-100 p-4">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <h1 class="text-lg font-bold text-slate-900">
                                Messenger
                            </h1>
                            <p class="text-xs text-slate-500">
                                {{ roleLabel(currentRole) }}
                            </p>
                        </div>
                        <LinkedStudentSelector
                            v-if="linkedStudents.length"
                            :students="linkedStudents"
                            :selected-student-id="selectedStudentId"
                        />
                    </div>
                </div>

                <div class="border-b border-slate-100 p-4">
                    <label class="text-xs font-bold text-slate-500 uppercase">
                        Search User
                    </label>
                    <div
                        class="mt-2 flex items-center gap-2 rounded-md border border-slate-300 px-3 py-2"
                    >
                        <Search class="h-4 w-4 text-slate-400" />
                        <input
                            v-model="search"
                            type="search"
                            placeholder="Name, email, or role"
                            class="w-full bg-transparent text-sm outline-none"
                        />
                    </div>
                    <div
                        class="mt-2 max-h-44 overflow-y-auto rounded-md border border-slate-100"
                    >
                        <button
                            v-for="recipient in filteredRecipients"
                            :key="recipient.user_id"
                            type="button"
                            class="block w-full border-b border-slate-100 px-3 py-2 text-left text-sm hover:bg-slate-50"
                            @click="selectRecipient(recipient)"
                        >
                            <span class="block font-semibold text-slate-800">
                                {{ recipient.name }}
                            </span>
                            <span class="block text-xs text-slate-500">
                                {{ recipient.email }} /
                                {{ roleLabel(recipient.role) }}
                            </span>
                        </button>
                        <p
                            v-if="filteredRecipients.length === 0"
                            class="p-3 text-sm text-slate-400"
                        >
                            No users found.
                        </p>
                    </div>
                </div>

                <div class="min-h-0 flex-1 overflow-y-auto">
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
                            <span class="text-[11px] text-slate-400">
                                {{ conversation.latest.created_label }}
                            </span>
                        </div>
                        <p class="mt-1 truncate text-xs text-slate-500">
                            {{ conversation.latest.preview }}
                        </p>
                    </button>
                    <p
                        v-if="conversations.length === 0"
                        class="p-6 text-center text-sm text-slate-400"
                    >
                        No conversations yet. Search for a user to start one.
                    </p>
                </div>
            </aside>

            <section
                class="flex min-h-0 flex-col rounded-md border border-slate-200 bg-white shadow-sm"
            >
                <div class="border-b border-slate-100 px-5 py-4">
                    <p
                        v-if="flashSuccess"
                        class="mb-3 rounded-md bg-emerald-50 px-3 py-2 text-sm font-semibold text-emerald-700"
                    >
                        {{ flashSuccess }}
                    </p>
                    <h2 class="text-lg font-bold text-slate-900">
                        {{
                            selectedConversation?.partner.name ||
                            selectedRecipient?.name ||
                            'Select a user'
                        }}
                    </h2>
                    <p class="text-xs text-slate-500">
                        {{
                            selectedConversation?.partner.email ||
                            selectedRecipient?.email ||
                            'Search for another user to start a conversation.'
                        }}
                    </p>
                </div>

                <div
                    v-if="selectedConversation"
                    class="min-h-0 flex-1 space-y-3 overflow-y-auto bg-slate-50 p-5"
                >
                    <article
                        v-for="message in selectedConversation.messages"
                        :key="message.id"
                        class="max-w-[78%] rounded-2xl p-3 shadow-sm"
                        :class="
                            Number(message.sender_user_id) === currentUserId
                                ? 'ml-auto rounded-br-sm bg-brand text-white'
                                : 'rounded-bl-sm bg-white text-slate-700'
                        "
                    >
                        <p class="text-xs font-bold opacity-80">
                            {{ message.sender }}
                        </p>
                        <p class="mt-2 text-sm whitespace-pre-line">
                            {{ message.body }}
                        </p>
                        <a
                            v-if="message.attachment_url"
                            :href="message.attachment_url"
                            class="mt-2 inline-flex max-w-full items-center gap-2 rounded-md bg-white/15 px-2 py-1 text-xs font-semibold underline"
                        >
                            <Image
                                v-if="message.is_image"
                                class="h-4 w-4 shrink-0"
                            />
                            <FileText v-else class="h-4 w-4 shrink-0" />
                            <span class="truncate">
                                {{ message.attachment_name || 'Attachment' }}
                            </span>
                        </a>
                        <p class="mt-2 text-[11px] opacity-70">
                            {{ message.created_label }}
                        </p>
                    </article>
                </div>

                <div
                    v-else
                    class="flex min-h-0 flex-1 items-center justify-center bg-slate-50 p-6 text-center text-slate-400"
                >
                    <p class="font-semibold">
                        Select a conversation or search for a user to start one.
                    </p>
                </div>

                <form
                    v-if="selectedRecipient || selectedConversation"
                    class="border-t border-slate-100 bg-white p-4"
                    @submit.prevent="sendMessage"
                >
                    <div
                        class="flex flex-col gap-3 rounded-2xl border border-slate-200 bg-slate-50 p-3"
                    >
                        <textarea
                            v-model="form.body"
                            class="min-h-20 w-full resize-none bg-transparent text-sm text-slate-700 outline-none placeholder:text-slate-400"
                            placeholder="Type a message..."
                            required
                        />
                        <div
                            class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                        >
                            <input
                                type="file"
                                class="text-sm text-slate-500"
                                @change="
                                    form.attachment =
                                        $event.target.files?.[0] || null
                                "
                            />
                            <button
                                class="inline-flex items-center gap-2 rounded-md bg-brand px-5 py-2 text-sm font-bold text-white disabled:opacity-60"
                                :disabled="
                                    form.processing || !form.recipient_user_id
                                "
                            >
                                <Send class="h-4 w-4" />
                                {{ form.processing ? 'Sending...' : 'Send' }}
                            </button>
                        </div>
                    </div>
                </form>
            </section>
        </div>
    </div>
</template>
