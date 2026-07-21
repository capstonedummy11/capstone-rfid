<script setup>
import { router, useForm, usePage } from '@inertiajs/vue3';
import { computed, nextTick, ref, watch } from 'vue';
import { FileText, Image, Inbox, Search, Send } from 'lucide-vue-next';

const props = defineProps({
    conversations: { type: Array, default: () => [] },
    currentUserRole: { type: String, default: '' },
});

const page = usePage();
const selectedKey = ref(props.conversations[0]?.key ?? null);
const search = ref('');
const thread = ref(null);
const replyForm = useForm({ body: '' });
const flashSuccess = computed(() => page.props.flash?.success);

const filteredConversations = computed(() => {
    const term = search.value.trim().toLowerCase();
    if (!term) return props.conversations;

    return props.conversations.filter((conversation) =>
        [
            conversation.participant?.name,
            conversation.participant?.email,
            conversation.participant?.student_number,
            conversation.preview,
        ].some((value) =>
            String(value || '')
                .toLowerCase()
                .includes(term),
        ),
    );
});

const selectedConversation = computed(
    () =>
        props.conversations.find(
            (conversation) => conversation.key === selectedKey.value,
        ) ??
        props.conversations[0] ??
        null,
);

const scrollToLatest = () =>
    nextTick(() => {
        if (thread.value) thread.value.scrollTop = thread.value.scrollHeight;
    });

watch(
    () => selectedConversation.value?.key,
    () => {
        const conversation = selectedConversation.value;
        if (!conversation) return;

        scrollToLatest();
        if (!conversation.unread) return;

        router.put(
            route('admin.messages.read', {
                message: conversation.reply_message_id,
            }),
            {},
            {
                preserveScroll: true,
                preserveState: true,
                replace: true,
            },
        );
    },
    { immediate: true },
);

watch(() => selectedConversation.value?.messages?.length, scrollToLatest);

const sendReply = () => {
    const conversation = selectedConversation.value;
    if (!conversation) return;

    replyForm.post(
        route('admin.messages.reply', conversation.reply_message_id),
        {
            preserveScroll: true,
            onSuccess: () => {
                replyForm.reset();
                scrollToLatest();
            },
        },
    );
};
</script>

<template>
    <div class="h-full bg-slate-50">
        <div
            class="grid h-full min-h-[640px] grid-cols-1 overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm lg:grid-cols-[340px_minmax(0,1fr)]"
        >
            <aside class="flex min-h-0 flex-col border-r border-slate-200">
                <div class="border-b border-slate-100 p-4">
                    <h1 class="text-xl font-bold text-slate-900">Chats</h1>
                    <label
                        class="mt-3 flex items-center gap-2 rounded-full bg-slate-100 px-3 py-2"
                    >
                        <Search class="h-4 w-4 text-slate-400" />
                        <input
                            v-model="search"
                            type="search"
                            placeholder="Search messages"
                            class="w-full bg-transparent text-sm text-slate-700 outline-none placeholder:text-slate-400"
                        />
                    </label>
                </div>

                <div class="min-h-0 flex-1 overflow-y-auto">
                    <button
                        v-for="conversation in filteredConversations"
                        :key="conversation.key"
                        type="button"
                        class="flex w-full gap-3 border-b border-slate-100 px-4 py-3 text-left hover:bg-slate-50"
                        :class="
                            selectedConversation?.key === conversation.key
                                ? 'bg-sky-50'
                                : 'bg-white'
                        "
                        @click="selectedKey = conversation.key"
                    >
                        <div
                            class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-brand text-sm font-bold text-white"
                        >
                            {{
                                conversation.participant?.name
                                    ?.charAt(0)
                                    ?.toUpperCase() || '?'
                            }}
                        </div>
                        <div class="min-w-0 flex-1">
                            <div
                                class="flex items-center justify-between gap-2"
                            >
                                <p
                                    class="truncate text-sm text-slate-900"
                                    :class="
                                        conversation.unread
                                            ? 'font-extrabold'
                                            : 'font-semibold'
                                    "
                                >
                                    {{ conversation.participant?.name }}
                                </p>
                                <span
                                    class="shrink-0 text-[11px] text-slate-400"
                                >
                                    {{ conversation.latest_label }}
                                </span>
                            </div>
                            <div class="mt-1 flex items-center gap-2">
                                <p
                                    class="min-w-0 flex-1 truncate text-xs text-slate-500"
                                >
                                    {{ conversation.preview }}
                                </p>
                                <span
                                    v-if="conversation.unread"
                                    class="h-2.5 w-2.5 shrink-0 rounded-full bg-brand"
                                ></span>
                            </div>
                        </div>
                    </button>

                    <p
                        v-if="filteredConversations.length === 0"
                        class="p-8 text-center text-sm text-slate-400"
                    >
                        No conversations found.
                    </p>
                </div>
            </aside>

            <section class="flex min-h-0 flex-col">
                <template v-if="selectedConversation">
                    <header
                        class="flex items-center gap-3 border-b border-slate-100 px-5 py-3"
                    >
                        <div
                            class="flex h-10 w-10 items-center justify-center rounded-full bg-brand font-bold text-white"
                        >
                            {{
                                selectedConversation.participant?.name
                                    ?.charAt(0)
                                    ?.toUpperCase() || '?'
                            }}
                        </div>
                        <div class="min-w-0">
                            <h2 class="truncate font-bold text-slate-900">
                                {{ selectedConversation.participant?.name }}
                            </h2>
                            <p class="truncate text-xs text-slate-500">
                                {{
                                    selectedConversation.participant
                                        ?.student_number ||
                                    selectedConversation.participant?.role
                                }}
                            </p>
                        </div>
                    </header>

                    <div
                        ref="thread"
                        class="min-h-0 flex-1 space-y-3 overflow-y-auto bg-slate-50 p-5"
                    >
                        <p
                            v-if="flashSuccess"
                            class="mx-auto max-w-md rounded-md bg-emerald-50 px-3 py-2 text-center text-sm font-semibold text-emerald-700"
                        >
                            {{ flashSuccess }}
                        </p>

                        <article
                            v-for="message in selectedConversation.messages"
                            :key="message.id"
                            class="flex"
                            :class="
                                message.direction === 'outgoing'
                                    ? 'justify-end'
                                    : 'justify-start'
                            "
                        >
                            <div class="max-w-[78%] space-y-1">
                                <div
                                    class="rounded-2xl px-4 py-2.5 shadow-sm"
                                    :class="
                                        message.direction === 'outgoing'
                                            ? 'rounded-br-sm bg-brand text-white'
                                            : 'rounded-bl-sm bg-white text-slate-700'
                                    "
                                >
                                    <p
                                        class="text-sm leading-6 whitespace-pre-wrap"
                                    >
                                        {{ message.body }}
                                    </p>
                                    <a
                                        v-if="message.attachment_url"
                                        :href="message.attachment_url"
                                        target="_blank"
                                        class="mt-2 flex items-center gap-2 rounded-lg border border-current/20 px-3 py-2 text-xs font-semibold"
                                    >
                                        <Image
                                            v-if="message.is_image"
                                            class="h-4 w-4"
                                        />
                                        <FileText v-else class="h-4 w-4" />
                                        <span class="truncate">{{
                                            message.attachment_name
                                        }}</span>
                                    </a>
                                </div>
                                <p
                                    class="px-1 text-[11px] text-slate-400"
                                    :class="
                                        message.direction === 'outgoing'
                                            ? 'text-right'
                                            : 'text-left'
                                    "
                                >
                                    {{ message.created_label }}
                                </p>
                            </div>
                        </article>
                    </div>

                    <footer class="border-t border-slate-100 bg-white p-4">
                        <form
                            class="flex items-center gap-3 rounded-full border border-slate-200 bg-slate-50 px-4 py-2.5 focus-within:border-brand"
                            @submit.prevent="sendReply"
                        >
                            <input
                                v-model="replyForm.body"
                                type="text"
                                :placeholder="`Message ${selectedConversation.participant?.name}`"
                                class="w-full bg-transparent text-sm text-slate-700 outline-none placeholder:text-slate-400"
                                required
                            />
                            <button
                                type="submit"
                                class="rounded-full bg-brand p-2 text-white disabled:opacity-50"
                                :disabled="
                                    replyForm.processing ||
                                    !replyForm.body.trim()
                                "
                                aria-label="Send reply"
                            >
                                <Send class="h-4 w-4" />
                            </button>
                        </form>
                        <p
                            v-if="replyForm.errors.body"
                            class="mt-2 text-xs text-red-600"
                        >
                            {{ replyForm.errors.body }}
                        </p>
                    </footer>
                </template>

                <div
                    v-else
                    class="flex flex-1 flex-col items-center justify-center p-8 text-center text-slate-500"
                >
                    <Inbox class="mb-3 h-11 w-11 text-slate-300" />
                    <p class="font-semibold">No messages yet.</p>
                    <p class="mt-1 text-sm text-slate-400">
                        New student and parent messages will appear here.
                    </p>
                </div>
            </section>
        </div>
    </div>
</template>
