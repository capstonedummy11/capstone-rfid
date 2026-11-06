<template>
    <div class="flex flex-col md:flex-row">
        <div class="bg-azure-gradient md:flex md:w-[60%]">
            <div class="m-5 w-full">
                <div
                    class="my-5 flex flex-col items-center justify-center text-white"
                >
                    <h1 class="md:text-[25px]">The Journal</h1>
                    <p class="md:text-[40px]">SYSTEM ACTIVITY LOGS</p>
                </div>
                <div>
                    <JournalComponent
                        v-for="(items, index) in item"
                        :image="items.image"
                        :header="items.header"
                        :date="items.date"
                        data-aos="fade-right"
                        data-aos-duration="1000"
                        :data-aos-delay="index * 300"
                        class="my-10 gap-5 md:gap-10"
                    />
                </div>
            </div>
        </div>
        <div class="relative h-full w-full">
            <img
                :src="Group1"
                alt="Group1"
                class="h-full w-full object-cover"
            />
        </div>
    </div>
</template>
<script setup>
import Group1 from '@/assets/images/pasayCitysouth.png';
import JournalComponent from './JournalComponent.vue';
import repair from '@/assets/images/repair.png';
import repair2 from '@/assets/images/repair2.png';
import repair3 from '@/assets/images/repair3.png';
const item = [
    {
        image: repair,
        header: 'New Equipment Tagged',
        date: 'September 19, 2025',
    },
    {
        image: repair2,
        header: 'Automated Report Generated',
        date: 'March 24, 2025',
    },
    {
        image: repair3,
        header: 'Low stock alert',
        date: 'March 23, 2025',
    },
];
</script>
