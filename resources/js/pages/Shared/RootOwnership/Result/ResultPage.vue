<script setup>
defineOptions({ layout: null });
defineProps({
    title: { type: String, required: true },
    message: { type: String, required: true },
});
</script>
<template>
    <main
        class="flex min-h-screen items-center justify-center bg-slate-100 p-4"
    >
        <section
            class="w-full max-w-lg rounded-lg bg-white p-8 text-center shadow"
        >
            <h1 class="text-2xl font-black">{{ title }}</h1>
            <p class="mt-3 text-slate-600">{{ message }}</p>
            <a
                href="/"
                class="mt-6 inline-block rounded bg-indigo-700 px-5 py-3 font-bold text-white"
                >Return home</a
            >
        </section>
    </main>
</template>
