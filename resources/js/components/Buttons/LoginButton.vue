<template>
    <button
        v-if="text == 'Login'"
        type="submit"
        :disabled="disabled"
        class="h-[50px] w-full cursor-pointer rounded-[10px] bg-brand text-center leading-[50px] text-white"
    >
        {{ text }}
    </button>

    <button
        v-else
        type="submit"
        :disabled="disabled"
        class="h-[50px] w-full cursor-pointer rounded-[10px] bg-brand text-center leading-[50px] text-white"
    >
        <span class="flex items-center justify-center gap-2">
            <UserRound class="shrink-0" /> {{ text }}
        </span>
    </button>
</template>
<script setup>
import UserRound from '../Icon/UserRound.vue';

const props = defineProps({
    text: {
        type: String,
        requied: true,
    },
    disabled: {
        type: Boolean,
        default: false,
    },
});
</script>
