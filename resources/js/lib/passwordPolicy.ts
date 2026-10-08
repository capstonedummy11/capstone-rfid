export const MIN_PASSWORD_LENGTH = 12;

export const PASSWORD_LENGTH_HELPER =
    'Password must be at least 12 characters.';

export const PASSWORD_LENGTH_ERROR =
    'Password must be at least 12 characters long.';

// @function isPasswordTooShort: Sinusuri kung password too short para sa password Policy.
// @useIn isPasswordTooShort: resources/js/pages/StudentParent/Profile/ProfilePage.vue
export const isPasswordTooShort = (password: string): boolean =>
    password.length > 0 && password.length < MIN_PASSWORD_LENGTH;

export const PASSWORD_REQUIREMENTS = [
    {
        key: 'length',
        label: `At least ${MIN_PASSWORD_LENGTH} characters`,
        isMet: (password: string) => password.length >= MIN_PASSWORD_LENGTH,
    },
    {
        key: 'mixed-case',
        label: 'Lowercase and uppercase letters',
        isMet: (password: string) =>
            /[a-z]/.test(password) && /[A-Z]/.test(password),
    },
    {
        key: 'number',
        label: 'At least 1 number',
        isMet: (password: string) => /\d/.test(password),
    },
    {
        key: 'symbol',
        label: 'At least 1 symbol',
        isMet: (password: string) => /[^A-Za-z0-9\s]/.test(password),
    },
] as const;

// @function evaluatePasswordRequirements: Pinoproseso ang evaluate password requirements para sa password Policy.
// @useIn evaluatePasswordRequirements: resources/js/pages/Shared/Auth/FirstLoginPassword/FirstLoginPasswordPage.vue
export const evaluatePasswordRequirements = (password: string) =>
    PASSWORD_REQUIREMENTS.map((requirement) => ({
        key: requirement.key,
        label: requirement.label,
        met: requirement.isMet(password),
    }));

// @function meetsPasswordRequirements: Pinoproseso ang meets password requirements para sa password Policy.
// @useIn meetsPasswordRequirements: resources/js/pages/Shared/Auth/FirstLoginPassword/FirstLoginPasswordPage.vue
export const meetsPasswordRequirements = (password: string): boolean =>
    PASSWORD_REQUIREMENTS.every((requirement) => requirement.isMet(password));
