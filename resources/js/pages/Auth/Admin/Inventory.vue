<script setup>
import DashboardCard from '@/components/Cards/Admin/Dashboard/DashboardCard.vue';
import InventoryTable from '@/components/Cards/Admin/InventoryTable.vue';
import Damaged from '@/components/Icon/Damaged.vue';
import Maintenance from '@/components/Icon/Maintenance.vue';
import Overdue from '@/components/Icon/Overdue.vue';
import Reserved from '@/components/Icon/Reserved.vue';
import Returned from '@/components/Icon/Returned.vue';
import Stacks from '@/components/Icon/Stacks.vue';

defineProps({
    items: { type: Array, default: () => [] },
});
const card = [
    {
        count: '422',
        icon: Stacks,
        text: 'Available',
    },
    {
        count: '422',
        icon: Overdue,
        text: 'Overdue',
    },
    {
        count: '422',
        icon: Reserved,
        text: 'Reserved',
    },
    {
        count: '422',
        icon: Reserved,
        text: 'Reserved',
    },
    {
        count: '422',
        icon: Returned,
        text: 'Returned',
    },
    {
        count: '422',
        icon: Damaged,
        text: 'Damaged',
    },
    {
        count: '422',
        icon: Maintenance,
        text: 'Maintenance',
    },
];

const table = [
    {
        table_header: 'Inventory',
        rows: [
            {
                item: 'Samsung Monitor 24 inch IPS',
                id: '2341421',
                barcode: '8901-2345-67890',
                description:
                    'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et',
                number: '5',
                date: '29 July 2026',
                status: 'Available',
            },
            {
                item: 'Asus Keyboard wireless',
                id: '3411421',
                barcode: '8901-2345-67890',
                description:
                    'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et',
                number: '5',
                date: '29 July 2026',
                status: 'Available',
            },
            {
                item: 'Mouse a4tech',
                id: '3411421',
                barcode: '8901-2345-67890',
                description:
                    'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et',
                number: '5',
                date: '29 July 2026',
                status: 'Available',
            },
            {
                item: 'Projector',
                id: '3411421',
                barcode: '8901-2345-67890',
                description:
                    'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et',
                number: '5',
                date: '29 July 2026',
                status: 'Available',
            },
            {
                item: 'RTX 5070',
                id: '3411421',
                barcode: '8901-2345-67890',
                description:
                    'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et',
                number: '5',
                date: '29 July 2026',
                status: 'Available',
            },
        ],
    },
];
</script>

<template>
    <!-- WALA PANG RESTOCK FUNCTIONS -->
    <div class="p-4">
        <div class="mb-4 flex gap-4">
            <DashboardCard
                v-for="(item, index) in card.slice(0, 4)"
                :key="index"
                :count="item.count"
                :text="item.text"
                :sub-text="item.subText"
                :icon="item.icon"
                :small_icon="item.small_icon"
            />
        </div>
        <div class="flex gap-4">
            <DashboardCard
                v-for="(item, index) in card.slice(4)"
                :key="index + 4"
                :count="item.count"
                :text="item.text"
                :sub-text="item.subText"
                :icon="item.icon"
                :small_icon="item.small_icon"
            />
        </div>
    </div>

    <div class="p-4">
        <InventoryTable
            v-for="(item, index) in table"
            :key="index"
            :table_header="item.table_header"
            :rows="items"
        />
    </div>
</template>