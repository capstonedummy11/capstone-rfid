<template>
    <div
        class="relative inline-flex items-center justify-end"
        @mouseenter="showHoverActions"
        @mouseleave="scheduleHideHoverActions"
    >
        <button
            ref="trigger"
            type="button"
            :aria-label="`Settings for ${recordLabel}`"
            aria-haspopup="menu"
            :aria-expanded="open"
            :aria-controls="menuId"
            class="inline-flex h-10 items-center justify-center gap-2 overflow-hidden rounded-lg border border-slate-200 bg-white text-slate-700 shadow-sm transition-[width,background-color] duration-200 hover:border-indigo-300 hover:bg-indigo-50 focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:outline-none"
            :class="open ? 'w-32' : 'w-10'"
            @click="toggle"
            @keydown.down.prevent="openMenu"
        >
            <Menu class="h-5 w-5 shrink-0" />
            <span v-if="open" class="text-sm font-semibold">Settings</span>
        </button>

        <Teleport to="body">
            <div
                v-for="(action, index) in hoverActionsVisible && !open
                    ? actions
                    : []"
                :key="action.id"
                class="fixed z-[100] flex h-9 w-9"
                :style="hoverActionStyle(index)"
                @mouseenter="cancelHideHoverActions"
                @mouseleave="scheduleHideHoverActions"
            >
                <button
                    type="button"
                    tabindex="-1"
                    :aria-label="`${action.label} for ${recordLabel}`"
                    :title="action.label"
                    class="flex h-9 w-9 items-center justify-center rounded-full border border-slate-200 bg-white shadow-md transition hover:scale-110 hover:border-indigo-300 focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:outline-none"
                    :class="previewColor(action.tone)"
                    @click="select(action.id)"
                >
                    <component :is="action.icon" class="h-4 w-4" />
                </button>
            </div>
            <div
                v-if="open"
                :id="menuId"
                ref="menu"
                role="menu"
                :aria-label="`Settings for ${recordLabel}`"
                class="fixed z-[100] w-56 rounded-lg border border-slate-200 bg-white p-1.5 shadow-xl"
                :style="menuStyle"
            >
                <button
                    v-for="action in actions"
                    :key="action.id"
                    type="button"
                    role="menuitem"
                    :data-testid="action.testId"
                    class="flex w-full items-center gap-3 rounded-md px-3 py-2 text-left text-sm font-medium hover:bg-slate-50 focus-visible:bg-slate-100 focus-visible:outline-none"
                    :class="previewColor(action.tone)"
                    @click="select(action.id)"
                >
                    <component :is="action.icon" class="h-4 w-4 shrink-0" />
                    {{ action.label }}
                </button>
            </div>
        </Teleport>
    </div>
</template>

<script setup lang="ts">
import { Menu } from 'lucide-vue-next';
import { nextTick, onBeforeUnmount, ref, useId, type Component } from 'vue';

interface RowAction {
    id: string;
    label: string;
    icon: Component;
    tone?: 'default' | 'amber' | 'sky' | 'emerald' | 'rose';
    testId?: string;
}

const props = defineProps<{
    actions: readonly RowAction[];
    recordLabel: string;
}>();

const emit = defineEmits<{
    select: [id: string];
}>();

const menuId = useId();
const open = ref(false);
const trigger = ref<HTMLButtonElement | null>(null);
const menu = ref<HTMLElement | null>(null);
const menuStyle = ref<Record<string, string>>({});
const hoverActionsVisible = ref(false);
const hoverAnchor = ref<DOMRect | null>(null);
let hideHoverTimer: ReturnType<typeof setTimeout> | undefined;

const hoverActionStyle = (index: number): Record<string, string> => {
    const rect = hoverAnchor.value;
    if (!rect) return {};
    const centerX = rect.left + rect.width / 2;
    const centerY = rect.top + rect.height / 2;
    const orbit = [
        [0, -42],
        [-42, 0],
        [42, 0],
        [0, 42],
        [30, 30],
        [-30, -30],
    ];
    const [offsetX, offsetY] = orbit[index] ?? orbit[5];
    const radius = 60;
    const anchorX = Math.max(
        radius,
        Math.min(centerX, window.innerWidth - radius),
    );
    const anchorY = Math.max(
        radius,
        Math.min(centerY, window.innerHeight - radius),
    );
    return {
        left: `${anchorX}px`,
        top: `${anchorY}px`,
        transform: `translate(-50%, -50%) translate(${offsetX}px, ${offsetY}px)`,
    };
};

const cancelHideHoverActions = () => {
    if (hideHoverTimer) clearTimeout(hideHoverTimer);
    hideHoverTimer = undefined;
};

const hideHoverActions = () => {
    cancelHideHoverActions();
    hoverActionsVisible.value = false;
};

const scheduleHideHoverActions = () => {
    cancelHideHoverActions();
    hideHoverTimer = setTimeout(hideHoverActions, 250);
};

const showHoverActions = () => {
    if (open.value || !trigger.value) return;
    cancelHideHoverActions();
    hoverAnchor.value = trigger.value.getBoundingClientRect();
    hoverActionsVisible.value = true;
};

const previewColor = (tone: RowAction['tone']) => {
    switch (tone) {
        case 'amber':
            return 'text-amber-700';
        case 'sky':
            return 'text-sky-700';
        case 'emerald':
            return 'text-emerald-700';
        case 'rose':
            return 'text-rose-700';
        default:
            return 'text-indigo-700';
    }
};

const closeMenu = (restoreFocus = false) => {
    if (!open.value) return;
    open.value = false;
    document.removeEventListener('pointerdown', onOutsidePointerDown);
    document.removeEventListener('keydown', onDocumentKeydown);
    window.removeEventListener('scroll', onViewportChange, true);
    window.removeEventListener('resize', onViewportChange);
    if (restoreFocus) nextTick(() => trigger.value?.focus());
};

const onOutsidePointerDown = (event: PointerEvent) => {
    const target = event.target as Node;
    if (!trigger.value?.contains(target) && !menu.value?.contains(target)) {
        closeMenu();
    }
};

const onDocumentKeydown = (event: KeyboardEvent) => {
    if (event.key === 'Escape') {
        event.preventDefault();
        closeMenu(true);
        return;
    }

    if (!menu.value?.contains(document.activeElement)) return;
    if (!['ArrowDown', 'ArrowUp', 'Home', 'End'].includes(event.key)) return;

    const items = Array.from(
        menu.value.querySelectorAll<HTMLButtonElement>('[role="menuitem"]'),
    );
    const currentIndex = items.indexOf(
        document.activeElement as HTMLButtonElement,
    );
    const nextIndex =
        event.key === 'Home'
            ? 0
            : event.key === 'End'
              ? items.length - 1
              : event.key === 'ArrowDown'
                ? (currentIndex + 1) % items.length
                : (currentIndex - 1 + items.length) % items.length;
    if (items[nextIndex]) {
        event.preventDefault();
        items[nextIndex].focus();
    }
};

const onViewportChange = () => closeMenu();

const openMenu = async () => {
    if (open.value || !trigger.value) return;
    hideHoverActions();
    const rect = trigger.value.getBoundingClientRect();
    const estimatedHeight = props.actions.length * 40 + 12;
    const below = window.innerHeight - rect.bottom >= estimatedHeight + 8;
    menuStyle.value = {
        left: `${Math.max(8, Math.min(rect.right - 224, window.innerWidth - 232))}px`,
        top: `${below ? rect.bottom + 6 : Math.max(8, rect.top - estimatedHeight - 6)}px`,
    };
    open.value = true;
    document.addEventListener('pointerdown', onOutsidePointerDown);
    document.addEventListener('keydown', onDocumentKeydown);
    window.addEventListener('scroll', onViewportChange, true);
    window.addEventListener('resize', onViewportChange);
    await nextTick();
    menu.value?.querySelector<HTMLButtonElement>('[role="menuitem"]')?.focus();
};

const toggle = () => (open.value ? closeMenu() : openMenu());

const select = (id: string) => {
    hideHoverActions();
    closeMenu();
    emit('select', id);
};

onBeforeUnmount(() => {
    hideHoverActions();
    closeMenu();
});
</script>
