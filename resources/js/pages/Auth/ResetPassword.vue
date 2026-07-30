<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import logo from '@/assets/images/logo-only.jpg';

const props = defineProps({
    email: { type: String, default: '' },
    token: { type: String, required: true },
});

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});

const submit = () => form.post(route('password.update'));
</script>

<template>
    <Head title="Reset Password" />
    <main class="flex min-h-screen items-center justify-center bg-slate-100 p-4">
        <section class="w-full max-w-md rounded-xl bg-white p-6 shadow-lg">
            <img :src="logo" alt="Pasay City South High School seal" class="mx-auto mb-4 h-20 w-20 rounded-full object-cover shadow" />
            <h1 class="text-2xl font-bold text-slate-900">Create a new password</h1>
            <p class="mt-2 text-sm text-slate-600">Choose a secure password for {{ form.email }}.</p>
            <form class="mt-5 space-y-4" @submit.prevent="submit">
                <label class="block text-sm font-semibold text-slate-700">
                    Email
                    <input v-model="form.email" type="email" required autocomplete="email" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2" />
                </label>
                <label class="block text-sm font-semibold text-slate-700">
                    New password
                    <input v-model="form.password" type="password" required autocomplete="new-password" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2" />
                </label>
                <label class="block text-sm font-semibold text-slate-700">
                    Confirm new password
                    <input v-model="form.password_confirmation" type="password" required autocomplete="new-password" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2" />
                </label>
                <div v-if="Object.keys(form.errors).length" class="rounded-md bg-red-50 px-3 py-2 text-sm text-red-600">
                    <p v-for="error in form.errors" :key="error">{{ error }}</p>
                </div>
                <button type="submit" :disabled="form.processing" class="w-full rounded-md bg-blue-600 px-4 py-2 font-bold text-white disabled:opacity-60">
                    {{ form.processing ? 'Resetting...' : 'Reset password' }}
                </button>
            </form>
        </section>
    </main>
</template>
