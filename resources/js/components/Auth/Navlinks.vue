<script setup>
defineProps({
    icon: {
        type: Object,
        required: true,
    },
    text: {
        type: String,
        required: true,
    },
    route: {
        type: String,
        required: false,
    },
});
</script>
<template>
    <div
        v-if="!route"
        class="auth-nav-link group cursor-not-allowed opacity-50"
    >
        <component :is="icon" class="text-[#A3AED0]" />
        <h1>{{ text }}</h1>
    </div>
    <Link v-else :href="route" class="auth-nav-link group">
        <component :is="icon" class="text-[#A3AED0] group-hover:text-brand" />
        <h1>{{ text }}</h1>
    </Link>
</template>
