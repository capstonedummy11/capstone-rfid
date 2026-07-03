<template>
    <div class="space-y-4 p-4">
        <!-- Messages List -->
        <div class="rounded-lg border border-slate-100 bg-white shadow-sm">
            <div class="border-b border-slate-100 bg-slate-50 px-4 py-3">
                <h2
                    class="text-sm font-bold tracking-widest text-slate-600 uppercase"
                >
                    Messages & Notifications
                </h2>
            </div>

            <div class="max-h-96 overflow-y-auto">
                <div
                    v-for="message in messages"
                    :key="message.id"
                    class="cursor-pointer border-b border-slate-100 p-4 hover:bg-slate-50"
                >
                    <div class="flex items-start gap-4">
                        <div
                            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-slate-200 text-xs font-bold text-slate-600"
                        >
                            {{
                                message.sender_name?.charAt(0)?.toUpperCase() ||
                                '?'
                            }}
                        </div>
                        <div class="min-w-0 flex-1">
                            <div
                                class="mb-1 flex items-center justify-between gap-2"
                            >
                                <span class="font-semibold text-slate-900">{{
                                    message.sender_name
                                }}</span>
                                <span class="shrink-0 text-xs text-slate-400">{{
                                    formatTimeAgo(message.created_at)
                                }}</span>
                            </div>
                            <p class="line-clamp-2 text-sm text-slate-600">
                                {{ message.subject }}
                            </p>
                            <p
                                v-if="message.body"
                                class="mt-1 line-clamp-1 text-xs text-slate-500"
                            >
                                {{ message.body }}
                            </p>
                            <div class="mt-2 flex gap-2">
                                <span
                                    v-if="!message.read_at"
                                    class="inline-block rounded bg-blue-100 px-2 py-1 text-xs font-semibold text-blue-700"
                                >
                                    New
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <div
                    v-if="messages.length === 0"
                    class="px-4 py-8 text-center text-slate-400"
                >
                    No messages yet
                </div>
            </div>
        </div>

        <!-- Message Detail Modal would go here if needed -->
        <!-- For now, messages are simple notifications/announcements -->
    </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    messages: {
        type: Array,
        default: () => [],
    },
});

function formatTimeAgo(date) {
    const now = new Date();
    const messageDate = new Date(date);
    const diffMs = now - messageDate;
    const diffMins = Math.floor(diffMs / 60000);
    const diffHours = Math.floor(diffMins / 60);
    const diffDays = Math.floor(diffHours / 24);

    if (diffMins < 1) return 'Just now';
    if (diffMins < 60) return `${diffMins}m ago`;
    if (diffHours < 24) return `${diffHours}h ago`;
    if (diffDays < 7) return `${diffDays}d ago`;

    return messageDate.toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
    });
}
</script>
