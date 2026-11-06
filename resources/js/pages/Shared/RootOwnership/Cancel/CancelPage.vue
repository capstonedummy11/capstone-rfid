<script setup>
import { useForm } from '@inertiajs/vue3';
defineOptions({ layout: null });
defineProps({
    transfer: { type: Object, required: true },
    submitUrl: { type: String, required: true },
});
const form = useForm({});
</script>
<template>
    <main
        class="flex min-h-screen items-center justify-center bg-slate-100 p-4"
    >
        <section class="w-full max-w-lg rounded-lg bg-white p-8 shadow">
            <h1 class="text-2xl font-black">Cancel Root Admin transfer</h1>
            <p class="mt-3 text-slate-600">
                This one-time protected link cancels the pending transfer even
                if an unauthorized browser session is active.
            </p>
            <form class="mt-6" @submit.prevent="form.post(submitUrl)">
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="rounded bg-rose-700 px-5 py-3 font-bold text-white"
                >
                    Cancel transfer now
                </button>
            </form>
        </section>
    </main>
</template>
