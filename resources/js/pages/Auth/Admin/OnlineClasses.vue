<script setup>
import { useForm, usePage, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import Swal from 'sweetalert2';

const props = defineProps({
    onlineClasses: { type: Array, default: () => [] },
    scheduleOptions: { type: Array, default: () => [] },
    defaultRequireFaceRecognition: { type: Boolean, default: true },
});

const page = usePage();
const editing = ref(null);
const form = useForm({
    schedule_id: '',
    title: '',
    description: '',
    meeting_link: '',
    scheduled_date: '',
    start_time: '',
    end_time: '',
    require_face_recognition: props.defaultRequireFaceRecognition,
    attachments: [],
});

const flashSuccess = computed(() => page.props.flash?.success);

const resetForm = () => {
    editing.value = null;
    form.reset();
    form.require_face_recognition = props.defaultRequireFaceRecognition;
    form.attachments = [];
};

const editClass = (onlineClass) => {
    editing.value = onlineClass;
    form.schedule_id = onlineClass.schedule_id;
    form.title = onlineClass.title;
    form.description = onlineClass.description || '';
    form.meeting_link = onlineClass.meeting_link;
    form.scheduled_date = onlineClass.scheduled_date;
    form.start_time = onlineClass.start_time;
    form.end_time = onlineClass.end_time;
    form.require_face_recognition = onlineClass.require_face_recognition;
    form.attachments = [];
};

const saveClass = () => {
    const options = {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Online class saved', showConfirmButton: false, timer: 1600 });
            resetForm();
        },
    };

    if (editing.value) {
        form.post(route('admin.online-classes.update', editing.value.online_class_id), {
            ...options,
            _method: 'put',
        });
        return;
    }

    form.post(route('admin.online-classes.store'), options);
};

const cancelClass = (onlineClass) => {
    router.put(route('admin.online-classes.cancel', onlineClass.online_class_id), {}, { preserveScroll: true });
};

const deleteClass = (onlineClass) => {
    router.delete(route('admin.online-classes.destroy', onlineClass.online_class_id), { preserveScroll: true });
};
</script>

<template>
    <div class="p-4 sm:p-6">
        <div class="mx-auto grid max-w-7xl gap-5 lg:grid-cols-[380px_1fr]">
            <section class="rounded-md border border-slate-200 bg-white p-5 shadow-sm">
                <h1 class="text-xl font-bold text-slate-900">
                    {{ editing ? 'Edit Online Class' : 'Create Online Class' }}
                </h1>
                <p v-if="flashSuccess" class="mt-3 rounded-md bg-emerald-50 px-3 py-2 text-sm font-semibold text-emerald-700">
                    {{ flashSuccess }}
                </p>

                <form class="mt-4 flex flex-col gap-3" @submit.prevent="saveClass">
                    <label class="text-sm font-semibold text-slate-700">
                        Class / Subject
                        <select v-model="form.schedule_id" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm" required>
                            <option value="">Select assigned class</option>
                            <option v-for="schedule in scheduleOptions" :key="schedule.scheduled_id" :value="schedule.scheduled_id">
                                {{ schedule.label }}
                            </option>
                        </select>
                    </label>
                    <label class="text-sm font-semibold text-slate-700">
                        Title
                        <input v-model="form.title" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm" required />
                    </label>
                    <label class="text-sm font-semibold text-slate-700">
                        Description / Instructions
                        <textarea v-model="form.description" class="mt-1 h-24 w-full rounded-md border border-slate-300 px-3 py-2 text-sm" />
                    </label>
                    <label class="text-sm font-semibold text-slate-700">
                        Meeting Link
                        <input v-model="form.meeting_link" type="url" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm" required />
                    </label>
                    <div class="grid grid-cols-3 gap-2">
                        <label class="text-sm font-semibold text-slate-700">
                            Date
                            <input v-model="form.scheduled_date" type="date" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm" required />
                        </label>
                        <label class="text-sm font-semibold text-slate-700">
                            Start
                            <input v-model="form.start_time" type="time" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm" required />
                        </label>
                        <label class="text-sm font-semibold text-slate-700">
                            End
                            <input v-model="form.end_time" type="time" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm" required />
                        </label>
                    </div>
                    <label class="flex items-center justify-between rounded-md border border-slate-200 p-3 text-sm font-semibold text-slate-700">
                        Require Facial Recognition
                        <input v-model="form.require_face_recognition" type="checkbox" class="h-5 w-5 accent-brand" />
                    </label>
                    <label class="text-sm font-semibold text-slate-700">
                        Attachments
                        <input type="file" multiple class="mt-1 w-full text-sm" @change="form.attachments = Array.from($event.target.files || [])" />
                    </label>
                    <div class="flex gap-2">
                        <button type="submit" class="rounded-md bg-brand px-4 py-2 text-sm font-bold text-white" :disabled="form.processing">
                            {{ form.processing ? 'Saving...' : 'Save' }}
                        </button>
                        <button type="button" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-bold text-slate-700" @click="resetForm">
                            Clear
                        </button>
                    </div>
                </form>
            </section>

            <section class="rounded-md border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-xl font-bold text-slate-900">Online Classes</h2>
                <div class="mt-4 overflow-x-auto">
                    <table class="w-full min-w-[780px] text-left text-sm">
                        <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                            <tr>
                                <th class="px-3 py-2">Schedule</th>
                                <th class="px-3 py-2">Title</th>
                                <th class="px-3 py-2">Link</th>
                                <th class="px-3 py-2">Face</th>
                                <th class="px-3 py-2">Status</th>
                                <th class="px-3 py-2">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="onlineClass in onlineClasses" :key="onlineClass.online_class_id">
                                <td class="px-3 py-3">
                                    <div class="font-semibold text-slate-900">{{ onlineClass.scheduled_date }} {{ onlineClass.start_time }}-{{ onlineClass.end_time }}</div>
                                    <div class="text-xs text-slate-500">{{ onlineClass.section_name }} / {{ onlineClass.subject_name }}</div>
                                </td>
                                <td class="px-3 py-3">{{ onlineClass.title }}</td>
                                <td class="px-3 py-3">
                                    <a :href="onlineClass.meeting_link" target="_blank" class="font-semibold text-brand">Open</a>
                                </td>
                                <td class="px-3 py-3">{{ onlineClass.require_face_recognition ? 'Required' : 'Off' }}</td>
                                <td class="px-3 py-3">{{ onlineClass.status }}</td>
                                <td class="space-x-2 px-3 py-3">
                                    <button class="rounded border border-slate-300 px-2 py-1 text-xs font-bold" @click="editClass(onlineClass)">Edit</button>
                                    <button class="rounded border border-amber-300 px-2 py-1 text-xs font-bold text-amber-700" @click="cancelClass(onlineClass)">Cancel</button>
                                    <button class="rounded border border-red-300 px-2 py-1 text-xs font-bold text-red-700" @click="deleteClass(onlineClass)">Delete</button>
                                </td>
                            </tr>
                            <tr v-if="onlineClasses.length === 0">
                                <td colspan="6" class="px-3 py-8 text-center text-slate-400">No online classes yet.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>
</template>
