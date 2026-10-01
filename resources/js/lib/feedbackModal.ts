import Swal, { type SweetAlertIcon } from 'sweetalert2';

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
