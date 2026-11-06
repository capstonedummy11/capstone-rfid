export type UseInitialsReturn = {
    getInitials: (fullName?: string) => string;
};

// @function getInitials: Kinukuha ang initials sa use Initials flow.
// @useIn getInitials: resources/js/composables/useInitials.ts:2
export function getInitials(fullName?: string): string {
    if (!fullName) return '';

    const names = fullName.trim().split(' ');

    if (names.length === 0) return '';
    if (names.length === 1) return names[0].charAt(0).toUpperCase();

    return `${names[0].charAt(0)}${names[names.length - 1].charAt(0)}`.toUpperCase();
}

// @function useInitials: Kinukuha ang use initials result para sa use Initials.
// @useIn useInitials: TODO(verify): walang direct caller na nakita sa static search
export function useInitials(): UseInitialsReturn {
    return { getInitials };
}
