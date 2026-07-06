<script setup>
import { router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { Edit3, FilePlus2, Trash2 } from 'lucide-vue-next';

const props = defineProps({
    histories: { type: Array, default: () => [] },
    recentCases: { type: Array, default: () => [] },
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);
const editingId = ref(null);

const form = useForm({
    patient_name: '',
    patient_type: 'student',
    summary: '',
    notes: '',
    occurred_at: '',
    student_id: '',
    user_id: '',
});

const resetForm = () => {
    editingId.value = null;
    form.reset();
    form.patient_type = 'student';
};

const editHistory = (history) => {
    editingId.value = history.id;
    form.patient_name = history.patient_name || '';
    form.patient_type = history.patient_type || 'student';
    form.summary = history.summary || '';
    form.notes = history.notes || '';
    form.occurred_at = history.occurred_at_input || '';
    form.student_id = history.student_id || '';
    form.user_id = history.user_id || '';
};

const useCase = (clinicCase) => {
    editingId.value = null;
    form.patient_name = clinicCase.patient_name || '';
    form.patient_type = clinicCase.patient_type || 'student';
    form.summary = clinicCase.summary || '';
    form.notes = clinicCase.notes || '';
    form.occurred_at = clinicCase.occurred_at || '';
    form.student_id = '';
    form.user_id = '';
};

const submitHistory = () => {
    if (editingId.value) {
        form.put(route('clinic.patient-history.update', editingId.value), {
            preserveScroll: true,
            onSuccess: resetForm,
        });
        return;
    }

    form.post(route('clinic.patient-history.store'), {
        preserveScroll: true,
        onSuccess: resetForm,
    });
};

const deleteHistory = (history) => {
    if (!confirm(`Delete patient history for ${history.patient_name}?`)) return;

    router.delete(route('clinic.patient-history.destroy', history.id), {
        preserveScroll: true,
    });
};
</script>

<template>
    <div class="mx-auto max-w-7xl px-4 py-6">
        <section
            class="mb-5 flex flex-col gap-3 rounded-md bg-white p-5 shadow-sm sm:flex-row sm:items-center sm:justify-between"
        >
            <div>
                <h1 class="text-2xl font-black text-slate-950">
                    Patient History
                </h1>
                <p class="text-sm text-slate-500">
                    Create, update, and review clinic history records.
                </p>
            </div>
            <p
                v-if="flashSuccess"
                class="rounded-md bg-emerald-50 px-3 py-2 text-sm font-bold text-emerald-700"
            >
                {{ flashSuccess }}
            </p>
        </section>

        <section class="grid gap-5 xl:grid-cols-[380px_1fr]">
            <aside class="space-y-5">
                <form
                    class="rounded-md bg-white p-5 shadow-sm"
                    @submit.prevent="submitHistory"
                >
                    <div class="mb-4 flex items-center justify-between">
                        <h2 class="font-black text-slate-900">
                            {{ editingId ? 'Edit History' : 'New History' }}
                        </h2>
                        <button
                            type="button"
                            class="text-xs font-bold text-slate-500"
                            @click="resetForm"
                        >
                            Clear
                        </button>
                    </div>

                    <div class="grid gap-3">
                        <label class="text-sm font-semibold text-slate-700">
                            Patient Name
                            <input
                                v-model="form.patient_name"
                                required
                                class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                            />
                        </label>
                        <label class="text-sm font-semibold text-slate-700">
                            Patient Type
                            <select
                                v-model="form.patient_type"
                                class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                            >
                                <option value="student">Student</option>
                                <option value="instructor">Instructor</option>
                                <option value="visitor">Visitor</option>
                                <option value="user">User</option>
                            </select>
                        </label>
                        <label class="text-sm font-semibold text-slate-700">
                            Summary
                            <input
                                v-model="form.summary"
                                required
                                class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                            />
                        </label>
                        <label class="text-sm font-semibold text-slate-700">
                            Occurred At
                            <input
                                v-model="form.occurred_at"
                                type="datetime-local"
                                class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                            />
                        </label>
                        <label class="text-sm font-semibold text-slate-700">
                            Notes
                            <textarea
                                v-model="form.notes"
                                rows="5"
                                class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                            />
                        </label>
                        <button
                            class="inline-flex items-center justify-center gap-2 rounded-md bg-brand px-4 py-2 text-sm font-bold text-white"
                            :disabled="form.processing"
                        >
                            <FilePlus2 class="h-4 w-4" />
                            {{
                                form.processing
                                    ? 'Saving...'
                                    : editingId
                                      ? 'Update History'
                                      : 'Save History'
                            }}
                        </button>
                    </div>
                </form>

                <div class="rounded-md bg-white p-5 shadow-sm">
                    <h2 class="font-black text-slate-900">
                        Start From Recent Case
                    </h2>
                    <div class="mt-3 max-h-80 space-y-2 overflow-y-auto">
                        <button
                            v-for="clinicCase in recentCases"
                            :key="clinicCase.id"
                            type="button"
                            class="w-full rounded-md border border-slate-100 p-3 text-left text-sm hover:bg-slate-50"
                            @click="useCase(clinicCase)"
                        >
                            <span class="block font-bold text-slate-800">{{
                                clinicCase.patient_name
                            }}</span>
                            <span class="block text-xs text-slate-500">{{
                                clinicCase.summary
                            }}</span>
                        </button>
                        <p
                            v-if="recentCases.length === 0"
                            class="text-sm text-slate-400"
                        >
                            No recent cases available.
                        </p>
                    </div>
                </div>
            </aside>

            <section class="rounded-md bg-white p-5 shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[860px] text-left text-sm">
                        <thead
                            class="bg-slate-50 text-xs text-slate-500 uppercase"
                        >
                            <tr>
                                <th class="px-3 py-3">Patient</th>
                                <th class="px-3 py-3">Type</th>
                                <th class="px-3 py-3">Summary</th>
                                <th class="px-3 py-3">Occurred</th>
                                <th class="px-3 py-3">Notes</th>
                                <th class="px-3 py-3">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="history in histories" :key="history.id">
                                <td class="px-3 py-3 font-bold text-slate-900">
                                    {{ history.patient_name || 'N/A' }}
                                </td>
                                <td class="px-3 py-3 text-slate-600">
                                    {{ history.patient_type || 'N/A' }}
                                </td>
                                <td class="px-3 py-3 text-slate-700">
                                    {{ history.summary }}
                                </td>
                                <td class="px-3 py-3 text-slate-500">
                                    {{ history.occurred_at || 'N/A' }}
                                </td>
                                <td class="px-3 py-3 text-slate-500">
                                    {{ history.notes || '' }}
                                </td>
                                <td class="px-3 py-3">
                                    <div class="flex gap-2">
                                        <button
                                            class="rounded-md border border-slate-200 p-2 text-slate-600"
                                            @click="editHistory(history)"
                                        >
                                            <Edit3 class="h-4 w-4" />
                                        </button>
                                        <button
                                            class="rounded-md border border-rose-200 p-2 text-rose-600"
                                            @click="deleteHistory(history)"
                                        >
                                            <Trash2 class="h-4 w-4" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="histories.length === 0">
                                <td
                                    colspan="6"
                                    class="px-3 py-10 text-center text-slate-400"
                                >
                                    No patient history yet.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </section>
    </div>
</template>
