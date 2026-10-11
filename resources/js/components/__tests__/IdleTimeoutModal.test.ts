import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import IdleTimeoutModal from '@/components/IdleTimeoutModal.vue';

describe('IdleTimeoutModal', () => {
    it('announces the countdown and exposes both session actions', async () => {
        const wrapper = mount(IdleTimeoutModal, {
            props: { secondsRemaining: 30 },
        });

        expect(wrapper.get('[role="dialog"]').attributes('aria-modal')).toBe(
            'true',
        );
        expect(wrapper.text()).toContain('Are you still there?');
        expect(wrapper.text()).toContain('30 seconds');

        const buttons = wrapper.findAll('button');
        await buttons[0].trigger('click');
        await buttons[1].trigger('click');

        expect(wrapper.emitted('logout')).toHaveLength(1);
        expect(wrapper.emitted('stay')).toHaveLength(1);
    });
});
