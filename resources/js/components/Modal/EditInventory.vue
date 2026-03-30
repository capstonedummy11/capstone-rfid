<script setup>
import { useForm } from '@inertiajs/vue3';
import { watch } from 'vue';
import Barcode from '@/components/Icon/Barcode.vue';
import BarcodeScanner from '../Cards/Admin/BarcodeScanner.vue';
import SearchBar from '../Cards/Admin/SearchBar.vue';
import Item from '../Icon/Item.vue';
import skuIcon from '../Icon/sku.vue';
import Description from '../Icon/Description.vue';
import Swal from 'sweetalert2';

const props = defineProps({
    show: { type: Boolean, default: false },
    item: { type: Object, default: null },
});

const emit = defineEmits(['close']);

const form = useForm({
    barcode: '',
    name: '',
    sku: '',
    description: '',
    status: '',
});

// Populate form when item changes
watch(
    () => [props.item, props.show],
    ([newItem]) => {
        if (newItem) {
            form.barcode = newItem.barcode ?? '';
            form.name = newItem.name ?? '';
            form.sku = newItem.sku ?? '';
            form.description = newItem.description ?? '';
            form.status = newItem.status ?? 'Available';
        }
    },
    { immediate: true },
);

const onUpdate = () => {
    if (!form.barcode || !form.name) {
        Swal.fire({
            title: 'Missing required fields',
            text: !form.barcode
                ? 'Barcode is required.'
                : 'Item name is required.',
            icon: 'warning',
        });
        return;
    }

    form.put(route('admin.items.update', props.item.item_id), {
        preserveScroll: true,
        onSuccess: () => {
            Swal.fire({
                title: 'Item updated',
                text: `${form.name} has been updated.`,
                icon: 'success',
            });
            emit('close');
        },
        onError: () => {
            Swal.fire({
                title: 'Failed to update item',
                text: Object.values(form.errors)[0],
                icon: 'error',
            });
        },
    });
};
</script>

<template>
    <Teleport to="body">
        <div
            v-if="show"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
            @click.self="emit('close')"
        >
            <div class="w-[500px] rounded-[10px] bg-white p-6 shadow-lg">
                <!-- Header -->
                <div
                    class="mb-5 flex items-center justify-between border-b pb-3"
                >
                    <h2 class="text-lg font-semibold">Edit Item</h2>
                    <button
                        class="cursor-pointer text-gray-400 hover:text-gray-600"
                        @click="emit('close')"
                    >
                        ✕
                    </button>
                </div>

                <!-- Fields -->
                <div class="flex flex-col gap-3">
                    <h1>Barcode:</h1>
                    <BarcodeScanner
                        v-model="form.barcode"
                        :icon="Barcode"
                        placeholder="Scan or Type the barcode"
                    />
                    <h1>Item Name:</h1>
                    <SearchBar
                        v-model="form.name"
                        :icon="Item"
                        placeholder="Type the item name"
                    />
                    <h1>SKU:</h1>
                    <SearchBar
                        v-model="form.sku"
                        :icon="skuIcon"
                        placeholder="SKU"
                    />
                    <h1>Description:</h1>
                    <SearchBar
                        v-model="form.description"
                        :icon="Description"
                        placeholder="Description"
                    />
                    <h1>Status:</h1>
                    <div class="relative w-full">
                        <select
                            v-model="form.status"
                            class="h-[40px] w-full appearance-none border-2 pr-8 pl-3 text-sm text-gray-700 focus:outline-none"
                        >
                            <option value="Available">Available</option>
                            <option value="Unavailable">Unavailable</option>
                            <option value="Maintenance">Maintenance</option>
                        </select>
                        <div
                            class="pointer-events-none absolute inset-y-0 right-2 flex items-center text-gray-400"
                        >
                            ▾
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="mt-5 flex justify-end gap-3">
                    <button
                        class="cursor-pointer rounded-md border px-4 py-2 text-sm text-gray-600 hover:bg-gray-100"
                        @click="emit('close')"
                    >
                        Cancel
                    </button>
                    <button
                        class="cursor-pointer rounded-md bg-blue-500 px-4 py-2 text-sm text-white hover:bg-blue-600 disabled:opacity-50"
                        :disabled="form.processing"
                        @click="onUpdate"
                    >
                        {{ form.processing ? 'Saving...' : 'Save Changes' }}
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
