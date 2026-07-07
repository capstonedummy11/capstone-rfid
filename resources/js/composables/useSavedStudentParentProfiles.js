const legacyStorageKey = 'studentParentSavedProfiles';
const storageKey = 'studentParentSavedProfiles:v2';
const pendingPreferenceKey = 'studentParentSavePreference:v1';
const maxProfiles = 5;
const profileTtlMs = 30 * 24 * 60 * 60 * 1000;

const safeParse = (value) => {
    try {
        return JSON.parse(value);
    } catch {
        return [];
    }
};

const initialsFor = (name, email) => {
    const source = String(name || email || '').trim();

    if (!source) return '?';

    const parts = source.split(/\s+/).filter(Boolean).slice(0, 2);

    return parts.map((part) => part.charAt(0).toUpperCase()).join('');
};

const normalizeProfile = (profile) => {
    const email = String(profile?.email || '')
        .trim()
        .toLowerCase();

    if (!email) return null;

    const name = String(profile?.name || email).trim();
    const role = String(profile?.role || 'Student').trim();

    return {
        email,
        name,
        role: role.charAt(0).toUpperCase() + role.slice(1).toLowerCase(),
        initials: initialsFor(name, email),
        savedAt: profile?.savedAt || new Date().toISOString(),
    };
};

const isFreshProfile = (profile) => {
    const savedAt = Date.parse(profile?.savedAt || '');

    return Number.isFinite(savedAt) && Date.now() - savedAt <= profileTtlMs;
};

const writeProfiles = (profiles) => {
    if (typeof window === 'undefined') return;

    window.localStorage.setItem(storageKey, JSON.stringify(profiles));
};

const clearLegacyProfiles = () => {
    if (typeof window === 'undefined') return;

    window.localStorage.removeItem(legacyStorageKey);
};

export const getSavedStudentParentProfiles = () => {
    if (typeof window === 'undefined') return [];

    clearLegacyProfiles();

    const savedProfiles = safeParse(window.localStorage.getItem(storageKey));

    if (!Array.isArray(savedProfiles)) return [];

    const profiles = savedProfiles
        .map(normalizeProfile)
        .filter(Boolean)
        .filter(isFreshProfile);

    if (profiles.length !== savedProfiles.length) {
        writeProfiles(profiles);
    }

    return profiles;
};

export const setStudentParentSavePreference = (email, shouldSave) => {
    if (typeof window === 'undefined') return;

    const normalizedEmail = String(email || '')
        .trim()
        .toLowerCase();

    if (!normalizedEmail) return;

    window.localStorage.setItem(
        pendingPreferenceKey,
        JSON.stringify({
            email: normalizedEmail,
            shouldSave: Boolean(shouldSave),
            createdAt: new Date().toISOString(),
        }),
    );
};

export const consumeStudentParentSavePreference = (email) => {
    if (typeof window === 'undefined') return null;

    const normalizedEmail = String(email || '')
        .trim()
        .toLowerCase();
    const preference = safeParse(
        window.localStorage.getItem(pendingPreferenceKey),
    );

    window.localStorage.removeItem(pendingPreferenceKey);

    if (!preference || preference.email !== normalizedEmail) return null;

    return Boolean(preference.shouldSave);
};

export const saveStudentParentProfile = (user) => {
    const role = String(user?.role || '').toLowerCase();

    if (!['student', 'parent'].includes(role)) return;

    const profile = normalizeProfile(user);

    if (!profile) return;

    const remainingProfiles = getSavedStudentParentProfiles().filter(
        (savedProfile) => savedProfile.email !== profile.email,
    );

    writeProfiles([profile, ...remainingProfiles].slice(0, maxProfiles));
};

export const removeSavedStudentParentProfile = (email) => {
    const normalizedEmail = String(email || '')
        .trim()
        .toLowerCase();
    const profiles = getSavedStudentParentProfiles().filter(
        (profile) => profile.email !== normalizedEmail,
    );

    writeProfiles(profiles);

    return profiles;
};
