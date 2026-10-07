import Swal, { type SweetAlertIcon } from 'sweetalert2';

// @function showAlertModal: Ipinapakita ang alert modal sa feedback Modal flow.
// @useIn showAlertModal: resources/js/pages/Rfid.vue
export const showAlertModal = (
    title: string,
    text: string,
    icon: SweetAlertIcon = 'info',
) =>
    Swal.fire({
        icon,
        title,
        text,
        confirmButtonText: 'OK',
        confirmButtonColor: '#2563eb',
    });

// @function confirmActionModal: Kinukuha ang confirm action modal result para sa feedback Modal.
// @useIn confirmActionModal: resources/js/pages/StudentsManagement.vue
export const confirmActionModal = async ({
    title,
    text,
    confirmButtonText = 'Delete',
}: {
    title: string;
    text: string;
    confirmButtonText?: string;
}): Promise<boolean> => {
    const result = await Swal.fire({
        icon: 'warning',
        title,
        text,
        showCancelButton: true,
        confirmButtonText,
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#64748b',
        reverseButtons: true,
        focusCancel: true,
    });

    return result.isConfirmed;
};
