<script setup>
defineOptions({
    layout: null,
});

import { computed, onMounted, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import logo from '@/assets/images/logo-only.jpg';

const props = defineProps({
    rooms: {
        type: Array,
        default: () => [],
    },
    alreadyVerified: {
        type: Boolean,
        default: false,
    },
});

const selectedRoom = ref('');
const gateStep = ref('room');
const pinVerified = ref(props.alreadyVerified);
const pinValue = ref('');
const pinError = ref('');
const pinLoading = ref(false);
const roomLoading = ref(false);
const roomError = ref('');
const roomSearch = ref('');

const roomOptions = computed(() =>
    props.rooms
        .map((room) => {
            if (typeof room === 'string') {
                return {
                    name: room,
                    status: 'offline',
                    label: 'Not in use',
                };
            }

            return {
                name: room?.name ?? '',
                status: room?.status ?? 'offline',
                label: room?.label ?? 'Not in use',
            };
        })
        .filter((room) => room.name !== ''),
);

const filteredRooms = computed(() => {
    if (!roomSearch.value) return roomOptions.value;
    const query = roomSearch.value.toLowerCase();
    return roomOptions.value.filter((room) =>
        room.name.toLowerCase().includes(query),
    );
});

const verifyPin = async () => {
    if (!pinValue.value || !selectedRoom.value) return;

    pinError.value = '';
    pinLoading.value = true;

    try {
        const xsrfRaw = document.cookie
            .split('; ')
            .find((row) => row.startsWith('XSRF-TOKEN='))
            ?.split('=')[1];
        const response = await fetch('/panel-verify', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-XSRF-TOKEN': xsrfRaw ? decodeURIComponent(xsrfRaw) : '',
            },
            body: JSON.stringify({
                pin: pinValue.value,
                room: selectedRoom.value,
            }),
        });

        if (response.ok) {
            pinVerified.value = true;
            unlockPanel();
            return;
        }

        const data = await response.json().catch(() => ({}));
        pinError.value = data.message ?? 'Incorrect PIN. Please try again.';
        pinValue.value = '';
        pinVerified.value = false;
    } catch {
        pinError.value = 'Connection error. Please retry.';
        pinVerified.value = false;
    } finally {
        pinLoading.value = false;
    }
};

const unlockPanel = () => {
    if (!selectedRoom.value || !pinVerified.value) return;

    roomError.value = '';
    roomLoading.value = true;

    const xsrfRaw = document.cookie
        .split('; ')
        .find((row) => row.startsWith('XSRF-TOKEN='))
        ?.split('=')[1];

    fetch('/attendance-control-panel/room', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-XSRF-TOKEN': xsrfRaw ? decodeURIComponent(xsrfRaw) : '',
        },
        body: JSON.stringify({ room: selectedRoom.value }),
    })
        .then(async (response) => {
            if (response.ok) {
                window.location.href = '/attendance-control-panel';
                return;
            }

            const data = await response.json().catch(() => ({}));
            roomError.value =
                data.message ?? 'Unable to assign this room. Please retry.';
        })
        .catch(() => {
            roomError.value = 'Connection error. Please retry.';
        })
        .finally(() => {
            roomLoading.value = false;
        });
};

const resetPinStep = () => {
    gateStep.value = 'room';
    pinValue.value = '';
    pinError.value = '';
    pinVerified.value = false;
};

onMounted(() => {
    if (props.alreadyVerified) {
        gateStep.value = 'room';
        pinVerified.value = true;
    }
});
</script>

<template>
    <div
        class="flex min-h-screen w-full items-center justify-center bg-[#f5f6fa] p-6"
    >
        <div class="w-full max-w-2xl">
            <div class="mb-10 text-center">
                <img
                    :src="logo"
                    alt="Pasay City South High School seal"
                    class="mx-auto h-20 w-20 rounded-full object-cover shadow-lg"
                />
                <h1 class="mt-5 text-3xl font-extrabold text-slate-900">
                    Panel Access
                </h1>
                <p class="mt-2 text-base text-slate-500">
                    Verify to activate the attendance panel.
                </p>
            </div>

            <div
                class="rounded-[28px] bg-white p-10 shadow-xl ring-1 ring-slate-200/70"
            >
                <div v-if="gateStep === 'pin'">
                    <button
                        type="button"
                        class="mb-4 text-xs font-semibold text-slate-400 transition hover:text-slate-700"
                        @click="resetPinStep"
                    >
                        <- Change Room
                    </button>
                    <div
                        class="text-[10px] font-bold tracking-[0.28em] text-slate-400 uppercase"
                    >
                        Step 2 of 2
                    </div>
                    <h2 class="mt-3 text-2xl font-extrabold text-slate-900">
                        Enter Access PIN
                    </h2>
                    <p class="mt-2 text-base text-slate-500">
                        Enter the PIN for {{ selectedRoom }}.
                    </p>
                    <div class="mt-5">
                        <input
                            v-model="pinValue"
                            type="password"
                            inputmode="numeric"
                            maxlength="8"
                            placeholder="PIN"
                            autocomplete="one-time-code"
                            class="w-full rounded-2xl border-2 border-slate-200 bg-slate-50 px-4 py-3.5 text-center text-xl font-bold tracking-[0.5em] text-slate-900 transition outline-none placeholder:tracking-normal placeholder:text-slate-300 focus:border-[#123456] focus:bg-white focus:ring-2 focus:ring-[#123456]/10"
                            :class="
                                pinError
                                    ? 'border-red-300 focus:border-red-400 focus:ring-red-100'
                                    : ''
                            "
                            @keydown.enter="verifyPin"
                        />
                        <p
                            v-if="pinError"
                            class="mt-2 text-center text-xs font-semibold text-red-500"
                        >
                            {{ pinError }}
                        </p>
                    </div>
                    <button
                        type="button"
                        class="mt-5 w-full rounded-2xl bg-[#123456] py-4 text-base font-bold text-white shadow-sm transition hover:bg-[#0e2840] disabled:cursor-not-allowed disabled:opacity-40"
                        :disabled="!pinValue || pinLoading"
                        @click="verifyPin"
                    >
                        <span v-if="pinLoading">Verifying...</span>
                        <span v-else>Verify PIN</span>
                    </button>
                    <Link
                        :href="route('landingPage')"
                        class="mt-3 block w-full rounded-2xl border border-slate-200 bg-white py-3 text-center text-sm font-semibold text-slate-600 transition hover:bg-slate-50"
                    >
                        Exit
                    </Link>
                </div>

                <div v-else>
                    <div
                        class="text-[10px] font-bold tracking-[0.28em] text-slate-400 uppercase"
                    >
                        Step 1 of 2
                    </div>
                    <h2 class="mt-3 text-2xl font-extrabold text-slate-900">
                        Select a Room
                    </h2>
                    <p class="mt-2 text-base text-slate-500">
                        Which room is this panel assigned to?
                    </p>

                    <div class="mt-5">
                        <input
                            v-model="roomSearch"
                            type="text"
                            placeholder="Search rooms..."
                            class="w-full rounded-xl border-2 border-slate-200 bg-slate-50 px-4 py-3 text-base transition outline-none focus:border-[#123456] focus:bg-white focus:ring-2 focus:ring-[#123456]/10"
                        />
                    </div>

                    <div
                        class="mt-4 max-h-96 overflow-y-auto rounded-xl border border-slate-200 bg-slate-50/30 p-4"
                    >
                        <div
                            class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3"
                        >
                            <button
                                v-for="room in filteredRooms"
                                :key="room.name"
                                type="button"
                                class="rounded-2xl border-2 px-4 py-4 text-left text-sm font-semibold transition-all duration-150"
                                :class="
                                    selectedRoom === room.name
                                        ? 'border-[#123456] bg-[#123456] text-white shadow-md'
                                        : 'border-slate-200 text-slate-700 hover:border-slate-300 hover:bg-white'
                                "
                                @click="selectedRoom = room.name"
                            >
                                <div
                                    class="mb-1 text-[10px] font-bold tracking-wide uppercase"
                                    :class="
                                        selectedRoom === room.name
                                            ? 'text-white/60'
                                            : 'text-slate-400'
                                    "
                                >
                                    Room
                                </div>
                                <div class="min-h-8 whitespace-normal">
                                    {{ room.name }}
                                </div>
                                <div
                                    class="mt-3 inline-flex rounded-full px-2.5 py-1 text-[10px] font-bold tracking-[0.18em] uppercase"
                                    :class="
                                        selectedRoom === room.name
                                            ? 'bg-white/15 text-white'
                                            : 'bg-slate-200 text-slate-600'
                                    "
                                >
                                    {{ room.label }}
                                </div>
                            </button>
                        </div>
                        <div
                            v-if="filteredRooms.length === 0"
                            class="py-8 text-center text-sm text-slate-500"
                        >
                            No rooms found
                        </div>
                    </div>

                    <button
                        type="button"
                        class="mt-5 w-full rounded-2xl bg-[#123456] py-4 text-base font-bold text-white shadow-sm transition hover:bg-[#0e2840] disabled:cursor-not-allowed disabled:opacity-40"
                        :disabled="!selectedRoom || roomLoading"
                        @click="
                            props.alreadyVerified
                                ? unlockPanel()
                                : (gateStep = 'pin')
                        "
                    >
                        <span v-if="roomLoading">Opening Panel...</span>
                        <span v-else>
                            {{
                                props.alreadyVerified
                                    ? 'Unlock Panel'
                                    : 'Continue to PIN ->'
                            }}
                        </span>
                    </button>
                    <p
                        v-if="roomError"
                        class="mt-2 text-center text-xs font-semibold text-red-500"
                    >
                        {{ roomError }}
                    </p>
                </div>
            </div>

            <p class="mt-6 text-center text-xs text-slate-400">
                Contact your system administrator if you need access assistance.
            </p>
        </div>
    </div>
</template>
