<script setup>
defineProps({
    icon: {
        type: Object,
        required: true,
    },
    small_icon: {
        type: Object,
        required: true,
    },
    count: {
        type: String,
        required: true,
    },
    text: {
        type: String,
        required: true,
    },
    subText: {
        type: String,
        required: true,
    },
});
</script>
<template>
    <div class="w-full">
        <div class="rounded-[5px] bg-white p-5">
            <div class="flex items-start justify-between">
                <div>
                    <h1 class="text-[40px] font-bold">{{ count }}</h1>
                    <h1 class="text-[25px] font-medium">{{ text }}</h1>
                    <div class="flex items-center gap-4">
                        <component :is="small_icon" />
                        <p class="text-custom-gray">{{ subText }}</p>
                    </div>
                </div>
                <component :is="icon" />
            </div>
        </div>
    </div>
</template>
