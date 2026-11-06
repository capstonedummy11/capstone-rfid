<script setup>
import { Send, X } from 'lucide-vue-next';

defineProps({
    open: { type: Boolean, default: false },
    decision: { type: String, default: 'approved' },
    recipients: { type: Array, default: () => [] },
    studentAvailable: { type: Boolean, default: false },
    studentEmail: { type: String, default: '' },
    emailSubject: { type: String, default: '' },
    emailBody: { type: String, default: '' },
    errors: { type: Object, default: () => ({}) },
    processing: { type: Boolean, default: false },
});

const emit = defineEmits([
    'update:recipients',
    'update:emailSubject',
    'update:emailBody',
    'close',
    'submit',
]);

const setRecipient = (event) =>
    emit('update:recipients', event.target.checked ? ['student'] : []);
</script>

<template>
    <div
        v-if="open"
        class="fixed inset-0 z-[70] flex items-center justify-center bg-slate-950/50 p-4"
        @click.self="emit('close')"
    >
        <section class="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-xl bg-white shadow-2xl">
            <header class="flex items-start justify-between border-b border-slate-200 px-5 py-4">
                <div>
                    <h3 class="text-lg font-bold text-slate-900">
                        {{ decision === 'approved' ? 'Approve' : 'Deny' }} excuse letter
                    </h3>
                    <p class="text-sm text-slate-500">
                        Select the Student email recipient and review the message before sending.
                    </p>
                </div>
                <button type="button" class="rounded-md p-2 text-slate-400 hover:bg-slate-100" aria-label="Close" @click="emit('close')">
                    <X class="h-5 w-5" />
                </button>
            </header>
            <form class="space-y-4 p-5" @submit.prevent="emit('submit')">
                <fieldset>
                    <legend class="text-xs font-bold text-slate-500 uppercase">Send email to</legend>
                    <label class="mt-2 flex items-start gap-3 rounded-lg border border-slate-200 p-3" :class="studentAvailable ? 'cursor-pointer hover:bg-slate-50' : 'cursor-not-allowed bg-slate-50 opacity-60'">
                        <input type="checkbox" value="student" class="mt-1 h-4 w-4" :checked="recipients.includes('student')" :disabled="!studentAvailable" @change="setRecipient" />
                        <span class="min-w-0 text-sm"><strong class="block text-slate-800">Student</strong><span class="block truncate text-slate-500">{{ studentEmail || 'No valid email' }}</span></span>
                    </label>
                    <p v-if="errors.recipients" class="mt-1 text-sm font-semibold text-rose-600">{{ errors.recipients }}</p>
                </fieldset>
                <label class="block text-xs font-bold text-slate-500 uppercase">Subject<input :value="emailSubject" type="text" maxlength="255" required class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm font-normal" @input="emit('update:emailSubject', $event.target.value)" /><span v-if="errors.email_subject" class="mt-1 block text-sm font-semibold text-rose-600">{{ errors.email_subject }}</span></label>
                <label class="block text-xs font-bold text-slate-500 uppercase">Email template<textarea :value="emailBody" maxlength="5000" required class="mt-1 min-h-48 w-full rounded-md border border-slate-300 px-3 py-2 text-sm font-normal text-slate-700" @input="emit('update:emailBody', $event.target.value)" /><span v-if="errors.email_body" class="mt-1 block text-sm font-semibold text-rose-600">{{ errors.email_body }}</span></label>
                <div class="flex justify-end gap-2">
                    <button type="button" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600" @click="emit('close')">Cancel</button>
                    <button type="submit" class="inline-flex items-center gap-2 rounded-md px-4 py-2 text-sm font-bold text-white disabled:opacity-60" :class="decision === 'approved' ? 'bg-emerald-600 hover:bg-emerald-700' : 'bg-rose-600 hover:bg-rose-700'" :disabled="processing || !recipients.length">
                        <Send class="h-4 w-4" />{{ processing ? 'Sending...' : `${decision === 'approved' ? 'Approve' : 'Deny'} and send email` }}
                    </button>
                </div>
            </form>
        </section>
    </div>
</template>
