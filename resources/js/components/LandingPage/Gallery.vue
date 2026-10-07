<template>
    <div class="w-full overflow-hidden">
        <div class="flex h-[300px] items-center justify-center bg-white/50">
            <h1
                class="border-b-2 border-b-[custom-gray] p-5 text-center text-[30px] text-[#0059ab] uppercase"
            >
                Our galleries
            </h1>
        </div>

        <div class="flex overflow-auto md:overflow-hidden">
            <img
                v-for="(item, index) in gallery"
                :key="index"
                :src="item.image"
                :alt="`Item ${index + 1}`"
                class="h-full w-full object-cover"
                data-aos="fade-down"
                data-aos-duration="1000"
                :data-aos-delay="index * 300"
            />
        </div>
    </div>
</template>
<script setup>
import item3 from '@/assets/images/Item 3.png';
import item5 from '@/assets/images/Item 5.png';
import container1 from '@/assets/images/Container.png';
import container2 from '@/assets/images/Container 2.png';
import container3 from '@/assets/images/Container 3.png';
import container4 from '@/assets/images/Container 4.png';
const gallery = [
    {
        image: container1,
    },
    {
        image: container2,
    },
    {
        image: container3,
    },
    {
        image: container4,
    },
    {
        image: item3,
    },
    {
        image: item5,
    },
];
</script>
