<template>
    <div class="space-y-4 p-4">
        <!-- Create New Request Button -->
        <div class="flex justify-end">
            <button
                @click="showForm = !showForm"
                class="rounded-lg bg-brand px-4 py-2 font-semibold text-white hover:bg-blue-700"
            >
                {{ showForm ? 'Cancel' : 'New Request' }}
            </button>
        </div>

        <!-- Create Form -->
        <form
            v-if="showForm"
            @submit.prevent="submitRequest"
            class="rounded-lg border border-slate-100 bg-white p-6 shadow-sm"
        >
            <h2 class="mb-4 text-lg font-bold text-slate-900">
                Submit Excuse Letter / Leave Request
            </h2>

            <div class="space-y-4">
                <!-- Subject -->
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-semibold text-slate-700"
                        >Subject *</label
                    >
                    <select
                        v-model="form.subject"
                        class="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand focus:outline-none"
                    >
                        <option value="">Select Subject</option>
                        <option value="Medical">Medical Excuse</option>
                        <option value="Personal">Personal Leave</option>
                        <option value="Family">Family Emergency</option>
                        <option value="Other">Other</option>
                    </select>
                    <span
                        v-if="form.errors?.subject"
                        class="text-xs text-red-600"
                        >{{ form.errors.subject }}</span
                    >
                </div>

                <!-- Date Range -->
                <div class="grid gap-4 md:grid-cols-2">
                    <div class="flex flex-col gap-2">
                        <label class="text-sm font-semibold text-slate-700"
                            >From Date *</label
                        >
                        <input
                            v-model="form.from_date"
                            type="date"
                            class="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand focus:outline-none"
                        />
                        <span
                            v-if="form.errors?.from_date"
                            class="text-xs text-red-600"
                            >{{ form.errors.from_date }}</span
                        >
                    </div>

                    <div class="flex flex-col gap-2">
                        <label class="text-sm font-semibold text-slate-700"
                            >To Date *</label
                        >
                        <input
                            v-model="form.to_date"
                            type="date"
                            class="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand focus:outline-none"
                        />
                        <span
                            v-if="form.errors?.to_date"
                            class="text-xs text-red-600"
                            >{{ form.errors.to_date }}</span
                        >
                    </div>
                </div>

                <!-- Reason -->
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-semibold text-slate-700"
                        >Reason *</label
                    >
                    <textarea
                        v-model="form.reason"
                        rows="4"
                        class="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand focus:outline-none"
                        placeholder="Explain the reason for your request..."
                    ></textarea>
                    <span
                        v-if="form.errors?.reason"
                        class="text-xs text-red-600"
                        >{{ form.errors.reason }}</span
                    >
                </div>

                <!-- Attachments -->
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-semibold text-slate-700"
                        >Supporting Document (Optional)</label
                    >
                    <input
                        type="file"
                        @change="handleFileUpload"
                        class="rounded-lg border border-slate-300 px-3 py-2 text-sm"
                        accept=".pdf,.doc,.docx,.jpg,.jpeg,.png"
                    />
                    <span class="text-xs text-slate-500"
                        >Accepted: PDF, DOC, DOCX, JPG, PNG (Max 5MB)</span
                    >
                </div>

                <!-- Submit Button -->
                <div class="flex gap-2 pt-4">
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="rounded-lg bg-brand px-4 py-2 font-semibold text-white hover:bg-blue-700 disabled:opacity-50"
                    >
                        {{
                            form.processing ? 'Submitting...' : 'Submit Request'
                        }}
                    </button>
                </div>
            </div>
        </form>

        <!-- Requests List -->
        <div
            class="overflow-x-auto rounded-lg border border-slate-100 bg-white shadow-sm"
        >
            <table class="w-full">
                <thead class="border-b border-slate-100 bg-slate-50">
                    <tr
                        class="text-xs font-semibold tracking-wider text-slate-600 uppercase"
                    >
                        <th class="px-4 py-3 text-left">Subject</th>
                        <th class="px-4 py-3 text-left">Date Range</th>
                        <th class="px-4 py-3 text-left">Reason</th>
                        <th class="px-4 py-3 text-left">Status</th>
                        <th class="px-4 py-3 text-left">Submitted</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr
                        v-for="request in excuseLetters"
                        :key="request.id"
                        class="text-sm text-slate-600 hover:bg-slate-50"
                    >
                        <td class="px-4 py-3 font-semibold">
                            {{ request.subject }}
                        </td>
                        <td class="px-4 py-3">
                            {{ formatDate(request.from_date) }} -
                            {{ formatDate(request.to_date) }}
                        </td>
                        <td class="line-clamp-1 px-4 py-3">
                            {{ request.reason }}
                        </td>
                        <td class="px-4 py-3">
                            <span
                                :class="getStatusBadgeClass(request.status)"
                                class="inline-block rounded px-2 py-1 text-xs font-semibold"
                            >
                                {{ request.status }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            {{ formatDate(request.created_at) }}
                        </td>
                    </tr>
                    <tr v-if="excuseLetters.length === 0">
                        <td
                            colspan="5"
                            class="px-4 py-6 text-center text-slate-400"
                        >
                            No requests submitted yet
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';

const props = defineProps({
    excuseLetters: {
        type: Array,
        default: () => [],
    },
});

const showForm = ref(false);
const form = useForm({
    subject: '',
    from_date: '',
    to_date: '',
    reason: '',
    attachment: null,
});

function handleFileUpload(event) {
    const file = event.target.files?.[0];
    if (file && file.size > 5 * 1024 * 1024) {
        alert('File size must be less than 5MB');
        event.target.value = '';
        return;
    }
    form.attachment = file;
}

function submitRequest() {
    form.post(route('student-parent.excuse-letters.store'), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            showForm.value = false;
        },
    });
}

function formatDate(date) {
    return new Date(date).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
}

function getStatusBadgeClass(status) {
    const baseClass = 'bg-opacity-10';
    switch (status?.toLowerCase()) {
        case 'approved':
            return `${baseClass} bg-green-500 text-green-700`;
        case 'rejected':
            return `${baseClass} bg-red-500 text-red-700`;
        case 'pending':
            return `${baseClass} bg-yellow-500 text-yellow-700`;
        default:
            return `${baseClass} bg-slate-500 text-slate-700`;
    }
}
</script>
