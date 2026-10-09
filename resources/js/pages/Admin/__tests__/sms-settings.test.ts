import { flushPromises, shallowMount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import SystemSettings from '../SystemSettings/SystemSettingsPage.vue';

vi.mock('@inertiajs/vue3', async () => {
    const { reactive } = await import('vue');
    return {
        router: { put: vi.fn(), delete: vi.fn() },
        usePage: () => ({ props: { auth: { user: { role: 'admin' } } } }),
        useForm: (values: Record<string, unknown>) =>
            reactive({
                ...values,
                errors: {},
                processing: false,
                put: vi.fn(),
                post: vi.fn(),
                reset: vi.fn(),
            }),
    };
});
vi.mock('sweetalert2', () => ({ default: { fire: vi.fn() } }));
vi.mock('@/lib/feedbackModal', () => ({ confirmActionModal: vi.fn() }));

beforeEach(() => {
    vi.stubGlobal(
        'route',
        (name: string, params?: { provider: string }) =>
            `${name}/${params?.provider ?? ''}`,
    );
    vi.stubGlobal('fetch', vi.fn());
});

describe('SMS settings tests', () => {
    it.each(['semaphore', 'iprog', 'philsms'])(
        'tests %s using its entered credentials while another provider is active',
        async (provider) => {
            vi.mocked(fetch).mockResolvedValue({
                ok: true,
                json: async () => ({
                    success: true,
                    message: 'Test SMS sent successfully',
                }),
            } as Response);
            const wrapper = shallowMount(SystemSettings, {
                props: {
                    smsSettings: {
                        primary: 'semaphore',
                        providers: { semaphore: { available: true } },
                    },
                },
                global: { stubs: { Head: true } },
            });
            const card = wrapper.get(
                `[data-testid="sms-provider-${provider}"]`,
            );
            await card.get('input[type="password"]').setValue('entered-token');
            await card.get('input[type="tel"]').setValue('09171234567');
            await card.get('textarea').setValue('My test');
            await card
                .findAll('button')
                .find((button) => button.text() === 'Send Test SMS')!
                .trigger('click');
            await flushPromises();

            expect(fetch).toHaveBeenCalledWith(
                `admin.settings.sms.providers.test/${provider}`,
                expect.objectContaining({
                    body: expect.any(String),
                }),
            );
            const body = JSON.parse(
                vi.mocked(fetch).mock.calls[0][1]!.body as string,
            );
            expect(body).toMatchObject({
                token: 'entered-token',
                phone: '09171234567',
                message: 'My test',
            });
            expect(card.get('[role="status"]').text()).toBe(
                'Test SMS sent successfully',
            );
            wrapper.unmount();
        },
    );

    it('disables the test button until the request finishes and shows a safe failure', async () => {
        let complete!: (response: Response) => void;
        vi.mocked(fetch).mockImplementation(
            () =>
                new Promise((resolve) => {
                    complete = resolve;
                }),
        );
        const wrapper = shallowMount(SystemSettings, {
            global: { stubs: { Head: true } },
        });
        const card = wrapper.get('[data-testid="sms-provider-philsms"]');
        const button = card
            .findAll('button')
            .find((item) => item.text() === 'Send Test SMS')!;
        await button.trigger('click');
        expect(button.attributes('disabled')).toBeDefined();
        expect(button.text()).toContain('Sending');
        await button.trigger('click');
        expect(fetch).toHaveBeenCalledTimes(1);
        complete({
            ok: false,
            json: async () => ({
                success: false,
                message: 'Test SMS failed: An API token is required.',
            }),
        } as Response);
        await flushPromises();
        expect(button.attributes('disabled')).toBeUndefined();
        expect(card.get('[role="status"]').text()).toBe(
            'Test SMS failed: An API token is required.',
        );
        wrapper.unmount();
    });
});
