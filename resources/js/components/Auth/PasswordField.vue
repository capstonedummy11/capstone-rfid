<script setup>
import { ref } from 'vue';
import EyeOn from '../Icon/EyeOn.vue';
import EyeOff from '../Icon/EyeOff.vue';

const showPassword = ref(false);

const model = defineModel({
    type: String,
    required: true,
});

defineProps({
    label: {
        type: String,
        required: true,
    },
    error: {
        // ← pass the specific error message from parent
        type: String,
        default: null,
    },
});
</script>

<template>
    <div class="my-5 flex flex-col gap-5">
        <label>{{ label }}:</label>
        <div class="relative">
            <input
                v-model="model"
                :type="showPassword ? 'text' : 'password'"
                class="h-[50px] w-full rounded-[10px] border-2 p-2 pr-10"
            />
            <button
                type="button"
                @click="showPassword = !showPassword"
                class="absolute top-1/2 right-3 -translate-y-1/2"
            >
                <EyeOn v-if="showPassword" />
                <EyeOff v-else />
            </button>
        </div>
        <span v-if="error" class="text-sm text-red-500">{{ error }}</span>
    </div>
</template>
