<script setup>
import AuthNavbar from './AuthNavbar.vue';
</script>

<template>
    <div class="flex min-h-screen">
        <AuthNavbar v-if="$page.props.auth.user" />
        <div class="flex flex-1 flex-col">
            <!-- Top Header -->
            <header
                class="flex h-[100px] items-center bg-white p-4 drop-shadow-sm"
            >
                <h1 class="text-[20px] font-bold">{{ $page.props.title }}</h1>
            </header>
            <!-- Page Content -->
            <main class="flex-1 bg-gray-100">
                <slot />
            </main>
        </div>
    </div>
</template>
