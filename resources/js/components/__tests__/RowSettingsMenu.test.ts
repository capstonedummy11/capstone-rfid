import { mount } from '@vue/test-utils';
import { Pencil, Trash2 } from 'lucide-vue-next';
import { afterEach, expect, it, vi } from 'vitest';
import RowSettingsMenu from '../RowSettingsMenu.vue';

afterEach(() => {
    vi.useRealTimers();
    document.body.innerHTML = '';
});

it('places hover actions around the burger and keeps them clickable', async () => {
    vi.useFakeTimers();
    const wrapper = mount(RowSettingsMenu, {
        attachTo: document.body,
        props: {
            recordLabel: 'Ada Lovelace',
            actions: [
                { id: 'edit', label: 'Edit student', icon: Pencil },
                { id: 'parents', label: 'Manage parents', icon: Pencil },
                { id: 'history', label: 'Enrollment history', icon: Pencil },
                { id: 'password', label: 'Reset password', icon: Pencil },
                {
                    id: 'delete',
                    label: 'Delete student',
                    icon: Trash2,
                    tone: 'rose',
                },
            ],
        },
    });

    const trigger = wrapper.get('button[aria-haspopup="menu"]');
    vi.spyOn(trigger.element, 'getBoundingClientRect').mockReturnValue({
        left: 400,
        top: 400,
        right: 440,
        bottom: 440,
        width: 40,
        height: 40,
    } as DOMRect);
    await wrapper.trigger('mouseenter');
    const rightAction = document.querySelector<HTMLButtonElement>(
        '[aria-label="Enrollment history for Ada Lovelace"]',
    );
    expect(rightAction).not.toBeNull();
    const position = (label: string) => {
        const button = document.querySelector<HTMLButtonElement>(
            `[aria-label="${label} for Ada Lovelace"]`,
        );
        const anchor = [
            button?.parentElement?.style.left,
            button?.parentElement?.style.top,
        ];
        return [anchor, button?.parentElement?.style.transform];
    };
    expect(position('Edit student')).toEqual([
        ['420px', '420px'],
        'translate(-50%, -50%) translate(0px, -42px)',
    ]);
    expect(position('Manage parents')).toEqual([
        ['420px', '420px'],
        'translate(-50%, -50%) translate(-42px, 0px)',
    ]);
    expect(position('Enrollment history')).toEqual([
        ['420px', '420px'],
        'translate(-50%, -50%) translate(42px, 0px)',
    ]);
    expect(position('Reset password')).toEqual([
        ['420px', '420px'],
        'translate(-50%, -50%) translate(0px, 42px)',
    ]);
    expect(position('Delete student')).toEqual([
        ['420px', '420px'],
        'translate(-50%, -50%) translate(30px, 30px)',
    ]);

    await wrapper.trigger('mouseleave');
    rightAction?.parentElement?.dispatchEvent(new MouseEvent('mouseenter'));
    vi.advanceTimersByTime(300);
    await wrapper.vm.$nextTick();
    expect(document.body.contains(rightAction)).toBe(true);

    rightAction?.click();
    await wrapper.vm.$nextTick();
    expect(wrapper.emitted('select')?.[0]).toEqual(['history']);
    expect(document.body.contains(rightAction)).toBe(false);
    wrapper.unmount();
});

it('opens labeled actions, selects one, and closes with Escape', async () => {
    const wrapper = mount(RowSettingsMenu, {
        attachTo: document.body,
        props: {
            recordLabel: 'Ada Lovelace',
            actions: [
                { id: 'edit', label: 'Edit student', icon: Pencil },
                {
                    id: 'delete',
                    label: 'Delete student',
                    icon: Trash2,
                    tone: 'rose',
                },
            ],
        },
    });

    const trigger = wrapper.get('button[aria-haspopup="menu"]');
    expect(trigger.attributes('aria-expanded')).toBe('false');
    expect(document.querySelector('[role="menu"]')).toBeNull();

    await trigger.trigger('click');
    expect(trigger.attributes('aria-expanded')).toBe('true');
    expect(trigger.text()).toContain('Settings');
    expect(document.querySelectorAll('[role="menuitem"]')).toHaveLength(2);

    (
        document.querySelectorAll('[role="menuitem"]')[1] as HTMLButtonElement
    ).click();
    await wrapper.vm.$nextTick();
    expect(wrapper.emitted('select')?.[0]).toEqual(['delete']);
    expect(document.querySelector('[role="menu"]')).toBeNull();

    await trigger.trigger('click');
    document.dispatchEvent(
        new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }),
    );
    await wrapper.vm.$nextTick();
    expect(document.querySelector('[role="menu"]')).toBeNull();
    expect(document.activeElement).toBe(trigger.element);

    wrapper.unmount();
});
