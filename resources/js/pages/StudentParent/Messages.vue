<script setup>
import { useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

defineProps({
    student: { type: Object, default: null },
    messages: { type: Array, default: () => [] },
    instructors: { type: Array, default: () => [] },
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);
const currentRole = computed(() => String(page.props.auth?.user?.role || '').toLowerCase());

const form = useForm({
    instructor_user_id: '',
    subject: '',
    body: '',
    attachment: null,
});

const sendMessage = () => {
    form.post(route('student-parent.messages.store'), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
};
</script>

<template>
    <div class="p-4 sm:p-6">
        <div class="mx-auto grid max-w-7xl gap-5 lg:grid-cols-[380px_1fr]">
            <section class="rounded-md border border-slate-200 bg-white p-5 shadow-sm">
                <h1 class="text-lg font-bold text-slate-900">New Message</h1>
                <p class="mt-1 text-sm text-slate-500">
                    Parents can view linked student messages. Students only see student-authored messages.
                </p>
                <p v-if="flashSuccess" class="mt-3 rounded-md bg-emerald-50 px-3 py-2 text-sm font-semibold text-emerald-700">{{ flashSuccess }}</p>

                <form class="mt-4 flex flex-col gap-3" @submit.prevent="sendMessage">
                    <label class="text-sm font-semibold text-slate-700">
                        Instructor
                        <select v-model="form.instructor_user_id" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                            <option value="">No instructor selected</option>
                            <option v-for="instructor in instructors" :key="instructor.user_id" :value="instructor.user_id">
                                {{ instructor.name }}
                            </option>
                        </select>
                    </label>
                    <label class="text-sm font-semibold text-slate-700">
                        Subject
                        <input v-model="form.subject" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm" required />
                    </label>
                    <label class="text-sm font-semibold text-slate-700">
                        Message
                        <textarea v-model="form.body" class="mt-1 h-40 w-full rounded-md border border-slate-300 px-3 py-2 text-sm" required />
                    </label>
                    <label class="text-sm font-semibold text-slate-700">
                        Attachment
                        <input type="file" class="mt-1 w-full text-sm" @change="form.attachment = $event.target.files?.[0] || null" />
                    </label>
                    <button class="rounded-md bg-brand px-4 py-2 text-sm font-bold text-white" :disabled="form.processing">
                        {{ form.processing ? 'Sending...' : 'Send Message' }}
                    </button>
                </form>
            </section>

            <section class="rounded-md border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="text-lg font-bold text-slate-900">Messages</h2>
                    <span class="rounded-md bg-slate-100 px-3 py-1 text-xs font-bold uppercase text-slate-500">{{ currentRole }}</span>
                </div>
                <div class="mt-4 divide-y divide-slate-100">
                    <article v-for="message in messages" :key="message.id" class="py-4">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <h3 class="font-bold text-slate-900">{{ message.subject }}</h3>
                                <p class="text-xs text-slate-500">
                                    {{ message.sender }} Â| {{ message.sender_role }}
                                    <span v-if="message.instructor"> Â| To {{ message.instructor }}</span>
                                </p>
                            </div>
                            <span class="text-xs text-slate-400">{{ message.created_at }}</span>
                        </div>
                        <p class="mt-3 whitespace-pre-line text-sm text-slate-700">{{ message.body }}</p>
                        <a v-if="message.attachment_url" :href="message.attachment_url" target="_blank" class="mt-2 inline-block text-sm font-semibold text-brand">
                            {{ message.attachment_name || 'Attachment' }}
                        </a>
                    </article>
                    <p v-if="messages.length === 0" class="py-8 text-center text-sm text-slate-400">No messages yet.</p>
                </div>
            </section>
        </div>
    </div>
</template>
