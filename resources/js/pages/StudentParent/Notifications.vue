<script setup>
defineProps({
    notifications: { type: Array, default: () => [] },
});
</script>

<template>
    <div class="p-4 sm:p-6">
        <section class="rounded-md border border-slate-200 bg-white p-5 shadow-sm">
            <h1 class="text-xl font-bold text-slate-900">Notifications</h1>
            <div class="mt-4 divide-y divide-slate-100">
                <article v-for="notification in notifications" :key="notification.id" class="py-4">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2 class="font-bold text-slate-900">{{ notification.title }}</h2>
                            <p class="mt-2 whitespace-pre-line text-sm text-slate-600">{{ notification.body }}</p>
                        </div>
                        <span class="rounded-md bg-slate-100 px-3 py-1 text-xs font-bold uppercase text-slate-600">{{ notification.event }}</span>
                    </div>
                    <p class="mt-2 text-xs text-slate-400">{{ notification.created_at }}</p>
                </article>
                <p v-if="notifications.length === 0" class="py-8 text-center text-sm text-slate-400">No notifications yet.</p>
            </div>
        </section>
    </div>
</template>
