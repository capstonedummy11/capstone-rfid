<template>
    <div @click="toggleMenu" class="cursor-pointer">
        <!-- Hamburger icon -->
        <svg
            v-if="!isOpen"
            xmlns="http://www.w3.org/2000/svg"
            width="24"
            height="24"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
            stroke-linecap="round"
            stroke-linejoin="round"
        >
            <path d="M4 5h16" />
            <path d="M4 12h16" />
            <path d="M4 19h16" />
        </svg>

        <!-- Close icon -->
        <svg
            v-else
            xmlns="http://www.w3.org/2000/svg"
            width="24"
            height="24"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
            stroke-linecap="round"
            stroke-linejoin="round"
        >
            <path d="M18 6 6 18" />
            <path d="m6 6 12 12" />
        </svg>
    </div>
</template>

<script setup>
import { ref, watch } from 'vue';

const props = defineProps({
    modelValue: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['toggle', 'update:modelValue']);
const isOpen = ref(props.modelValue);

watch(
    () => props.modelValue,
    (val) => {
        isOpen.value = val;
    },
);

// @function toggleMenu: Tina-toggle ang menu sa Menu flow.
// @useIn toggleMenu: resources/js/components/Buttons/Menu.vue template @click
function toggleMenu() {
    isOpen.value = !isOpen.value;
    emit('toggle', isOpen.value);
    emit('update:modelValue', isOpen.value);
}
</script>
