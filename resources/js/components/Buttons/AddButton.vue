<script setup>
defineProps({
    name: {
        type: String,
        default: 'Add Item +',
    },
});

defineEmits(['click']);
</script>
<template>
    <div class="relative mb-5 shrink-0">
        <button
            class="h-[40px] w-full cursor-pointer rounded-[5px] bg-blue-500 px-5 text-white"
            @click="$emit('click')"
        >
            {{ name }}
        </button>
    </div>
</template>
