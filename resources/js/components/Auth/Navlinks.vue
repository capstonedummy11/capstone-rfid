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
        required: true,
    },
});
</script>
<template>
    <Link :href="route" class="auth-nav-link group">
        <component :is="icon" class="text-[#A3AED0] group-hover:text-brand" />
        <h1>{{ text }}</h1>
    </Link>
</template>
