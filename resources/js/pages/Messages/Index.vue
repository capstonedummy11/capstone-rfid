<script setup>
import { router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import {
    FileText,
    Image,
    Inbox,
    MailOpen,
    Paperclip,
    Send,
} from 'lucide-vue-next';
import Swal from 'sweetalert2';

const props = defineProps({
    messages: { type: Array, default: () => [] },
    currentUserRole: { type: String, default: '' },
});

const selectedId = ref(props.messages[0]?.id ?? null);
const replyFileInputRef = ref(null);

const selectedMessage = computed(
    () =>
        props.messages.find((message) => message.id === selectedId.value) ??
        props.messages[0] ??
        null,
);
const canReply = computed(
    () => props.currentUserRole === 'instructor' && selectedMessage.value,
);
const replyForm = useForm({
    reply_body: '',
    reply_attachment: null,
});

const setReplyAttachment = (event) => {
    replyForm.reply_attachment = event.target.files?.[0] ?? null;
};

const clearReplyAttachment = () => {
    replyForm.reply_attachment = null;
    if (replyFileInputRef.value) {
        replyFileInputRef.value.value = '';
    }
};

const submitReply = () => {
    if (!selectedMessage.value || !canReply.value) return;

    replyForm.post(
        route('admin.messages.reply', { message: selectedMessage.value.id }),
        {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                replyForm.reset('reply_body', 'reply_attachment');
                clearReplyAttachment();
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'success',
                    title: 'Reply sent.',
                    showConfirmButton: false,
                    timer: 1800,
                });
            },
        },
    );
};

watch(
    () => selectedMessage.value?.id,
    (id) => {
        const message = selectedMessage.value;
        if (!id || !message || message.read_at) return;

        router.put(
            route('admin.messages.read', { message: id }),
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

watch(
    () => selectedMessage.value?.id,
    () => {
        replyForm.clearErrors();
        replyForm.reset('reply_body', 'reply_attachment');
        clearReplyAttachment();
    },
);
</script>

<template>
    <div class="h-full bg-slate-50 p-4">
        <div
            class="grid h-full min-h-[620px] grid-cols-1 gap-4 xl:grid-cols-[minmax(0,1fr)_360px]"
        >
            <!-- Message content area -->
            <section
                class="flex min-h-0 flex-col overflow-hidden rounded-md border border-slate-200 bg-white shadow-sm"
            >
                <div class="border-b border-slate-100 px-5 py-4">
                    <h1 class="text-xl font-bold text-slate-900">
                        {{ selectedMessage?.sender_name || 'Messages' }}
                    </h1>
                </div>

                <div
                    v-if="selectedMessage"
                    class="flex-1 space-y-4 overflow-y-auto bg-slate-50 p-5"
                >
                    <!-- Bubble 1: subject / notice -->
                    <div class="flex items-start gap-3">
                        <div
                            class="mt-1 h-9 w-9 shrink-0 rounded-full bg-slate-200"
                        ></div>

                        <div class="max-w-md space-y-1.5">
                            <div
                                class="rounded-2xl rounded-tl-sm bg-white p-4 shadow-sm"
                            >
                                <p
                                    class="text-xs font-bold tracking-wide text-slate-900 uppercase"
                                >
                                    {{ selectedMessage.sender_name }}
                                </p>
                                <p
                                    class="mt-1 text-sm leading-6 whitespace-pre-wrap text-slate-700"
                                >
                                    {{ selectedMessage.subject }}
                                </p>

                                <a
                                    v-if="selectedMessage.attachment_url"
                                    :href="selectedMessage.attachment_url"
                                    target="_blank"
                                    class="mt-3 flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2.5 text-sm font-medium text-slate-500 hover:bg-slate-50"
                                >
                                    <Image
                                        v-if="selectedMessage.is_image"
                                        class="h-4 w-4 shrink-0 text-slate-400"
                                    />
                                    <FileText
                                        v-else
                                        class="h-4 w-4 shrink-0 text-slate-400"
                                    />
                                    <span class="truncate">{{
                                        selectedMessage.attachment_name
                                    }}</span>
                                </a>

                                <a
                                    v-if="selectedMessage.link_url"
                                    :href="selectedMessage.link_url"
                                    target="_blank"
                                    class="mt-3 inline-block text-sm font-medium text-sky-600 underline hover:text-sky-700"
                                >
                                    {{
                                        selectedMessage.link_label ||
                                        'Click here to go to the Site'
                                    }}
                                </a>
                            </div>

                            <p class="px-1 text-xs text-slate-400">
                                {{ selectedMessage.created_label }}
                            </p>
                        </div>
                    </div>

                    <!-- Bubble 2: body / follow-up message -->
                    <div
                        v-if="selectedMessage.body"
                        class="flex items-start gap-3"
                    >
                        <div
                            class="mt-1 h-9 w-9 shrink-0 rounded-full bg-slate-200"
                        ></div>
                        <div class="max-w-md space-y-1.5">
                            <div
                                class="rounded-2xl rounded-tl-sm bg-white p-4 shadow-sm"
                            >
                                <p
                                    class="text-xs font-bold tracking-wide text-slate-900 uppercase"
                                >
                                    {{ selectedMessage.sender_name }}
                                </p>
                                <p
                                    class="mt-1 text-sm leading-6 whitespace-pre-wrap text-slate-700"
                                >
                                    {{ selectedMessage.body }}
                                </p>
                            </div>
                            <p class="px-1 text-xs text-slate-400">
                                {{ selectedMessage.created_label }}
                            </p>
                        </div>
                    </div>

                    <div
                        v-if="
                            selectedMessage.reply_body ||
                            selectedMessage.reply_attachment_url
                        "
                        class="flex justify-end"
                    >
                        <div class="max-w-md space-y-1.5">
                            <div
                                class="rounded-2xl rounded-tr-sm bg-brand p-4 text-white shadow-sm"
                            >
                                <p
                                    class="text-xs font-bold tracking-wide text-white/80 uppercase"
                                >
                                    {{
                                        selectedMessage.reply_author_name ||
                                        'Instructor'
                                    }}
                                </p>
                                <p
                                    v-if="selectedMessage.reply_body"
                                    class="mt-1 text-sm leading-6 whitespace-pre-wrap"
                                >
                                    {{ selectedMessage.reply_body }}
                                </p>

                                <a
                                    v-if="selectedMessage.reply_attachment_url"
                                    :href="selectedMessage.reply_attachment_url"
                                    target="_blank"
                                    class="mt-3 flex items-center gap-2 rounded-lg border border-white/25 bg-white/10 px-3 py-2.5 text-sm font-medium text-white hover:bg-white/15"
                                >
                                    <Image
                                        v-if="selectedMessage.reply_is_image"
                                        class="h-4 w-4 shrink-0"
                                    />
                                    <FileText v-else class="h-4 w-4 shrink-0" />
                                    <span class="truncate">{{
                                        selectedMessage.reply_attachment_name
                                    }}</span>
                                </a>
                            </div>
                            <p class="px-1 text-right text-xs text-slate-400">
                                {{ selectedMessage.replied_label }}
                            </p>
                        </div>
                    </div>
                </div>

                <div
                    v-else
                    class="flex flex-1 flex-col items-center justify-center bg-slate-50 p-8 text-center text-slate-500"
                >
                    <Inbox class="mb-3 h-10 w-10 text-slate-300" />
                    <p class="font-semibold">No messages yet.</p>
                </div>

                <div class="border-t border-slate-100 bg-white px-4 py-3">
                    <form
                        v-if="canReply"
                        class="space-y-2"
                        @submit.prevent="submitReply"
                    >
                        <div
                            class="flex items-center gap-3 rounded-full border border-slate-200 bg-slate-50 px-4 py-2.5"
                        >
                            <button
                                type="button"
                                class="shrink-0 text-slate-400 hover:text-slate-600"
                                title="Attach file"
                                @click="replyFileInputRef?.click()"
                            >
                                <Paperclip class="h-4 w-4" />
                            </button>
                            <input
                                v-model="replyForm.reply_body"
                                type="text"
                                placeholder="Type your reply..."
                                class="w-full bg-transparent text-sm text-slate-700 placeholder:text-slate-400 focus:outline-none"
                                required
                            />
                            <button
                                type="submit"
                                :disabled="replyForm.processing"
                                class="shrink-0 text-slate-400 hover:text-slate-600 disabled:cursor-not-allowed disabled:opacity-60"
                                title="Send reply"
                            >
                                <Send class="h-4 w-4" />
                            </button>
                        </div>
                        <input
                            ref="replyFileInputRef"
                            type="file"
                            accept=".pdf,image/png,image/jpeg,image/webp"
                            class="hidden"
                            @change="setReplyAttachment"
                        />
                        <div
                            class="flex min-h-5 items-center justify-between gap-3 text-xs"
                        >
                            <p
                                v-if="replyForm.reply_attachment"
                                class="truncate text-slate-500"
                            >
                                Attached: {{ replyForm.reply_attachment.name }}
                            </p>
                            <p v-else class="text-slate-400">
                                PDF, JPG, PNG, or WEBP up to 5 MB.
                            </p>
                            <button
                                v-if="replyForm.reply_attachment"
                                type="button"
                                class="shrink-0 font-semibold text-slate-500 hover:text-slate-700"
                                @click="clearReplyAttachment"
                            >
                                Remove
                            </button>
                        </div>
                        <p
                            v-if="replyForm.errors.reply_body"
                            class="text-xs text-red-600"
                        >
                            {{ replyForm.errors.reply_body }}
                        </p>
                        <p
                            v-if="replyForm.errors.reply_attachment"
                            class="text-xs text-red-600"
                        >
                            {{ replyForm.errors.reply_attachment }}
                        </p>
                    </form>
                    <div
                        v-else
                        class="rounded-full border border-slate-200 bg-slate-50 px-4 py-2.5 text-sm text-slate-400"
                    >
                        Reply box available to the assigned instructor.
                    </div>
                </div>
            </section>

            <!-- People list panel, unchanged -->
            <aside
                class="min-h-0 overflow-hidden rounded-md border border-slate-200 bg-white shadow-sm"
            >
                <div class="border-b border-slate-100 px-4 py-3">
                    <h2 class="text-sm font-bold text-slate-900">People</h2>
                </div>
                <div class="h-full overflow-y-auto">
                    <button
                        v-for="message in messages"
                        :key="message.id"
                        type="button"
                        class="flex w-full gap-3 border-b border-slate-100 px-4 py-3 text-left hover:bg-slate-50"
                        :class="
                            selectedMessage?.id === message.id
                                ? 'bg-sky-50'
                                : 'bg-white'
                        "
                        @click="selectedId = message.id"
                    >
                        <div
                            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-brand text-sm font-bold text-white"
                        >
                            {{
                                message.sender_name?.charAt(0)?.toUpperCase() ||
                                '?'
                            }}
                        </div>
                        <div class="min-w-0 flex-1">
                            <div
                                class="flex items-center justify-between gap-2"
                            >
                                <p
                                    class="truncate text-sm font-bold text-slate-800"
                                >
                                    {{ message.sender_name }}
                                </p>
                                <MailOpen
                                    v-if="message.read_at"
                                    class="h-4 w-4 shrink-0 text-slate-300"
                                />
                                <span
                                    v-else
                                    class="h-2 w-2 shrink-0 rounded-full bg-brand"
                                ></span>
                            </div>
                            <p
                                class="truncate text-xs font-semibold text-slate-500"
                            >
                                {{ message.subject }}
                            </p>
                            <p
                                class="mt-1 line-clamp-2 text-xs leading-5 text-slate-500"
                            >
                                {{ message.preview }}
                            </p>
                            <p class="mt-1 text-xs text-slate-400">
                                {{ message.created_label }}
                            </p>
                        </div>
                    </button>
                </div>
            </aside>
        </div>
    </div>
</template>
