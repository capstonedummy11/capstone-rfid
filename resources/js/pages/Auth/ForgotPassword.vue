<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import logo from '@/assets/images/logo-only.jpg';

defineProps({
    status: { type: String, default: '' },
    backUrl: { type: String, default: '/' },
    backLabel: {
        type: String,
        default: 'Back to Student / Parent login',
    },
});

const form = useForm({ email: '' });
const submit = () => form.post(route('password.email'));
</script>

<template>
    <Head title="Forgot Password" />
    <main class="flex min-h-screen items-center justify-center bg-slate-100 p-4">
        <section class="w-full max-w-md rounded-xl bg-white p-6 shadow-lg">
            <img :src="logo" alt="Pasay City South High School seal" class="mx-auto mb-4 h-20 w-20 rounded-full object-cover shadow" />
            <h1 class="text-2xl font-bold text-slate-900">Forgot password</h1>
            <p class="mt-2 text-sm text-slate-600">
                Enter the email registered to your account. We will send a secure password-reset link. Console accounts are managed separately and cannot use this recovery form.
            </p>
            <p v-if="status" class="mt-4 rounded-md bg-emerald-50 px-3 py-2 text-sm font-semibold text-emerald-700">
                {{ status }}
            </p>
            <form class="mt-5 space-y-4" @submit.prevent="submit">
                <label class="block text-sm font-semibold text-slate-700">
                    Email address
                    <input v-model="form.email" type="email" autocomplete="email" required autofocus class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2" />
                </label>
                <p v-if="form.errors.email" class="text-sm text-red-600">{{ form.errors.email }}</p>
                <button type="submit" :disabled="form.processing" class="w-full rounded-md bg-blue-600 px-4 py-2 font-bold text-white disabled:opacity-60">
                    {{ form.processing ? 'Sending...' : 'Send reset link' }}
                </button>
            </form>
            <div class="mt-5">
                <Link
                    :href="backUrl"
                    class="block w-full rounded-md border border-slate-300 px-4 py-2 text-center text-sm font-bold text-slate-700 transition hover:border-blue-400 hover:bg-blue-50 hover:text-blue-700"
                >
                    ← {{ backLabel }}
                </Link>
            </div>
        </section>
    </main>
</template>
