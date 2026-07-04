<script setup>
import { useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    student: { type: Object, default: null },
    user: { type: Object, required: true },
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);

const form = useForm({
    name: props.user.name || '',
    phone: props.user.phone || props.student?.phone || '',
    gender: props.user.gender || props.student?.gender || '',
});

const passwordForm = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const saveProfile = () => {
    form.put(route('student-parent.profile.update'), { preserveScroll: true });
};

const savePassword = () => {
    passwordForm.put(route('student-parent.password.update'), {
        preserveScroll: true,
        onSuccess: () => passwordForm.reset(),
    });
};
</script>

<template>
    <div class="p-4 sm:p-6">
        <div class="mx-auto grid max-w-6xl gap-5 lg:grid-cols-[1fr_360px]">
            <section class="rounded-md border border-slate-200 bg-white p-5 shadow-sm">
                <h1 class="text-xl font-bold text-slate-900">Profile</h1>
                <p v-if="flashSuccess" class="mt-3 rounded-md bg-emerald-50 px-3 py-2 text-sm font-semibold text-emerald-700">{{ flashSuccess }}</p>

                <form class="mt-4 grid gap-4 md:grid-cols-2" @submit.prevent="saveProfile">
                    <label class="text-sm font-semibold text-slate-700">
                        Name
                        <input v-model="form.name" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm" required />
                    </label>
                    <label class="text-sm font-semibold text-slate-700">
                        Phone
                        <input v-model="form.phone" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm" />
                    </label>
                    <label class="text-sm font-semibold text-slate-700">
                        Gender
                        <select v-model="form.gender" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                            <option value="">Select gender</option>
                            <option value="male">Male</option>
                            <option value="female">Female</option>
                        </select>
                    </label>
                    <div class="md:col-span-2">
                        <button class="rounded-md bg-brand px-4 py-2 text-sm font-bold text-white" :disabled="form.processing">Save Profile</button>
                    </div>
                </form>
            </section>

            <aside class="rounded-md border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="font-bold text-slate-900">Student Record</h2>
                <dl class="mt-4 space-y-3 text-sm">
                    <div><dt class="font-semibold text-slate-500">Student No.</dt><dd>{{ student?.student_number || '-' }}</dd></div>
                    <div><dt class="font-semibold text-slate-500">Section</dt><dd>{{ student?.section || '-' }}</dd></div>
                    <div><dt class="font-semibold text-slate-500">Strand</dt><dd>{{ student?.strand || '-' }}</dd></div>
                    <div><dt class="font-semibold text-slate-500">School Year</dt><dd>{{ student?.school_year || '-' }}</dd></div>
                </dl>

                <form class="mt-6 flex flex-col gap-3" @submit.prevent="savePassword">
                    <h2 class="font-bold text-slate-900">Change Password</h2>
                    <input v-model="passwordForm.current_password" type="password" placeholder="Current password" class="rounded-md border border-slate-300 px-3 py-2 text-sm" required />
                    <input v-model="passwordForm.password" type="password" placeholder="New password" class="rounded-md border border-slate-300 px-3 py-2 text-sm" required />
                    <input v-model="passwordForm.password_confirmation" type="password" placeholder="Confirm password" class="rounded-md border border-slate-300 px-3 py-2 text-sm" required />
                    <button class="rounded-md border border-slate-300 px-4 py-2 text-sm font-bold text-slate-700" :disabled="passwordForm.processing">Update Password</button>
                </form>
            </aside>
        </div>
    </div>
</template>
