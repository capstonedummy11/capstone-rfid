export const MIN_PASSWORD_LENGTH = 12;

export const PASSWORD_LENGTH_HELPER =
    'Password must be at least 12 characters.';

export const PASSWORD_LENGTH_ERROR =
    'Password must be at least 12 characters long.';

export const isPasswordTooShort = (password: string): boolean =>
    password.length > 0 && password.length < MIN_PASSWORD_LENGTH;
