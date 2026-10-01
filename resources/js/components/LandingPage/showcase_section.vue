<template>
    <div class="relative overflow-hidden">
        <div>
            <!-- BG Image -->
            <div>
                <img
                    :src="ShowcaseImage"
                    alt="Showcase Image"
                    class="h-full w-full object-cover"
                />
            </div>

            <div class="bg-white/50 md:absolute md:inset-0 md:w-[50%]">
                <div
                    class="flex h-full w-full flex-col items-center justify-center bg-white/50 p-5"
                >
                    <div>
                        <ShowcaseItem
                            v-for="(items, index) in item"
                            :key="index"
                            :icon="items.icon"
                            :header="items.header"
                            :text="items.text"
                            data-aos="fade-down"
                            data-aos-duration="1000"
                            :data-aos-delay="index * 300"
                            class="py-5"
                        />
                    </div>

                    <div class="pt-5">
                        <Button label="LEARN MORE" is-blue="true" />
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
<script setup>
import ShowcaseImage from '@/assets/images/Container 4.png';
import ShowcaseItem from './ShowcaseItem.vue';
import Eye from '../Icon/Eye.vue';
import Cube from '../Icon/Cube.vue';
import Monitoring from '../Icon/Monitoring.vue';
import Bell from '../Icon/Bell.vue';
import Button from '../Buttons/Button.vue';

const item = [
    {
        icon: Eye,
        header: 'Attendance Monitoring',
        text: 'Automated logging of personnel and student entry/exit using RFID tags. Provides real-time timestamps and location tracking for accurate record-keeping.',
    },
    {
        icon: Cube,
        header: 'borrowing system',
        text: 'Tracks the lending and return of equipment using RFID tags. Links item IDs and borrower information with specific due dates to prevent loss.',
    },
    {
        icon: Monitoring,
        header: 'inventory management',
        text: 'Tracks equipment availability and maintenance schedules. Provides an updated assets database to ensure all items are accounted for and in good condition.',
    },
    {
        icon: Bell,
        header: 'notification module',
        text: 'Monitors stock levels, item condition, and maintenance schedules. Sends automated alerts via SMS for overdue items or low stock levels.',
    },
];
</script>
