<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import {
    broadcastIdleLogout,
    idleActivityStorageKey,
    IDLE_LOGOUT_STORAGE_KEY,
    redirectOnExpiredSession,
    xsrfToken,
} from '@/lib/idleSession';
import IdleTimeoutModal from './IdleTimeoutModal.vue';

const page = usePage();
const warningVisible = ref(false);
const secondsRemaining = ref(0);
const busy = ref(false);
const error = ref('');

const settings = computed(() => page.props.idleTimeout ?? {});
const currentRole = computed(() =>
    String(page.props.auth?.user?.role ?? '').toLowerCase(),
);
const currentUserId = computed(
    () => page.props.auth?.user?.user_id ?? page.props.auth?.user?.id ?? null,
);
const exempt = computed(() =>
    (settings.value.exempt_roles ?? []).includes(currentRole.value),
);
const enabled = computed(
    () =>
        Boolean(currentUserId.value && settings.value.enabled) && !exempt.value,
);

const IDLE_TIMEOUT = computed(
    () => Number(settings.value.idle_timeout_seconds ?? 900) * 1000,
);
const WARNING_COUNTDOWN = computed(
    () => Number(settings.value.warning_countdown_seconds ?? 30) * 1000,
);
const ACTIVITY_PING_INTERVAL = computed(
    () => Number(settings.value.activity_ping_seconds ?? 60) * 1000,
);
const ACTIVITY_EVENT_THROTTLE = 1000;
const ACTIVITY_EVENTS = [
    'mousemove',
    'mousedown',
    'keydown',
    'scroll',
    'touchstart',
    'click',
] as const;

let lastActivityAt = Date.now();
let lastHandledInputAt = 0;
let lastServerPingAt = Date.now();
let tickTimer: number | null = null;
let activeActivityKey = '';
let activeLoginUrl = '';
let mounted = false;
let loggingOut = false;

const activityKey = () =>
    idleActivityStorageKey(
        settings.value.session_key || currentUserId.value || 'unknown',
    );

const stopTicking = () => {
    if (tickTimer !== null) {
        window.clearInterval(tickTimer);
        tickTimer = null;
    }
};

const removeActivityListeners = () => {
    ACTIVITY_EVENTS.forEach((eventName) =>
        window.removeEventListener(eventName, handleUserActivity),
    );
    document.removeEventListener('visibilitychange', handleVisibilityChange);
};

const redirectToLogin = (loginUrl = activeLoginUrl) => {
    window.location.replace(loginUrl || '/');
};

const publishLogoutAndRedirect = (loginUrl?: string) => {
    if (activeActivityKey) localStorage.removeItem(activeActivityKey);
    broadcastIdleLogout();
    redirectToLogin(loginUrl);
};

const logout = async () => {
    if (loggingOut) return;
    loggingOut = true;
    busy.value = true;

    let loginUrl = activeLoginUrl;
    try {
        const response = await fetch(String(settings.value.logout_url), {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-XSRF-TOKEN': xsrfToken(),
                'X-User-Activity': '1',
            },
            credentials: 'same-origin',
        });
        const payload = await response.json().catch(() => null);
        loginUrl = payload?.login_url || loginUrl;
    } catch {
        // Redirect locally even if the server cannot be reached.
    } finally {
        publishLogoutAndRedirect(loginUrl);
    }
};

const evaluateIdleState = (now = Date.now()) => {
    if (!enabled.value || loggingOut) return;

    const idleFor = now - lastActivityAt;
    if (idleFor >= IDLE_TIMEOUT.value + WARNING_COUNTDOWN.value) {
        void logout();
        return;
    }

    if (idleFor >= IDLE_TIMEOUT.value) {
        warningVisible.value = true;
        secondsRemaining.value = Math.max(
            0,
            Math.ceil(
                (IDLE_TIMEOUT.value + WARNING_COUNTDOWN.value - idleFor) / 1000,
            ),
        );
        return;
    }

    warningVisible.value = false;
    secondsRemaining.value = Math.ceil(WARNING_COUNTDOWN.value / 1000);
};

const keepAlive = async (fromWarning = false) => {
    if (!enabled.value || loggingOut) return false;
    if (fromWarning) {
        busy.value = true;
        error.value = '';
    }

    lastServerPingAt = Date.now();
    try {
        const response = await fetch(String(settings.value.keep_alive_url), {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-XSRF-TOKEN': xsrfToken(),
                'X-User-Activity': '1',
            },
            credentials: 'same-origin',
        });

        if (redirectOnExpiredSession(response, activeLoginUrl)) return false;
        if (!response.ok)
            throw new Error(`Keep-alive failed (${response.status}).`);

        return true;
    } catch {
        if (fromWarning) {
            error.value =
                'Your session could not be refreshed. Check your connection and try again.';
        }
        return false;
    } finally {
        if (fromWarning) busy.value = false;
    }
};

const recordActivity = (timestamp: number, broadcast = true) => {
    lastActivityAt = timestamp;
    warningVisible.value = false;
    error.value = '';
    secondsRemaining.value = Math.ceil(WARNING_COUNTDOWN.value / 1000);
    if (broadcast && activeActivityKey) {
        localStorage.setItem(activeActivityKey, String(timestamp));
    }
};

function handleUserActivity() {
    if (!enabled.value || warningVisible.value || loggingOut) return;

    const now = Date.now();
    evaluateIdleState(now);
    if (warningVisible.value || loggingOut) return;
    if (now - lastHandledInputAt < ACTIVITY_EVENT_THROTTLE) return;

    lastHandledInputAt = now;
    recordActivity(now);
    if (now - lastServerPingAt >= ACTIVITY_PING_INTERVAL.value) {
        void keepAlive();
    }
}

function handleVisibilityChange() {
    if (!document.hidden) evaluateIdleState(Date.now());
}

const handleStorage = (event: StorageEvent) => {
    if (!enabled.value) return;

    if (event.key === IDLE_LOGOUT_STORAGE_KEY && event.newValue) {
        redirectToLogin();
        return;
    }

    if (event.key !== activeActivityKey || !event.newValue) return;
    const timestamp = Number(event.newValue);
    if (Number.isFinite(timestamp) && timestamp > lastActivityAt) {
        recordActivity(timestamp, false);
    }
};

const stayLoggedIn = async () => {
    if (await keepAlive(true)) recordActivity(Date.now());
};

const deactivate = () => {
    stopTicking();
    removeActivityListeners();
    warningVisible.value = false;
    busy.value = false;
    error.value = '';
};

const activate = () => {
    deactivate();
    if (!mounted || !enabled.value) return;

    loggingOut = false;
    activeActivityKey = activityKey();
    activeLoginUrl = String(settings.value.login_url || '/');
    const storedTimestamp = Number(localStorage.getItem(activeActivityKey));
    const now = Date.now();
    lastActivityAt =
        Number.isFinite(storedTimestamp) && storedTimestamp > 0
            ? storedTimestamp
            : now;
    lastHandledInputAt = 0;
    lastServerPingAt = now;
    if (!storedTimestamp) localStorage.setItem(activeActivityKey, String(now));

    ACTIVITY_EVENTS.forEach((eventName) =>
        window.addEventListener(eventName, handleUserActivity, {
            passive: true,
        }),
    );
    document.addEventListener('visibilitychange', handleVisibilityChange);
    tickTimer = window.setInterval(() => evaluateIdleState(Date.now()), 1000);
    evaluateIdleState(now);
};

watch(
    () => [enabled.value, settings.value.session_key, currentUserId.value],
    ([isEnabled, sessionKey, userId], previous) => {
        const previousUserId = previous?.[2];
        if (previousUserId && !userId) broadcastIdleLogout();
        if (isEnabled && sessionKey) activate();
        else deactivate();
    },
);

onMounted(() => {
    mounted = true;
    window.addEventListener('storage', handleStorage);
    activate();
});

onUnmounted(() => {
    mounted = false;
    deactivate();
    window.removeEventListener('storage', handleStorage);
});
</script>

<template>
    <IdleTimeoutModal
        v-if="warningVisible"
        :seconds-remaining="secondsRemaining"
        :busy="busy"
        :error="error"
        @stay="stayLoggedIn"
        @logout="logout"
    />
</template>
