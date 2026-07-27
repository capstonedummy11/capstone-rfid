<script setup>
import { router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { ClipboardPlus, FileClock, Save } from 'lucide-vue-next';

const props = defineProps({
    cases: { type: Array, default: () => [] },
    emergencyTypes: { type: Array, default: () => [] },
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);
const editingId = ref(null);

const form = useForm({
    emergency_alert_id: '',
    patient_name: '',
    patient_type: 'student',
    case_type: '',
    symptoms: '',
    action_taken: '',
    notes: '',
    status: 'open',
    occurred_at: '',
    student_id: '',
    user_id: '',
});

const resetForm = () => {
    editingId.value = null;
    form.reset();
    form.patient_type = 'student';
    form.status = 'open';
};

const editCase = (clinicCase) => {
    editingId.value = clinicCase.id;
    form.emergency_alert_id = clinicCase.emergency_alert_id || '';
    form.patient_name = clinicCase.patient_name || '';
    form.patient_type = clinicCase.patient_type || 'student';
    form.case_type = clinicCase.case_type || '';
    form.symptoms = clinicCase.symptoms || '';
    form.action_taken = clinicCase.action_taken || '';
    form.notes = clinicCase.notes || '';
    form.status = clinicCase.status || 'open';
    form.occurred_at = clinicCase.occurred_at_input || '';
    form.student_id = clinicCase.student_id || '';
    form.user_id = clinicCase.user_id || '';
};

const submitCase = () => {
    if (editingId.value) {
        form.put(route('clinic.case-logs.update', editingId.value), {
            preserveScroll: true,
            onSuccess: resetForm,
        });
        return;
    }

    form.post(route('clinic.case-logs.store'), {
        preserveScroll: true,
        onSuccess: resetForm,
    });
};

const createHistory = (clinicCase) => {
    router.post(
        route('clinic.case-logs.history', clinicCase.id),
        {},
        { preserveScroll: true },
    );
};

const statusClass = (status) => {
    if (status === 'resolved') return 'bg-emerald-50 text-emerald-700';
    if (status === 'monitoring') return 'bg-sky-50 text-sky-700';
    if (status === 'referred') return 'bg-amber-50 text-amber-700';
    return 'bg-rose-50 text-rose-700';
};
</script>

<template>
    <div class="mx-auto max-w-7xl px-4 py-6">
        <section
            class="mb-5 flex flex-col gap-3 rounded-md bg-white p-5 shadow-sm sm:flex-row sm:items-center sm:justify-between"
        >
            <div>
                <h1 class="text-2xl font-black text-slate-950">Case Logs</h1>
                <p class="text-sm text-slate-500">
                    Create clinic cases, update treatment notes, and send
                    records to patient history.
                </p>
            </div>
            <p
                v-if="flashSuccess"
                class="rounded-md bg-emerald-50 px-3 py-2 text-sm font-bold text-emerald-700"
            >
                {{ flashSuccess }}
            </p>
        </section>

        <section class="grid gap-5 xl:grid-cols-[390px_1fr]">
            <form
                class="rounded-md bg-white p-5 shadow-sm"
                @submit.prevent="submitCase"
            >
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="font-black text-slate-900">
                        {{ editingId ? 'Edit Case' : 'New Case' }}
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
                    <div class="grid grid-cols-2 gap-2">
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
                            Status
                            <select
                                v-model="form.status"
                                required
                                class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                            >
                                <option value="open">Open</option>
                                <option value="monitoring">Monitoring</option>
                                <option value="resolved">Resolved</option>
                                <option value="referred">Referred</option>
                            </select>
                        </label>
                    </div>
                    <label class="text-sm font-semibold text-slate-700">
                        Case Type
                        <input
                            v-model="form.case_type"
                            list="case-type-options"
                            class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                        />
                        <datalist id="case-type-options">
                            <option
                                v-for="type in emergencyTypes"
                                :key="type.emergency_type_id"
                                :value="type.name"
                            />
                        </datalist>
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
                        Symptoms
                        <textarea
                            v-model="form.symptoms"
                            rows="3"
                            class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                        />
                    </label>
                    <label class="text-sm font-semibold text-slate-700">
                        Action Taken
                        <textarea
                            v-model="form.action_taken"
                            rows="3"
                            class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                        />
                    </label>
                    <label class="text-sm font-semibold text-slate-700">
                        Notes
                        <textarea
                            v-model="form.notes"
                            rows="3"
                            class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                        />
                    </label>
                    <button
                        class="inline-flex items-center justify-center gap-2 rounded-md bg-brand px-4 py-2 text-sm font-bold text-white"
                        :disabled="form.processing"
                    >
                        <Save class="h-4 w-4" />
                        {{
                            form.processing
                                ? 'Saving...'
                                : editingId
                                  ? 'Update Case'
                                  : 'Save Case'
                        }}
                    </button>
                </div>
            </form>

            <section class="rounded-md bg-white p-5 shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[980px] text-left text-sm">
                        <thead
                            class="bg-slate-50 text-xs text-slate-500 uppercase"
                        >
                            <tr>
                                <th class="px-3 py-3">Patient</th>
                                <th class="px-3 py-3">Case</th>
                                <th class="px-3 py-3">Symptoms</th>
                                <th class="px-3 py-3">Action</th>
                                <th class="px-3 py-3">Status</th>
                                <th class="px-3 py-3">Occurred</th>
                                <th class="px-3 py-3">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr
                                v-for="clinicCase in cases"
                                :key="clinicCase.id"
                            >
                                <td class="px-3 py-3">
                                    <div class="font-bold text-slate-900">
                                        {{ clinicCase.patient_name || 'N/A' }}
                                    </div>
                                    <div class="text-xs text-slate-400">
                                        {{ clinicCase.patient_type || 'N/A' }}
                                    </div>
                                </td>
                                <td class="px-3 py-3 text-slate-700">
                                    {{
                                        clinicCase.case_type ||
                                        clinicCase.alert ||
                                        'N/A'
                                    }}
                                </td>
                                <td class="px-3 py-3 text-slate-500">
                                    {{ clinicCase.symptoms || '' }}
                                </td>
                                <td class="px-3 py-3 text-slate-500">
                                    {{ clinicCase.action_taken || '' }}
                                </td>
                                <td class="px-3 py-3">
                                    <span
                                        class="rounded-full px-2.5 py-1 text-[10px] font-black uppercase"
                                        :class="statusClass(clinicCase.status)"
                                    >
                                        {{ clinicCase.status }}
                                    </span>
                                </td>
                                <td class="px-3 py-3 text-slate-500">
                                    {{ clinicCase.occurred_at || 'N/A' }}
                                </td>
                                <td class="px-3 py-3">
                                    <div class="flex gap-2">
                                        <button
                                            class="rounded-md border border-slate-200 p-2 text-slate-600"
                                            @click="editCase(clinicCase)"
                                        >
                                            <ClipboardPlus class="h-4 w-4" />
                                        </button>
                                        <button
                                            class="rounded-md border border-brand/30 p-2 text-brand"
                                            @click="createHistory(clinicCase)"
                                        >
                                            <FileClock class="h-4 w-4" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="cases.length === 0">
                                <td
                                    colspan="7"
                                    class="px-3 py-10 text-center text-slate-400"
                                >
                                    No case logs yet.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </section>
    </div>
</template>
