<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { FileText, X } from 'lucide-vue-next';
import ExcuseLetterReviewModal from '@/components/ExcuseLetterReviewModal.vue';

const props = defineProps({
    subject: { type: Object, required: true },
    student: { type: Object, required: true },
    history: { type: Array, default: () => [] },
    absentDefaultDays: { type: Number, default: 15 },
});

const page = usePage();
const currentRole = computed(() => String(page.props.auth?.user?.role || '').toLowerCase());
const selectedLetter = ref(null);
const decisionForm = useForm({ decision: 'approved', recipients: [], email_subject: '', email_body: '' });
const badgeClass = (status) => status === 'approved' ? 'bg-emerald-100 text-emerald-800' : status === 'denied' ? 'bg-rose-100 text-rose-800' : 'bg-blue-100 text-blue-800';
const openLetter = (letter) => { selectedLetter.value = letter; };
const openDecision = (letter, decision) => {
    if (!letter.can_review) return;
    selectedLetter.value = { ...letter, student: props.student.name };
    decisionForm.clearErrors();
    decisionForm.decision = decision;
    decisionForm.recipients = letter.student_email_available ? ['student'] : [];
    decisionForm.email_subject = `Excuse Letter ${decision === 'approved' ? 'Approved' : 'Denied'} - ${props.student.name}`;
    decisionForm.email_body = `Dear ${props.student.name},\n\nYour excuse letter has been ${decision} by the instructor.\n\nSubject: ${letter.subject}\n\nRegards,\n${page.props.auth?.user?.name || 'Instructor'}`;
};
const closeDecision = () => {
    if (decisionForm.processing) return;
    selectedLetter.value = null;
    decisionForm.reset();
    decisionForm.clearErrors();
};
const submitDecision = () => {
    if (!selectedLetter.value?.message_id) return;
    decisionForm.put(route('messages.excuse-letters.review', { message: selectedLetter.value.message_id }), { preserveScroll: true, onSuccess: closeDecision });
};
</script>

<template>
    <Head :title="`${student.name} Attendance History`" />
    <main class="min-h-screen bg-slate-50 px-4 py-6 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-5xl">
            <Link
                :href="route('admin.attendance.summary', subject.id)"
                class="text-sm font-bold text-blue-700"
                >← Student summary</Link
            >
            <header
                class="mt-4 rounded-3xl bg-gradient-to-r from-slate-950 to-blue-800 p-7 text-white"
            >
                <p
                    class="text-xs font-bold tracking-widest text-blue-200 uppercase"
                >
                    {{ subject.code }} · {{ subject.name }}
                </p>
                <h1 class="mt-2 text-3xl font-black">{{ student.name }}</h1>
                <p class="mt-2 text-sm text-blue-100">
                    {{ student.student_number }} · Complete attendance history
                </p>
            </header>
            <div
                class="mt-5 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"
            >
                <table class="min-w-full text-sm">
                    <thead
                        class="bg-slate-100 text-left text-xs text-slate-600 uppercase"
                    >
                        <tr>
                            <th class="px-4 py-3">Date</th>
                            <th class="px-4 py-3">Schedule</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Time in</th>
                            <th class="px-4 py-3">Time out</th>
                            <th class="px-4 py-3">Remarks / excuse letter</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr
                            v-for="item in history"
                            :key="item.session_id"
                            class="hover:bg-slate-50"
                        >
                            <td class="px-4 py-3 font-bold text-slate-900">
                                {{ item.date_label }}
                                <span
                                    v-if="item.session_type === 'online'"
                                    class="ml-2 rounded-full bg-violet-100 px-2 py-0.5 text-[10px] font-black text-violet-700 uppercase"
                                    >Online</span
                                >
                            </td>
                            <td class="px-4 py-3 text-slate-600">
                                {{ item.schedule }}<br /><span
                                    class="text-xs text-slate-400"
                                    >{{ item.room }}</span
                                >
                            </td>
                            <td class="px-4 py-3">
                                <span
                                    class="rounded-full bg-blue-50 px-3 py-1 text-xs font-bold text-blue-700"
                                    >{{ item.status }}</span
                                >
                            </td>
                            <td class="px-4 py-3">{{ item.time_in || '—' }}</td>
                            <td class="px-4 py-3">
                                {{ item.time_out || '—' }}
                            </td>
                            <td class="px-4 py-3 text-slate-500">
                                <div v-if="currentRole === 'instructor' && item.excuse_letters?.length" class="mb-2 space-y-2">
                                    <article v-for="letter in item.excuse_letters" :key="letter.message_id" class="rounded-lg border border-slate-200 bg-white p-3 text-slate-700">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <button type="button" class="inline-flex items-center gap-1 font-bold text-blue-700 hover:underline" @click="openLetter({ ...letter, student: student.name })"><FileText class="h-4 w-4" />Excuse letter</button>
                                            <span class="rounded-full px-2 py-0.5 text-[11px] font-bold capitalize" :class="badgeClass(letter.status)">{{ letter.status }}</span>
                                            <span v-if="letter.status === 'pending' && letter.can_review && letter.hours_remaining <= 24" class="rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-bold text-amber-900">{{ letter.hours_remaining }} hour(s) left to review</span>
                                        </div>
                                        <p class="mt-1 text-xs">{{ letter.subject }} · {{ letter.from_date }} to {{ letter.to_date }}</p>
                                        <div v-if="letter.can_review && letter.status === 'pending'" class="mt-2 flex gap-2">
                                            <button type="button" class="rounded bg-emerald-600 px-2 py-1 text-xs font-bold text-white" @click="openDecision(letter, 'approved')">Approve</button>
                                            <button type="button" class="rounded bg-rose-600 px-2 py-1 text-xs font-bold text-white" @click="openDecision(letter, 'denied')">Deny</button>
                                        </div>
                                    </article>
                                </div>
                                {{ item.remarks || '—' }}
                            </td>
                        </tr>
                    </tbody>
                </table>
                <p
                    v-if="!history.length"
                    class="p-10 text-center text-sm text-slate-500"
                >
                    No attendance sessions are available.
                </p>
            </div>
        </div>
    </main>
    <div v-if="selectedLetter && !decisionForm.email_subject" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 p-4" @click.self="selectedLetter = null">
        <section class="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-xl bg-white shadow-2xl">
            <header class="flex items-center justify-between border-b border-slate-200 p-5"><div><h2 class="text-lg font-bold text-slate-900">Excuse letter preview</h2><p class="text-sm text-slate-500">{{ selectedLetter.student }} · {{ selectedLetter.from_date }} to {{ selectedLetter.to_date }}</p></div><button type="button" class="rounded-md p-2 text-slate-500 hover:bg-slate-100" aria-label="Close preview" @click="selectedLetter = null"><X class="h-5 w-5" /></button></header>
            <div class="space-y-4 p-5"><p><strong>Subject:</strong> {{ selectedLetter.subject }}</p><p class="whitespace-pre-line"><strong>Reason:</strong> {{ selectedLetter.reason }}</p><a v-if="selectedLetter.attachment_url" :href="selectedLetter.attachment_url" class="text-sm font-semibold text-blue-700 underline">{{ selectedLetter.attachment_name || 'Open attachment' }}</a><span class="rounded-full px-3 py-1 text-xs font-bold capitalize" :class="badgeClass(selectedLetter.status)">{{ selectedLetter.status }}</span><p v-if="selectedLetter.status === 'pending' && selectedLetter.can_review && selectedLetter.hours_remaining <= 24" class="rounded-lg bg-amber-50 p-3 text-sm font-semibold text-amber-900">Please review this letter within {{ selectedLetter.hours_remaining }} hour(s). It will leave the configured attendance threshold after {{ selectedLetter.expires_at }}.</p></div>
        </section>
    </div>
    <ExcuseLetterReviewModal :open="Boolean(selectedLetter && decisionForm.email_subject)" :decision="decisionForm.decision" :recipients="decisionForm.recipients" :student-available="Boolean(selectedLetter?.student_email_available)" :student-email="selectedLetter?.student_email || ''" :email-subject="decisionForm.email_subject" :email-body="decisionForm.email_body" :errors="decisionForm.errors" :processing="decisionForm.processing" @update:recipients="decisionForm.recipients = $event" @update:email-subject="decisionForm.email_subject = $event" @update:email-body="decisionForm.email_body = $event" @close="closeDecision" @submit="submitDecision" />
</template>
