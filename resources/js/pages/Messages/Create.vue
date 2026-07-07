<script setup>
import Layout from '@/layouts/Layout.vue';
import { useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import Swal from 'sweetalert2';

defineOptions({ layout: Layout });

const props = defineProps({
    instructors: { type: Array, default: () => [] },
});

const page = usePage();
const successMessage = ref('');
const fileInputRef = ref(null);
const flashSuccess = computed(
    () => successMessage.value || page.props.flash?.success,
);

const form = useForm({
    instructor_user_id: props.instructors[0]?.value ?? '',
    sender_type: 'student',
    sender_name: '',
    sender_email: '',
    student_number: '',
    body: '',
    attachment: null,
});

const setAttachment = (event) => {
    form.attachment = event.target.files?.[0] ?? null;
};

const submit = () => {
    successMessage.value = '';

    form.post(route('messages.store'), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            successMessage.value = 'Message sent to the instructor.';
            form.reset(
                'sender_name',
                'sender_email',
                'student_number',
                'body',
                'attachment',
            );
            if (fileInputRef.value) {
                fileInputRef.value.value = '';
            }
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'success',
                title: successMessage.value,
                showConfirmButton: false,
                timer: 1800,
            });
        },
    });
};
</script>

<template>
    <div class="bg-slate-50 px-4 py-8">
        <section
            class="mx-auto max-w-3xl rounded-md border border-slate-200 bg-white p-5 shadow-sm"
        >
            <div class="border-b border-slate-100 pb-4">
                <h1 class="text-2xl font-bold text-slate-900">
                    Message Instructor
                </h1>
                <p class="mt-1 text-sm text-slate-500">
                    Send attendance concerns, absence notes, or questions
                    directly to an instructor.
                </p>
                <p
                    v-if="flashSuccess"
                    class="mt-3 rounded-md bg-emerald-50 px-3 py-2 text-sm font-semibold text-emerald-700"
                >
                    {{ flashSuccess }}
                </p>
            </div>

            <form
                class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-2"
                @submit.prevent="submit"
            >
                <label class="md:col-span-2">
                    <span
                        class="mb-1 block text-xs font-semibold text-slate-500 uppercase"
                        >Instructor</span
                    >
                    <select
                        v-model="form.instructor_user_id"
                        class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:ring-2 focus:ring-brand focus:outline-none"
                        required
                    >
                        <option value="" disabled>Select instructor</option>
                        <option
                            v-for="instructor in instructors"
                            :key="instructor.value"
                            :value="instructor.value"
                        >
                            {{ instructor.label }}
                        </option>
                    </select>
                    <p
                        v-if="form.errors.instructor_user_id"
                        class="mt-1 text-xs text-red-600"
                    >
                        {{ form.errors.instructor_user_id }}
                    </p>
                </label>

                <label>
                    <span
                        class="mb-1 block text-xs font-semibold text-slate-500 uppercase"
                        >Sender</span
                    >
                    <select
                        v-model="form.sender_type"
                        class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:ring-2 focus:ring-brand focus:outline-none"
                    >
                        <option value="student">Student</option>
                        <option value="parent">Parent</option>
                    </select>
                </label>

                <label>
                    <span
                        class="mb-1 block text-xs font-semibold text-slate-500 uppercase"
                        >Name</span
                    >
                    <input
                        v-model="form.sender_name"
                        type="text"
                        class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:ring-2 focus:ring-brand focus:outline-none"
                        required
                    />
                    <p
                        v-if="form.errors.sender_name"
                        class="mt-1 text-xs text-red-600"
                    >
                        {{ form.errors.sender_name }}
                    </p>
                </label>

                <label>
                    <span
                        class="mb-1 block text-xs font-semibold text-slate-500 uppercase"
                        >Email</span
                    >
                    <input
                        v-model="form.sender_email"
                        type="email"
                        class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:ring-2 focus:ring-brand focus:outline-none"
                    />
                    <p
                        v-if="form.errors.sender_email"
                        class="mt-1 text-xs text-red-600"
                    >
                        {{ form.errors.sender_email }}
                    </p>
                </label>

                <label>
                    <span
                        class="mb-1 block text-xs font-semibold text-slate-500 uppercase"
                        >Student Number</span
                    >
                    <input
                        v-model="form.student_number"
                        type="text"
                        class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:ring-2 focus:ring-brand focus:outline-none"
                    />
                </label>

                <label class="md:col-span-2">
                    <span
                        class="mb-1 block text-xs font-semibold text-slate-500 uppercase"
                        >Message</span
                    >
                    <textarea
                        v-model="form.body"
                        rows="6"
                        class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:ring-2 focus:ring-brand focus:outline-none"
                        required
                    ></textarea>
                    <p
                        v-if="form.errors.body"
                        class="mt-1 text-xs text-red-600"
                    >
                        {{ form.errors.body }}
                    </p>
                </label>

                <label class="md:col-span-2">
                    <span
                        class="mb-1 block text-xs font-semibold text-slate-500 uppercase"
                        >Attachment</span
                    >
                    <input
                        ref="fileInputRef"
                        type="file"
                        accept=".pdf,image/png,image/jpeg,image/webp"
                        class="w-full rounded-md border border-dashed border-slate-300 px-3 py-2 text-sm text-slate-600"
                        @change="setAttachment"
                    />
                    <p class="mt-1 text-xs text-slate-400">
                        PDF, JPG, PNG, or WEBP up to 5 MB.
                    </p>
                    <p
                        v-if="form.errors.attachment"
                        class="mt-1 text-xs text-red-600"
                    >
                        {{ form.errors.attachment }}
                    </p>
                </label>

                <div class="flex justify-end md:col-span-2">
                    <button
                        type="submit"
                        :disabled="form.processing || instructors.length === 0"
                        class="rounded-md bg-brand px-4 py-2 text-sm font-bold text-white disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        {{ form.processing ? 'Sending...' : 'Send Message' }}
                    </button>
                </div>
            </form>
        </section>
    </div>
</template>
