<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

const page = usePage();

const props = defineProps({
    icon: { type: Object, required: true },
    text: { type: String, required: true },
    route: { type: String, required: false },
    badge: { type: Number, default: 0 },
});

const isActive = computed(() => {
    if (!props.route) return false;

    // Extract just the pathname from the full URL
    const routePath = new URL(props.route).pathname;

    return page.url.startsWith(routePath);
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
    <Link
        v-else
        :href="route"
        class="auth-nav-link group"
        :class="{ 'bg-black/20 text-brand': isActive }"
    >
        <component
            :is="icon"
            class="group-hover:text-brand"
            :class="isActive ? 'text-brand' : 'text-[#A3AED0]'"
        />
        <h1 class="min-w-0 flex-1">{{ text }}</h1>
        <span
            v-if="badge > 0"
            class="ml-auto inline-flex min-w-5 items-center justify-center rounded-full bg-red-500 px-1.5 py-0.5 text-[10px] font-bold text-white"
        >
            {{ badge > 99 ? '99+' : badge }}
        </span>
    </Link>
</template>
