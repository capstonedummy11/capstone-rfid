<template>
    <div class="flex min-h-screen items-center justify-center bg-slate-100 p-6">
        <div
            class="w-full overflow-hidden rounded-lg bg-white shadow-sm"
            :class="loginType === 'staff' ? 'min-h-[420px] max-w-md' : 'max-w-5xl'"
        >
            <Login :login-type="loginType" />
        </div>
    </div>
</template>

<script setup>
import Login from '@/components/Auth/Login.vue';

defineProps({
    loginType: { type: String, default: 'student_parent' },
});
</script>
