<script setup>
const model = defineModel({
    type: null,
    required: true,
});

defineProps({
    message: String,
    label: {
        type: String,
        required: true,
    },
    type: {
        type: String,
        default: 'text',
    },
});
</script>
<template>
    <div class="my-5 flex flex-col gap-5">
        <label for="name">{{ label }}:</label>
        <input
            v-model="model"
            type="text"
            id="name"
            class="h-[50px] w-full rounded-[10px] border-2 p-2"
        />
        <span v-if="message" class="text-sm text-red-500">{{ message }}</span>
    </div>
</template>
