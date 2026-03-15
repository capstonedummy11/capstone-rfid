<script setup>
import AuthNavbar from './AuthNavbar.vue';
</script>

<template>
    <div class="flex min-h-screen">
        <AuthNavbar v-if="$page.props.auth.user" />
        <main class="flex-1 bg-[#FAFAFA]">
            <slot />
        </main>
    </div>
</template>
