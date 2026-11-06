import type { ComputedRef, Ref } from 'vue';
import { computed, ref } from 'vue';
import { qrCode, recoveryCodes, secretKey } from '@/routes/two-factor';

export type UseTwoFactorAuthReturn = {
    qrCodeSvg: Ref<string | null>;
    manualSetupKey: Ref<string | null>;
    recoveryCodesList: Ref<string[]>;
    errors: Ref<string[]>;
    hasSetupData: ComputedRef<boolean>;
    clearSetupData: () => void;
    clearErrors: () => void;
    clearTwoFactorAuthData: () => void;
    fetchQrCode: () => Promise<void>;
    fetchSetupKey: () => Promise<void>;
    fetchSetupData: () => Promise<void>;
    fetchRecoveryCodes: () => Promise<void>;
};

// @function fetchJson: Kinukuha ang json sa use Two Factor Auth flow.
// @useIn fetchJson: resources/js/composables/useTwoFactorAuth.ts:44
const fetchJson = async <T>(url: string): Promise<T> => {
    const response = await fetch(url, {
        headers: { Accept: 'application/json' },
    });

    if (!response.ok) {
        throw new Error(`Failed to fetch: ${response.status}`);
    }

    return response.json();
};

const errors = ref<string[]>([]);
const manualSetupKey = ref<string | null>(null);
const qrCodeSvg = ref<string | null>(null);
const recoveryCodesList = ref<string[]>([]);

const hasSetupData = computed<boolean>(
    () => qrCodeSvg.value !== null && manualSetupKey.value !== null,
);

// @function useTwoFactorAuth: Kinukuha ang use two factor auth result para sa use Two Factor Auth.
// @useIn useTwoFactorAuth: TODO(verify): walang direct caller na nakita sa static search
export const useTwoFactorAuth = (): UseTwoFactorAuthReturn => {
    // @function fetchQrCode: Kinukuha ang qr code sa use Two Factor Auth flow.
    // @useIn fetchQrCode: resources/js/composables/useTwoFactorAuth.ts:99
    const fetchQrCode = async (): Promise<void> => {
        try {
            const { svg } = await fetchJson<{ svg: string; url: string }>(
                qrCode.url(),
            );

            qrCodeSvg.value = svg;
        } catch {
            errors.value.push('Failed to fetch QR code');
            qrCodeSvg.value = null;
        }
    };

    // @function fetchSetupKey: Binubuo ang fetch setup key value.
    // @useIn fetchSetupKey: resources/js/composables/useTwoFactorAuth.ts:99
    const fetchSetupKey = async (): Promise<void> => {
        try {
            const { secretKey: key } = await fetchJson<{ secretKey: string }>(
                secretKey.url(),
            );

            manualSetupKey.value = key;
        } catch {
            errors.value.push('Failed to fetch a setup key');
            manualSetupKey.value = null;
        }
    };

    // @function clearSetupData: Nililinis ang setup data sa use Two Factor Auth flow.
    // @useIn clearSetupData: resources/js/composables/useTwoFactorAuth.ts:79
    const clearSetupData = (): void => {
        manualSetupKey.value = null;
        qrCodeSvg.value = null;
        clearErrors();
    };

    // @function clearErrors: Nililinis ang errors sa use Two Factor Auth flow.
    // @useIn clearErrors: resources/js/composables/useTwoFactorAuth.ts:71
    const clearErrors = (): void => {
        errors.value = [];
    };

    // @function clearTwoFactorAuthData: Nililinis ang two factor auth data sa use Two Factor Auth flow.
    // @useIn clearTwoFactorAuthData: resources/js/composables/useTwoFactorAuth.ts:13
    const clearTwoFactorAuthData = (): void => {
        clearSetupData();
        clearErrors();
        recoveryCodesList.value = [];
    };

    // @function fetchRecoveryCodes: Kinukuha ang recovery codes sa use Two Factor Auth flow.
    // @useIn fetchRecoveryCodes: resources/js/composables/useTwoFactorAuth.ts:17
    const fetchRecoveryCodes = async (): Promise<void> => {
        try {
            clearErrors();
            recoveryCodesList.value = await fetchJson<string[]>(
                recoveryCodes.url(),
            );
        } catch {
            errors.value.push('Failed to fetch recovery codes');
            recoveryCodesList.value = [];
        }
    };

    // @function fetchSetupData: Kinukuha ang setup data sa use Two Factor Auth flow.
    // @useIn fetchSetupData: resources/js/composables/useTwoFactorAuth.ts:16
    const fetchSetupData = async (): Promise<void> => {
        try {
            clearErrors();
            await Promise.all([fetchQrCode(), fetchSetupKey()]);
        } catch {
            qrCodeSvg.value = null;
            manualSetupKey.value = null;
        }
    };

    return {
        qrCodeSvg,
        manualSetupKey,
        recoveryCodesList,
        errors,
        hasSetupData,
        clearSetupData,
        clearErrors,
        clearTwoFactorAuthData,
        fetchQrCode,
        fetchSetupKey,
        fetchSetupData,
        fetchRecoveryCodes,
    };
};
