<template>
    <div class="mx-auto max-w-7xl px-4 py-6">
        <section class="mb-4 rounded-md border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-bold text-slate-900">Emergency Hotlines</h1>
                    <p class="text-sm text-slate-500">Manage contact numbers shown on the attendance panel.</p>
                </div>
                <button type="button" class="rounded-md bg-red-600 px-4 py-2 text-sm font-bold text-white" @click="openCreate">
                    Add Hotline
                </button>
            </div>
            <p v-if="flashSuccess" class="mt-3 rounded-md bg-emerald-50 px-3 py-2 text-sm font-semibold text-emerald-700">
                {{ flashSuccess }}
            </p>
        </section>

        <section class="rounded-md border border-slate-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[900px] text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-3">Name</th>
                            <th class="px-4 py-3">Category</th>
                            <th class="px-4 py-3">Phone</th>
                            <th class="px-4 py-3">Contact</th>
                            <th class="px-4 py-3">SMS</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="hotline in hotlines" :key="hotline.emergency_hotline_id" class="border-t border-slate-100">
                            <td class="px-4 py-3 font-semibold text-slate-800">{{ hotline.name }}</td>
                            <td class="px-4 py-3 capitalize text-slate-600">{{ hotline.category }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ hotline.phone_number }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ hotline.contact_person || '-' }}</td>
                            <td class="px-4 py-3">
                                <span :class="hotline.sms_enabled ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600'" class="rounded-md px-2 py-1 text-xs font-bold">
                                    {{ hotline.sms_enabled ? 'Enabled' : 'Manual' }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <span :class="hotline.is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700'" class="rounded-md px-2 py-1 text-xs font-bold">
                                    {{ hotline.is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <button type="button" class="rounded-md border border-slate-300 px-3 py-2 text-xs font-bold text-slate-700" @click="openEdit(hotline)">
                                    Edit
                                </button>
                                <button type="button" class="ml-2 rounded-md bg-rose-600 px-3 py-2 text-xs font-bold text-white" @click="deleteHotline(hotline)">
                                    Delete
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div v-if="hotlines.length === 0" class="p-8 text-center text-sm text-slate-400">
                No emergency hotlines configured.
            </div>
        </section>

        <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <form class="w-full max-w-2xl rounded-md bg-white p-5 shadow-xl" @submit.prevent="submit">
                <h2 class="text-lg font-bold text-slate-900">{{ selectedHotline ? 'Edit Hotline' : 'Add Hotline' }}</h2>
                <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
                    <label class="block">
                        <span class="mb-1 block text-sm font-semibold text-slate-700">Name</span>
                        <input v-model="form.name" type="text" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm" required />
                        <span v-if="form.errors.name" class="mt-1 block text-xs text-red-600">{{ form.errors.name }}</span>
                    </label>
                    <label class="block">
                        <span class="mb-1 block text-sm font-semibold text-slate-700">Category</span>
                        <input v-model="form.category" type="text" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm" required />
                        <span v-if="form.errors.category" class="mt-1 block text-xs text-red-600">{{ form.errors.category }}</span>
                    </label>
                    <label class="block">
                        <span class="mb-1 block text-sm font-semibold text-slate-700">Phone Number</span>
                        <input v-model="form.phone_number" type="text" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm" required />
                        <span v-if="form.errors.phone_number" class="mt-1 block text-xs text-red-600">{{ form.errors.phone_number }}</span>
                    </label>
                    <label class="block">
                        <span class="mb-1 block text-sm font-semibold text-slate-700">Contact Person</span>
                        <input v-model="form.contact_person" type="text" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm" />
                    </label>
                    <label class="block">
                        <span class="mb-1 block text-sm font-semibold text-slate-700">Sort Order</span>
                        <input v-model.number="form.sort_order" type="number" min="0" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm" />
                    </label>
                    <div class="flex items-center gap-4 pt-6">
                        <label class="flex items-center gap-2 text-sm font-semibold text-slate-700">
                            <input v-model="form.sms_enabled" type="checkbox" class="rounded border-slate-300" />
                            SMS enabled
                        </label>
                        <label class="flex items-center gap-2 text-sm font-semibold text-slate-700">
                            <input v-model="form.is_active" type="checkbox" class="rounded border-slate-300" />
                            Active
                        </label>
                    </div>
                    <label class="block md:col-span-2">
                        <span class="mb-1 block text-sm font-semibold text-slate-700">Notes</span>
                        <textarea v-model="form.notes" rows="3" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm"></textarea>
                    </label>
                </div>
                <div class="mt-5 flex justify-end gap-2 border-t pt-4">
                    <button type="button" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-bold text-slate-700" @click="closeModal">
                        Cancel
                    </button>
                    <button type="submit" class="rounded-md bg-red-600 px-4 py-2 text-sm font-bold text-white" :disabled="form.processing">
                        {{ form.processing ? 'Saving...' : 'Save Hotline' }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>

<script setup>
import { router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

defineProps({ hotlines: { type: Array, default: () => [] } });

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);
const showModal = ref(false);
const selectedHotline = ref(null);
const form = useForm({
    name: '',
    category: 'clinic',
    phone_number: '',
    contact_person: '',
    sms_enabled: false,
    is_active: true,
    sort_order: 0,
    notes: '',
});

const openCreate = () => {
    selectedHotline.value = null;
    form.reset();
    form.category = 'clinic';
    form.sms_enabled = false;
    form.is_active = true;
    form.sort_order = 0;
    showModal.value = true;
};

const openEdit = (hotline) => {
    selectedHotline.value = hotline;
    form.name = hotline.name;
    form.category = hotline.category;
    form.phone_number = hotline.phone_number;
    form.contact_person = hotline.contact_person || '';
    form.sms_enabled = Boolean(hotline.sms_enabled);
    form.is_active = Boolean(hotline.is_active);
    form.sort_order = hotline.sort_order ?? 0;
    form.notes = hotline.notes || '';
    form.clearErrors();
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
    selectedHotline.value = null;
    form.clearErrors();
};

const submit = () => {
    if (selectedHotline.value) {
        form.put(route('clinic.emergency-hotlines.update', { id: selectedHotline.value.emergency_hotline_id }), {
            preserveScroll: true,
            onSuccess: closeModal,
        });
        return;
    }

    form.post(route('clinic.emergency-hotlines.store'), {
        preserveScroll: true,
        onSuccess: closeModal,
    });
};

const deleteHotline = (hotline) => {
    if (!confirm(`Delete ${hotline.name}?`)) return;

    router.delete(route('clinic.emergency-hotlines.destroy', { id: hotline.emergency_hotline_id }), {
        preserveScroll: true,
    });
};
</script>
