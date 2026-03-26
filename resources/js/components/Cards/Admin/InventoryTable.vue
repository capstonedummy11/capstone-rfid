<script setup>
import Barcode from '@/components/Icon/Barcode.vue';
import SearchBar from './SearchBar.vue';
import Search from '@/components/Icon/Search.vue';
import Item from '@/components/Icon/Item.vue';
import AddButton from '@/components/Buttons/AddButton.vue';

defineProps({
    table_header: {
        type: String,
        required: true,
    },
    rows: {
        type: Array,
        default: () => [],
    },
});
</script>

<template>
    <div class="h-[500px] w-full overflow-auto rounded-[10px] bg-white p-5">
        <header class="flex items-center justify-between gap-5 border-b-2">
            <div class="flex w-[30%] items-center space-x-5">
                <h1 class="pb-5 text-[20px] font-medium">
                    {{ table_header }}
                </h1>
                <SearchBar :icon="Search" placeholder="Quick Search..." />
            </div>
            <div class="mb-5 h-10 w-px self-center bg-gray-300"></div>
            <div class="flex w-full items-center space-x-5">
                <SearchBar
                    :icon="Barcode"
                    placeholder="Scan or Type the barcode"
                />
                <SearchBar :icon="Item" placeholder="Type the item name" />
                <div class="relative mb-5 w-[300px]">
                    <input
                        type="number"
                        class="h-[40px] w-full border-2 pl-2"
                        placeholder="Quantity"
                    />
                </div>
                <AddButton />
            </div>
        </header>

        <!-- Column Headers -->
        <div class="mt-4 flex px-4 text-center text-sm text-gray-400">
            <span class="min-w-0 flex-1">Item Name</span>
            <span class="min-w-0 flex-1">ID</span>
            <span class="min-w-0 flex-1">Barcode</span>
            <span class="min-w-0 flex-1">Description</span>
            <span class="min-w-0 flex-1">Quantity</span>
            <span class="min-w-0 flex-1">Date</span>
            <span class="min-w-0 flex-1">Status</span>
        </div>

        <!-- Rows -->
        <div class="mt-2 flex flex-col divide-y divide-gray-100">
            <div
                v-for="(row, index) in rows"
                :key="index"
                class="flex items-center px-4 py-3"
            >
                <span class="min-w-0 flex-1 pr-2 text-center font-medium">{{
                    row.item
                }}</span>
                <span class="min-w-0 flex-1 pr-2 text-center">{{
                    row.id
                }}</span>
                <span class="min-w-0 flex-1 pr-2 text-center text-blue-500">{{
                    row.barcode
                }}</span>
                <span
                    class="min-w-0 flex-1 pr-2 text-center text-sm text-gray-600"
                    >{{ row.description }}</span
                >
                <span class="min-w-0 flex-1 pr-2 text-center">{{
                    row.quantity
                }}</span>
                <span class="min-w-0 flex-1 pr-2 text-center text-gray-500">{{
                    row.date
                }}</span>
                <span class="min-w-0 flex-1 pr-2">
                    <div class="flex items-center justify-center">
                        <span
                            class="rounded-md bg-green-100 px-3 py-1 text-xs text-green-600"
                            >{{ row.status }}</span
                        >
                    </div>
                </span>
            </div>
        </div>
    </div>
</template>
