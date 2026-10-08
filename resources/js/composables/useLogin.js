import { ref } from 'vue';

const isLoginOpen = ref(false);

// @function useLogin: Kinukuha ang use login result para sa use Login.
// @useIn useLogin: resources/js/pages/Public/Legacy/Landing/LandingPage.vue
export function useLogin() {
    return { isLoginOpen };
}
