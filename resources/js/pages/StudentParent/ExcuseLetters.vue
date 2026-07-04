<script setup>
import { useForm, usePage } from '@inertiajs/vue3';
import SuccessModal from '@/components/StudentPortal/SuccessModal.vue';
import { computed } from 'vue';

const props = defineProps({
    student: { type: Object, default: null },
    letters: { type: Array, default: () => [] },
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);
const showSuccessModal = computed(() => Boolean(flashSuccess.value));

const form = useForm({
    subject: '',
    from_date: '',
    to_date: '',
    reason: '',
    attachment: null,
});

const submitLetter = () => {
    form.post(route('student-parent.excuse-letters.store'), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
};
</script>

<template>
    <div class="p-4 sm:p-6">
        <div class="mx-auto grid max-w-7xl gap-5 lg:grid-cols-[360px_1fr]">
            <section class="rounded-md border border-slate-200 bg-white p-5 shadow-sm">
                <h1 class="text-lg font-bold text-slate-900">Excuse Letter</h1>
                <form class="mt-4 flex flex-col gap-3" @submit.prevent="submitLetter">
                    <label class="text-xs font-bold uppercase text-slate-500">
                        Subject
                        <input v-model="form.subject" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm normal-case" required />
                    </label>
                    <div class="grid grid-cols-2 gap-2">
                        <label class="text-xs font-bold uppercase text-slate-500">
                            From
                            <input v-model="form.from_date" type="date" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm" required />
                        </label>
                        <label class="text-xs font-bold uppercase text-slate-500">
                            To
                            <input v-model="form.to_date" type="date" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm" required />
                        </label>
                    </div>
                    <label class="text-xs font-bold uppercase text-slate-500">
                        Reason
                        <textarea v-model="form.reason" class="mt-1 h-36 w-full rounded-md border border-slate-300 px-3 py-2 text-sm normal-case" required />
                    </label>
                    <label class="text-xs font-bold uppercase text-slate-500">
                        Attachment
                        <input type="file" class="mt-1 w-full text-sm normal-case" @change="form.attachment = $event.target.files?.[0] || null" />
                    </label>
                    <button class="rounded-md bg-brand px-4 py-2 text-sm font-bold text-white" :disabled="form.processing">
                        {{ form.processing ? 'Submitting...' : 'Submit Letter' }}
                    </button>
                </form>

                <div class="mt-6">
                    <h2 class="text-sm font-bold text-slate-900">Submitted Letters</h2>
                    <div class="mt-3 space-y-2">
                        <div v-for="letter in letters" :key="letter.id" class="rounded-md border border-slate-200 p-3 text-sm">
                            <div class="font-semibold text-slate-900">{{ letter.subject }}</div>
                            <div class="text-xs text-slate-500">{{ letter.from_date }} to {{ letter.to_date }} Â| {{ letter.status }}</div>
                        </div>
                        <p v-if="letters.length === 0" class="text-sm text-slate-400">No excuse letters yet.</p>
                    </div>
                </div>
            </section>

            <section class="min-h-[720px] rounded-md border border-slate-200 bg-white p-10 shadow-sm">
                <div class="mx-auto max-w-3xl text-sm leading-6 text-slate-800">
                    <h2 class="text-center text-xl font-bold text-slate-950">Excuse Letter</h2>
                    <div class="mt-14">
                        <p>Date: {{ form.from_date || '[Date Submitted]' }}</p>
                        <p>Dear Instructor,</p>
                    </div>
                    <p class="mt-12">
                        Good day. I am {{ student?.name || '[Student Name]' }} from {{ student?.section || '[Section]' }}.
                        I would like to request consideration for my absence or late attendance for
                        {{ form.subject || '[Subject]' }} from {{ form.from_date || '[Start Date]' }} to {{ form.to_date || '[End Date]' }}.
                    </p>
                    <p class="mt-5 whitespace-pre-line">{{ form.reason || '[Reason]' }}</p>
                    <p class="mt-8">
                        I respectfully ask for your consideration regarding this matter. I will make sure to catch up on any missed requirements.
                    </p>
                    <div class="mt-16">
                        <p>Sincerely,</p>
                        <p>{{ student?.name || '[Student Name]' }}</p>
                    </div>
                </div>
            </section>
        </div>

        <SuccessModal
            :show="showSuccessModal"
            title="Success"
            message="Take Care of yourself."
            :primary-href="letters[0]?.attachment_url || ''"
            primary-text="Download Letter"
            :secondary-href="route('student-parent.attendance')"
            secondary-text="Back to Attendance Page"
        />
    </div>
</template>
