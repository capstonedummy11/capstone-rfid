<script setup>
import DashboardCard from '@/components/Cards/Admin/Dashboard/DashboardCard.vue';
import DashboardTable from '@/components/Cards/Admin/Dashboard/DashboardTable.vue';
import DashboardCardIcon from '@/components/Icon/DashboardCardIcon.vue';
import DashboardIcon2 from '@/components/Icon/DashboardIcon2.vue';
import DashboardIcon3 from '@/components/Icon/DashboardIcon3.vue';
import Plus from '@/components/Icon/Plus.vue';
import SmallIcon from '@/components/Icon/SmallIcon.vue';
import SmallIcon2 from '@/components/Icon/SmallIcon2.vue';
import TwoPerson from '@/components/Icon/TwoPerson.vue';

const card = [
    {
        count: '422',
        icon: TwoPerson,
        text: 'Laboratories',
        subText: '2 new employees added',
        small_icon: Plus,
    },
    {
        count: '360',
        icon: DashboardCardIcon,
        text: 'Instructor',
        subText: '-10% Less than yesterday',
        small_icon: SmallIcon,
    },
    {
        count: '422',
        icon: DashboardIcon2,
        text: 'Sections',
        subText: '+3% Increase than yesterday',
        small_icon: SmallIcon2,
    },
    {
        count: '422',
        icon: DashboardIcon3,
        text: 'Total Student',
        subText: '+3% Increase than yesterday',
        small_icon: SmallIcon2,
    },
];

const table = [
    {
        table_header: 'Lab Sessions',
        rows: [
            {
                time: '7:30 AM',
                room: 'BSIT 3A Lab',
                details: 'Mr. Juan Dela Cruz | BSIT - 101 | 27student',
                active: true,
            },
            {
                time: '5:30 AM',
                room: 'BSIT 3A Lab',
                details: null,
                active: false,
            },
            {
                time: '8:30 AM',
                room: 'BSIT 3A Lab',
                details: 'Mrs. Joana De Guzman | BSIT - 101 | 27student',
                active: true,
            },
            {
                time: '10:30 AM',
                room: 'BSIT 3A Lab',
                details: null,
                active: false,
            },
        ],
    },
];
</script>

<template>
    <div class="p-4">
        <div class="flex gap-4">
            <DashboardCard
                v-for="(item, index) in card"
                :key="index"
                :count="item.count"
                :text="item.text"
                :sub-text="item.subText"
                :icon="item.icon"
                :small_icon="item.small_icon"
            />
        </div>
    </div>

    <div class="p-4">
        <div class="flex gap-4">
            <DashboardTable
                v-for="(item, index) in table"
                :key="index"
                :table_header="item.table_header"
                :rows="item.rows"
            />
            <div></div>
        </div>
    </div>
</template>
