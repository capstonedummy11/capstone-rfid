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
            <!-- Message content area, redesigned as chat bubbles to match reference -->
            <section class="flex min-h-0 flex-col overflow-hidden rounded-md border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-100 px-5 py-4">
                    <h1 class="text-xl font-bold text-slate-900">
                        {{ selectedMessage?.sender_name || 'Messages' }}
                    </h1>
                </div>

                <div v-if="selectedMessage" class="flex-1 overflow-y-auto bg-slate-50 p-5 space-y-4">
                    <!-- Bubble 1: subject / notice -->
                    <div class="flex items-start gap-3">
                        <div class="mt-1 h-9 w-9 shrink-0 rounded-full bg-slate-200"></div>

                        <div class="max-w-md space-y-1.5">
                            <div class="rounded-2xl rounded-tl-sm bg-white p-4 shadow-sm">
                                <p class="text-xs font-bold uppercase tracking-wide text-slate-900">
                                    {{ selectedMessage.sender_name }}
                                </p>
                                <p class="mt-1 whitespace-pre-wrap text-sm leading-6 text-slate-700">
                                    {{ selectedMessage.subject }}
                                </p>

                                <a
                                    v-if="selectedMessage.attachment_url"
                                    :href="selectedMessage.attachment_url"
                                    target="_blank"
                                    class="mt-3 flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2.5 text-sm font-medium text-slate-500 hover:bg-slate-50"
                                >
                                    <Image v-if="selectedMessage.is_image" class="h-4 w-4 shrink-0 text-slate-400" />
                                    <FileText v-else class="h-4 w-4 shrink-0 text-slate-400" />
                                    <span class="truncate">{{ selectedMessage.attachment_name }}</span>
                                </a>

                                <a
                                    v-if="selectedMessage.link_url"
                                    :href="selectedMessage.link_url"
                                    target="_blank"
                                    class="mt-3 inline-block text-sm font-medium text-sky-600 underline hover:text-sky-700"
                                >
                                    {{ selectedMessage.link_label || 'Click here to go to the Site' }}
                                </a>
                            </div>

                            <p class="px-1 text-xs text-slate-400">
                                {{ selectedMessage.created_label }}
                            </p>
                        </div>
                    </div>

                    <!-- Bubble 2: body / follow-up message -->
                    <div v-if="selectedMessage.body" class="flex items-start gap-3">
                        <div class="mt-1 h-9 w-9 shrink-0 rounded-full bg-slate-200"></div>
                        <div class="max-w-md space-y-1.5">
                            <div class="rounded-2xl rounded-tl-sm bg-white p-4 shadow-sm">
                                <p class="text-xs font-bold uppercase tracking-wide text-slate-900">
                                    {{ selectedMessage.sender_name }}
                                </p>
                                <p class="mt-1 whitespace-pre-wrap text-sm leading-6 text-slate-700">
                                    {{ selectedMessage.body }}
                                </p>
                            </div>
                            <p class="px-1 text-xs text-slate-400">
                                {{ selectedMessage.created_label }}
                            </p>
                        </div>
                    </div>
                </div>

                <div v-else class="flex flex-1 flex-col items-center justify-center bg-slate-50 p-8 text-center text-slate-500">
                    <Inbox class="mb-3 h-10 w-10 text-slate-300" />
                    <p class="font-semibold">No messages yet.</p>
                </div>

                <!-- Bottom quick search bar, matching reference -->
                <div class="border-t border-slate-100 bg-white px-4 py-3">
                    <div class="flex items-center gap-3 rounded-full border border-slate-200 bg-slate-50 px-4 py-2.5">
                        <input
                            type="text"
                            placeholder="Quick search..."
                            class="w-full bg-transparent text-sm text-slate-500 placeholder:text-slate-400 focus:outline-none"
                        />
                        <button type="button" class="shrink-0 text-slate-400 hover:text-slate-600">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="22" y1="2" x2="11" y2="13"></line>
                                <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                            </svg>
                        </button>
                    </div>
                </div>
            </section>

            <!-- People list panel, unchanged -->
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