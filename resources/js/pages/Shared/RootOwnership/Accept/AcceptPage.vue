<script setup>
import { useForm } from '@inertiajs/vue3';
defineOptions({ layout: null });
const props = defineProps({
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
            <h1 class="text-2xl font-black">Accept Root Admin ownership</h1>
            <p class="mt-3 text-slate-600">
                {{ transfer.from_user?.email || transfer.fromUser?.email }}
                selected you as the next Root Admin. Acceptance does not bypass
                the scheduled safety delay.
            </p>
            <form class="mt-6" @submit.prevent="form.post(submitUrl)">
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="rounded bg-indigo-700 px-5 py-3 font-bold text-white"
                >
                    Accept ownership transfer
                </button>
            </form>
        </section>
    </main>
</template>
