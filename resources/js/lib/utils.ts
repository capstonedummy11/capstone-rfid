import type { InertiaLinkProps } from '@inertiajs/vue3';
import { clsx, type ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';

// @function cn: Kinukuha ang cn result para sa utils.
// @useIn cn: TODO(verify): walang direct caller na nakita sa static search
export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

// @function toUrl: Kinukuha ang to url result para sa utils.
// @useIn toUrl: resources/js/composables/useCurrentUrl.ts
export function toUrl(href: NonNullable<InertiaLinkProps['href']>) {
    return typeof href === 'string' ? href : href?.url;
}
