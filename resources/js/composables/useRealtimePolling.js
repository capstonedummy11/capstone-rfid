import { onMounted, onUnmounted, watch } from 'vue';

export const REALTIME_POLL_INTERVAL = 5000;

export function useRealtimePolling(poll, enabled = () => true) {
    let active = false;
    let inFlight = false;
    let runAfterInflight = false;
    let timer = null;
    let failures = 0;

    const clearTimer = () => {
        if (timer !== null) {
            window.clearTimeout(timer);
            timer = null;
        }
    };

    const schedule = () => {
        clearTimer();
        if (!active || document.hidden || !enabled()) return;

        const delay =
            failures >= 2
                ? 30000
                : failures === 1
                  ? 10000
                  : REALTIME_POLL_INTERVAL;
        timer = window.setTimeout(run, delay);
    };

    const run = async () => {
        clearTimer();
        if (!active || document.hidden || !enabled()) return;
        if (inFlight) {
            runAfterInflight = true;
            return;
        }

        inFlight = true;
        try {
            await poll();
            failures = 0;
        } catch (error) {
            failures += 1;
            console.warn('Realtime polling failed; it will retry.', error);
        } finally {
            inFlight = false;
            if (runAfterInflight) {
                runAfterInflight = false;
                void run();
            } else {
                schedule();
            }
        }
    };

    const onVisibilityChange = () => {
        if (document.hidden) {
            clearTimer();
        } else {
            void run();
        }
    };

    watch(
        enabled,
        (isEnabled) => {
            clearTimer();
            if (active && isEnabled) void run();
        },
        { flush: 'post' },
    );

    onMounted(() => {
        active = true;
        document.addEventListener('visibilitychange', onVisibilityChange);
        if (enabled() && !document.hidden) void run();
    });

    onUnmounted(() => {
        active = false;
        clearTimer();
        document.removeEventListener('visibilitychange', onVisibilityChange);
    });

    return { pollNow: run };
}
