<script setup>
import { Head, useForm } from '@inertiajs/vue3';

const form = useForm({
    password: '',
    password_confirmation: '',
});

const submit = () => form.put(route('password.first-login.update'));
</script>

<template>
    <Head title="Change Temporary Password" />
    <main class="flex min-h-screen items-center justify-center bg-slate-100 p-4">
        <section class="w-full max-w-md rounded-xl border-t-4 border-blue-600 bg-white p-6 shadow-lg">
            <h1 class="text-2xl font-bold text-slate-900">Create your private password</h1>
            <p class="mt-2 text-sm text-slate-600">
                This is a new account using a temporary password. You must replace it before accessing the system.
            </p>
            <form class="mt-5 space-y-4" @submit.prevent="submit">
                <label class="block text-sm font-semibold text-slate-700">
                    New password
                    <input v-model="form.password" type="password" required autofocus autocomplete="new-password" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2" />
                </label>
                <label class="block text-sm font-semibold text-slate-700">
                    Confirm new password
                    <input v-model="form.password_confirmation" type="password" required autocomplete="new-password" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2" />
                </label>
                <div v-if="Object.keys(form.errors).length" class="rounded-md bg-red-50 px-3 py-2 text-sm text-red-600">
                    <p v-for="error in form.errors" :key="error">{{ error }}</p>
                </div>
                <button type="submit" :disabled="form.processing" class="w-full rounded-md bg-blue-600 px-4 py-2 font-bold text-white disabled:opacity-60">
                    {{ form.processing ? 'Saving...' : 'Save password and continue' }}
                </button>
            </form>
        </section>
    </main>
</template>
