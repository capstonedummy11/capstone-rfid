<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Check, ChevronDown, Search, X } from 'lucide-vue-next';

interface SearchableOption {
    value: string | number;
    label: string;
    keywords?: string;
}

const props = withDefaults(
    defineProps<{
        modelValue: string | number | null;
        options: SearchableOption[];
        placeholder?: string;
        emptyText?: string;
        disabled?: boolean;
        clearable?: boolean;
    }>(),
    {
        placeholder: 'Search and select...',
        emptyText: 'No matching options.',
        disabled: false,
        clearable: false,
    },
);

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();

const isOpen = ref(false);
const query = ref('');

const selectedOption = computed(
    () =>
        props.options.find(
            (option) => String(option.value) === String(props.modelValue ?? ''),
        ) ?? null,
);

const filteredOptions = computed(() => {
    const term = query.value.trim().toLowerCase();
    const options = term
        ? props.options.filter((option) =>
              [option.label, option.keywords]
                  .filter(Boolean)
                  .some((value) =>
                      String(value).toLowerCase().includes(term),
                  ),
          )
        : props.options;

    return options.slice(0, 50);
});

watch(
    selectedOption,
    (option) => {
        if (!isOpen.value) query.value = option?.label ?? '';
    },
    { immediate: true },
);

const open = () => {
    if (props.disabled) return;
    query.value = '';
    isOpen.value = true;
};

const selectOption = (option: SearchableOption) => {
    emit('update:modelValue', String(option.value));
    query.value = option.label;
    isOpen.value = false;
};

const clearSelection = () => {
    emit('update:modelValue', '');
    query.value = '';
    isOpen.value = false;
};

const clearInvalidQuery = () => {
    window.setTimeout(() => {
        if (isOpen.value) return;
        query.value = selectedOption.value?.label ?? '';
    }, 120);
};

const closeAfterBlur = () => {
    window.setTimeout(() => {
        isOpen.value = false;
        clearInvalidQuery();
    }, 100);
};
</script>

<template>
    <div class="relative">
        <div
            class="flex items-center rounded-md border border-slate-300 bg-white focus-within:border-blue-500 focus-within:ring-2 focus-within:ring-blue-100"
            :class="{ 'cursor-not-allowed bg-slate-100 opacity-60': disabled }"
        >
            <Search class="ml-3 h-4 w-4 shrink-0 text-slate-400" />
            <input
                v-model="query"
                type="text"
                autocomplete="off"
                :placeholder="placeholder"
                :disabled="disabled"
                class="min-w-0 flex-1 bg-transparent px-2 py-2 text-sm outline-none"
                @focus="open"
                @input="isOpen = true"
                @blur="closeAfterBlur"
                @keydown.esc="isOpen = false"
            />
            <button
                v-if="clearable && selectedOption"
                type="button"
                class="rounded p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-700"
                aria-label="Clear selection"
                @mousedown.prevent="clearSelection"
            >
                <X class="h-4 w-4" />
            </button>
            <ChevronDown class="mr-3 h-4 w-4 shrink-0 text-slate-400" />
        </div>

        <div
            v-if="isOpen"
            class="absolute z-50 mt-1 max-h-56 w-full overflow-y-auto rounded-md border border-slate-200 bg-white py-1 shadow-lg"
        >
            <button
                v-for="option in filteredOptions"
                :key="String(option.value)"
                type="button"
                class="flex w-full items-center justify-between gap-3 px-3 py-2 text-left text-sm hover:bg-blue-50"
                @mousedown.prevent="selectOption(option)"
            >
                <span class="min-w-0 truncate text-slate-700">
                    {{ option.label }}
                </span>
                <Check
                    v-if="
                        String(option.value) === String(modelValue ?? '')
                    "
                    class="h-4 w-4 shrink-0 text-blue-600"
                />
            </button>
            <p
                v-if="filteredOptions.length === 0"
                class="px-3 py-3 text-sm text-slate-400"
            >
                {{ emptyText }}
            </p>
            <p
                v-else-if="options.length > 50 && !query.trim()"
                class="border-t border-slate-100 px-3 py-2 text-xs text-slate-400"
            >
                Type to search all {{ options.length }} options.
            </p>
        </div>
    </div>
</template>
