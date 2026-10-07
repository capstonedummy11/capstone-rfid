const legacyStorageKey = 'studentParentSavedProfiles';
const storageKey = 'studentParentSavedProfiles:v2';
const pendingPreferenceKey = 'studentParentSavePreference:v1';
const staffStorageKey = 'staffSavedProfiles:v1';
const staffPendingPreferenceKey = 'staffSavePreference:v1';
const maxProfiles = 5;
const profileTtlMs = 30 * 24 * 60 * 60 * 1000;

// @function safeParse: Kinukuha ang safe parse result para sa use Saved Student Parent Profiles.
// @useIn safeParse: resources/js/composables/useSavedStudentParentProfiles.js:75
const safeParse = (value) => {
    try {
        return JSON.parse(value);
    } catch {
        return [];
    }
};

// @function initialsFor: Kinukuha ang initials for result para sa use Saved Student Parent Profiles.
// @useIn initialsFor: resources/js/composables/useSavedStudentParentProfiles.js:41
const initialsFor = (name, email) => {
    const source = String(name || email || '').trim();

    if (!source) return '?';

    const parts = source.split(/\s+/).filter(Boolean).slice(0, 2);

    return parts.map((part) => part.charAt(0).toUpperCase()).join('');
};

// @function normalizeProfile: Nino-normalize ang profile sa use Saved Student Parent Profiles flow.
// @useIn normalizeProfile: resources/js/composables/useSavedStudentParentProfiles.js:132
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

// @function isFreshProfile: Sinusuri kung fresh profile para sa use Saved Student Parent Profiles.
// @useIn isFreshProfile: resources/js/composables/useSavedStudentParentProfiles.js:82
const isFreshProfile = (profile) => {
    const savedAt = Date.parse(profile?.savedAt || '');

    return Number.isFinite(savedAt) && Date.now() - savedAt <= profileTtlMs;
};

// @function writeProfiles: Kinukuha ang write profiles result para sa use Saved Student Parent Profiles.
// @useIn writeProfiles: resources/js/composables/useSavedStudentParentProfiles.js:86
const writeProfiles = (profiles) => {
    if (typeof window === 'undefined') return;

    window.localStorage.setItem(storageKey, JSON.stringify(profiles));
};

// @function writeStaffProfiles: Kinukuha ang write staff profiles result para sa use Saved Student Parent Profiles.
// @useIn writeStaffProfiles: resources/js/composables/useSavedStudentParentProfiles.js:176
const writeStaffProfiles = (profiles) => {
    if (typeof window === 'undefined') return;

    window.localStorage.setItem(staffStorageKey, JSON.stringify(profiles));
};

// @function clearLegacyProfiles: Nililinis ang legacy profiles sa use Saved Student Parent Profiles flow.
// @useIn clearLegacyProfiles: resources/js/composables/useSavedStudentParentProfiles.js:73
const clearLegacyProfiles = () => {
    if (typeof window === 'undefined') return;

    window.localStorage.removeItem(legacyStorageKey);
};

// @function getSavedStudentParentProfiles: Kinukuha ang saved student parent profiles sa use Saved Student Parent Profiles flow.
// @useIn getSavedStudentParentProfiles: resources/js/composables/useSavedStudentParentProfiles.js:136
export const getSavedStudentParentProfiles = () => {
    if (typeof window === 'undefined') return [];

    clearLegacyProfiles();

    const savedProfiles = safeParse(window.localStorage.getItem(storageKey));

    if (!Array.isArray(savedProfiles)) return [];

    const profiles = savedProfiles
        .map(normalizeProfile)
        .filter(Boolean)
        .filter(isFreshProfile);

    // Always rewrite the normalized profile so legacy or injected fields such
    // as a password or token cannot remain in browser storage.
    writeProfiles(profiles);

    return profiles;
};

// @function setStudentParentSavePreference: Sine-set ang student parent save preference sa use Saved Student Parent Profiles flow.
// @useIn setStudentParentSavePreference: resources/js/pages/Auth/StudentParentLogin.vue
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

// @function consumeStudentParentSavePreference: Ginagamit nang isang beses ang student parent save preference sa use Saved Student Parent Profiles flow.
// @useIn consumeStudentParentSavePreference: resources/js/layouts/AuthLayout.vue
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

// @function saveStudentParentProfile: Sine-save ang student parent profile sa use Saved Student Parent Profiles flow.
// @useIn saveStudentParentProfile: resources/js/layouts/AuthLayout.vue
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

// @function removeSavedStudentParentProfile: Tinatanggal ang saved student parent profile sa use Saved Student Parent Profiles flow.
// @useIn removeSavedStudentParentProfile: resources/js/pages/Auth/StudentParentLogin.vue
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

// @function getSavedStaffProfiles: Kinukuha ang saved staff profiles sa use Saved Student Parent Profiles flow.
// @useIn getSavedStaffProfiles: resources/js/composables/useSavedStudentParentProfiles.js:226
export const getSavedStaffProfiles = () => {
    if (typeof window === 'undefined') return [];

    const savedProfiles = safeParse(
        window.localStorage.getItem(staffStorageKey),
    );

    if (!Array.isArray(savedProfiles)) return [];

    const profiles = savedProfiles
        .map(normalizeProfile)
        .filter(Boolean)
        .filter(isFreshProfile)
        .filter((profile) =>
            ['admin', 'instructor', 'registrar', 'clinic'].includes(
                String(profile.role || '').toLowerCase(),
            ),
        );

    // Keep only the explicitly normalized display fields in browser storage.
    writeStaffProfiles(profiles);

    return profiles;
};

// @function setStaffSavePreference: Sine-set ang staff save preference sa use Saved Student Parent Profiles flow.
// @useIn setStaffSavePreference: resources/js/pages/Auth/StaffLogin.vue
export const setStaffSavePreference = (email, shouldSave) => {
    if (typeof window === 'undefined') return;

    const normalizedEmail = String(email || '')
        .trim()
        .toLowerCase();

    if (!normalizedEmail) return;

    window.localStorage.setItem(
        staffPendingPreferenceKey,
        JSON.stringify({
            email: normalizedEmail,
            shouldSave: Boolean(shouldSave),
            createdAt: new Date().toISOString(),
        }),
    );
};

// @function consumeStaffSavePreference: Ginagamit nang isang beses ang staff save preference sa use Saved Student Parent Profiles flow.
// @useIn consumeStaffSavePreference: resources/js/layouts/AuthLayout.vue
export const consumeStaffSavePreference = (email) => {
    if (typeof window === 'undefined') return null;

    const normalizedEmail = String(email || '')
        .trim()
        .toLowerCase();
    const preference = safeParse(
        window.localStorage.getItem(staffPendingPreferenceKey),
    );

    window.localStorage.removeItem(staffPendingPreferenceKey);

    if (!preference || preference.email !== normalizedEmail) return null;

    return Boolean(preference.shouldSave);
};

// @function saveStaffProfile: Sine-save ang staff profile sa use Saved Student Parent Profiles flow.
// @useIn saveStaffProfile: resources/js/layouts/AuthLayout.vue
export const saveStaffProfile = (user) => {
    const role = String(user?.role || '').toLowerCase();

    if (!['admin', 'instructor', 'registrar', 'clinic'].includes(role)) return;

    const profile = normalizeProfile(user);

    if (!profile) return;

    const remainingProfiles = getSavedStaffProfiles().filter(
        (savedProfile) => savedProfile.email !== profile.email,
    );

    writeStaffProfiles([profile, ...remainingProfiles].slice(0, maxProfiles));
};

// @function removeSavedStaffProfile: Tinatanggal ang saved staff profile sa use Saved Student Parent Profiles flow.
// @useIn removeSavedStaffProfile: resources/js/pages/Auth/StaffLogin.vue
export const removeSavedStaffProfile = (email) => {
    const normalizedEmail = String(email || '')
        .trim()
        .toLowerCase();
    const profiles = getSavedStaffProfiles().filter(
        (profile) => profile.email !== normalizedEmail,
    );

    writeStaffProfiles(profiles);

    return profiles;
};
