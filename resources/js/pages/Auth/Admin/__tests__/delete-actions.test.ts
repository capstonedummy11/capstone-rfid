import { flushPromises, shallowMount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import Sections from '../Sections.vue';
import Students from '../Students.vue';
import Subjects from '../Subjects.vue';

const {
    deleteRequest,
    reload,
    confirmActionModal,
    showAlertModal,
    sweetAlert,
} = vi.hoisted(() => ({
    deleteRequest: vi.fn(),
    reload: vi.fn(),
    confirmActionModal: vi.fn(),
    showAlertModal: vi.fn(),
    sweetAlert: vi.fn(),
}));

vi.mock('@inertiajs/vue3', () => ({
    router: {
        get: vi.fn(),
        reload,
        visit: vi.fn(),
    },
    usePage: () => ({ props: { auth: { user: { role: 'admin' } } } }),
    useForm: (values: Record<string, unknown>) => ({
        ...values,
        errors: {},
        processing: false,
        clearErrors: vi.fn(),
        reset: vi.fn(),
        post: vi.fn(),
        put: vi.fn(),
        delete: deleteRequest,
        transform: vi.fn().mockReturnThis(),
    }),
}));

vi.mock('@/lib/feedbackModal', () => ({
    confirmActionModal,
    showAlertModal,
}));

vi.mock('sweetalert2', () => ({
    default: { fire: sweetAlert },
}));

const route = vi.fn(
    (name: string, params?: Record<string, string | number>) =>
        `${name}/${params?.id ?? ''}`,
);

beforeEach(() => {
    vi.stubGlobal('route', route);
    confirmActionModal.mockResolvedValue(true);
    sweetAlert.mockResolvedValue({ isConfirmed: true });
});

describe('admin delete actions', () => {
    it('deletes the selected section and displays a server rejection', async () => {
        const wrapper = shallowMount(Sections, {
            props: {
                sections: [
                    {
                        section_id: 11,
                        section_name: 'Section 11-A',
                        strand_id: 1,
                        strand_code: 'ICT',
                        year_level: 11,
                        semester: '1st Semester',
                        school_year: '2026-2027',
                        academic_year_id: 1,
                        academic_year_status: 'active',
                        is_writable: true,
                        status: 'active',
                    },
                ],
            },
        });

        await wrapper.get('[data-testid="delete-section"]').trigger('click');
        await flushPromises();

        expect(confirmActionModal).toHaveBeenCalled();
        expect(deleteRequest).toHaveBeenCalledWith(
            'admin.sections.destroy/11',
            expect.any(Object),
        );

        deleteRequest.mock.calls[0][1].onError({ section: 'Has history.' });
        expect(showAlertModal).toHaveBeenCalledWith(
            'Section not deleted',
            'Has history.',
            'error',
        );
    });

    it('deletes the selected subject and reloads after success', async () => {
        const wrapper = shallowMount(Subjects, {
            props: {
                subjects: [
                    {
                        subject_id: 22,
                        subject_name: 'Programming',
                        subject_code: 'PROG-101',
                        subject_description: null,
                        department: 'ICT',
                        unit: 3,
                        semester: '1st Semester',
                        offerings: [],
                        has_locked_offerings: false,
                    },
                ],
            },
        });

        await wrapper.get('[data-testid="delete-subject"]').trigger('click');
        await flushPromises();

        expect(deleteRequest).toHaveBeenCalledWith(
            'admin.subjects.destroy/22',
            expect.any(Object),
        );
        deleteRequest.mock.calls[0][1].onSuccess();
        expect(reload).toHaveBeenCalledWith({ only: ['subjects'] });
    });

    it('deletes the selected student and displays a server rejection', async () => {
        const wrapper = shallowMount(Students, {
            props: {
                canManageStudents: true,
                currentUserRole: 'admin',
                students: [
                    {
                        student_id: 33,
                        student_number: 'STU-33',
                        first_name: 'Ada',
                        middle_name: '',
                        last_name: 'Lovelace',
                        email: 'ada@example.test',
                        phone: '',
                        gender: 'female',
                        strand_id: 1,
                        strand_code: 'ICT',
                        section_id: 11,
                        section_name: 'Section 11-A',
                        year_level: 11,
                        semester: '1st Semester',
                        school_year: '2026-2027',
                        rfid_tag: null,
                        face_images: [],
                        status: 'active',
                        parents: [],
                        enrollments: [],
                    },
                ],
            },
        });

        await wrapper.get('[data-testid="delete-student"]').trigger('click');
        await flushPromises();

        expect(sweetAlert).toHaveBeenCalled();
        expect(deleteRequest).toHaveBeenCalledWith(
            'admin.students.destroy/33',
            expect.any(Object),
        );

        deleteRequest.mock.calls[0][1].onError({ student: 'Not allowed.' });
        expect(showAlertModal).toHaveBeenCalledWith(
            'Student not deleted',
            'Not allowed.',
            'error',
        );
    });
});
