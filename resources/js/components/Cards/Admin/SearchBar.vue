<script setup>
defineProps({
    icon: {
        type: Object,
        default: null,
    },
    placeholder: {
        type: String,
        required: true,
    },
    modelValue: { type: String, default: '' },
});

defineEmits(['update:modelValue']);
</script>

<template>
    <div class="relative mb-5 w-full">
        <input
            type="text"
            class="h-[40px] w-full border-2 pl-9"
            :placeholder="placeholder"
            @input="$emit('update:modelValue', $event.target.value)"
        />
        <component
            :is="icon"
            class="absolute top-1/2 left-2 -translate-y-1/2 text-gray-400"
        />
    </div>
</template>