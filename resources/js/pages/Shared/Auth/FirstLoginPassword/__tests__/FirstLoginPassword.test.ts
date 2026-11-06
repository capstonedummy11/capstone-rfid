import { shallowMount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { nextTick } from 'vue';

import FirstLoginPassword from '../FirstLoginPasswordPage.vue';

const { put, currentForm } = vi.hoisted(() => ({
    put: vi.fn(),
    currentForm: { value: null as null | { errors: Record<string, string> } },
}));

vi.mock('@inertiajs/vue3', async () => {
    const { reactive } = await import('vue');

    return {
        Head: { template: '<span />' },
        router: { post: vi.fn() },
        useForm: (values: Record<string, string>) => {
            const form = reactive({
                ...values,
                errors: {} as Record<string, string>,
                processing: false,
                put,
                setError(field: string, message: string) {
                    form.errors[field] = message;
                },
                clearErrors(...fields: string[]) {
                    for (const field of fields) delete form.errors[field];
                },
            });
            currentForm.value = form;

            return form;
        },
    };
});

vi.mock('sweetalert2', () => ({ default: { fire: vi.fn() } }));

beforeEach(() => {
    put.mockReset();
    currentForm.value = null;
    vi.stubGlobal('route', () => '/first-login/password');
});

describe('first-login password feedback', () => {
    it('updates length and confirmation messages while typing and submits without a page reload', async () => {
        const wrapper = shallowMount(FirstLoginPassword);
        const [password, confirmation] = wrapper.findAll(
            'input[autocomplete="new-password"]',
        );

        await password.setValue('Short1!');
        expect(wrapper.text()).toContain(
            'Password must be at least 12 characters long.',
        );

        await password.setValue('LongEnough123!');
        expect(wrapper.text()).not.toContain(
            'Password must be at least 12 characters long.',
        );

        await confirmation.setValue('Different123!');
        expect(wrapper.text()).toContain('Passwords do not match yet.');
        expect(
            wrapper.find('button[type="submit"]').attributes('disabled'),
        ).toBeDefined();

        await confirmation.setValue('LongEnough123!');
        expect(wrapper.text()).not.toContain('Passwords do not match yet.');
        expect(
            wrapper.find('button[type="submit"]').attributes('disabled'),
        ).toBeUndefined();

        await wrapper.find('form').trigger('submit');
        expect(put).toHaveBeenCalledWith(
            '/first-login/password',
            expect.objectContaining({ preserveScroll: 'errors' }),
        );
    });

    it('shows a server field error in place and clears it when the password changes', async () => {
        const wrapper = shallowMount(FirstLoginPassword);
        const password = wrapper.findAll(
            'input[autocomplete="new-password"]',
        )[0];

        currentForm.value!.errors.password =
            'Choose a password different from your temporary password.';
        await nextTick();

        expect(wrapper.find('#first-login-password-error').text()).toContain(
            'Choose a password different from your temporary password.',
        );

        await password.setValue('NewerPassword123!');
        expect(wrapper.find('#first-login-password-error').exists()).toBe(
            false,
        );
    });
});
