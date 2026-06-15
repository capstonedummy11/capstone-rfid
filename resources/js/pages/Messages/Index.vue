<script setup>
import { router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { FileText, Image, Inbox, MailOpen } from 'lucide-vue-next';

const props = defineProps({
    messages: { type: Array, default: () => [] },
    currentUserRole: { type: String, default: '' },
});

const selectedId = ref(props.messages[0]?.id ?? null);

const selectedMessage = computed(
    () => props.messages.find((message) => message.id === selectedId.value) ?? props.messages[0] ?? null,
);

watch(
    () => selectedMessage.value?.id,
    (id) => {
        const message = selectedMessage.value;
        if (!id || !message || message.read_at) return;

        router.put(route('admin.messages.read', { message: id }), {}, {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        });
    },
    { immediate: true },
);
</script>

<template>
    <div class="h-full bg-slate-50 p-4">
        <div class="grid h-full min-h-[620px] grid-cols-1 gap-4 xl:grid-cols-[minmax(0,1fr)_360px]">
            <section class="min-h-0 overflow-hidden rounded-md border border-slate-200 bg-white shadow-sm">
                <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-5 py-4">
                    <div>
                        <h1 class="text-xl font-bold text-slate-900">Messages</h1>
                        <p class="text-sm text-slate-500">
                            Attendance concerns and questions from students or parents.
                        </p>
                    </div>
                    <div class="rounded-md bg-slate-100 px-3 py-2 text-sm font-semibold text-slate-600">
                        {{ messages.length }} total
                    </div>
                </div>

                <div v-if="selectedMessage" class="h-full overflow-y-auto p-5">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p class="text-xs font-semibold uppercase text-slate-400">
                                {{ selectedMessage.sender_type }}
                            </p>
                            <h2 class="mt-1 text-2xl font-bold text-slate-900">
                                {{ selectedMessage.subject }}
                            </h2>
                            <p class="mt-2 text-sm text-slate-500">
                                From {{ selectedMessage.sender_name }}
                                <span v-if="selectedMessage.sender_email">({{ selectedMessage.sender_email }})</span>
                            </p>
                            <p v-if="selectedMessage.student_number" class="mt-1 text-sm text-slate-500">
                                Student No. {{ selectedMessage.student_number }}
                            </p>
                        </div>
                        <span
                            class="rounded-md px-3 py-1 text-xs font-bold"
                            :class="selectedMessage.read_at ? 'bg-slate-100 text-slate-500' : 'bg-sky-50 text-sky-700'"
                        >
                            {{ selectedMessage.read_at ? 'Read' : 'New' }}
                        </span>
                    </div>

                    <div class="mt-5 whitespace-pre-wrap rounded-md border border-slate-200 bg-slate-50 p-4 text-sm leading-6 text-slate-700">
                        {{ selectedMessage.body }}
                    </div>

                    <a
                        v-if="selectedMessage.attachment_url"
                        :href="selectedMessage.attachment_url"
                        target="_blank"
                        class="mt-4 flex items-center gap-3 rounded-md border border-slate-200 p-3 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                    >
                        <Image v-if="selectedMessage.is_image" class="h-5 w-5 text-brand" />
                        <FileText v-else class="h-5 w-5 text-brand" />
                        <span class="truncate">{{ selectedMessage.attachment_name }}</span>
                    </a>
                </div>

                <div v-else class="flex h-full flex-col items-center justify-center p-8 text-center text-slate-500">
                    <Inbox class="mb-3 h-10 w-10 text-slate-300" />
                    <p class="font-semibold">No messages yet.</p>
                </div>
            </section>

            <aside class="min-h-0 overflow-hidden rounded-md border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-100 px-4 py-3">
                    <h2 class="text-sm font-bold text-slate-900">People</h2>
                </div>
                <div class="h-full overflow-y-auto">
                    <button
                        v-for="message in messages"
                        :key="message.id"
                        type="button"
                        class="flex w-full gap-3 border-b border-slate-100 px-4 py-3 text-left hover:bg-slate-50"
                        :class="selectedMessage?.id === message.id ? 'bg-sky-50' : 'bg-white'"
                        @click="selectedId = message.id"
                    >
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-brand text-sm font-bold text-white">
                            {{ message.sender_name?.charAt(0)?.toUpperCase() || '?' }}
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center justify-between gap-2">
                                <p class="truncate text-sm font-bold text-slate-800">{{ message.sender_name }}</p>
                                <MailOpen v-if="message.read_at" class="h-4 w-4 shrink-0 text-slate-300" />
                                <span v-else class="h-2 w-2 shrink-0 rounded-full bg-brand"></span>
                            </div>
                            <p class="truncate text-xs font-semibold text-slate-500">{{ message.subject }}</p>
                            <p class="mt-1 line-clamp-2 text-xs leading-5 text-slate-500">{{ message.preview }}</p>
                            <p class="mt-1 text-xs text-slate-400">{{ message.created_label }}</p>
                        </div>
                    </button>
                </div>
            </aside>
        </div>
    </div>
</template>
