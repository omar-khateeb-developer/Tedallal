import axios from 'axios';

declare global {
    interface Window {
        axios: typeof axios;
    }
}

window.axios = axios;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
window.axios.defaults.withCredentials = true;

const token = document.head.querySelector('meta[name="csrf-token"]');
if (token instanceof HTMLMetaElement) {
    window.axios.defaults.headers.common['X-CSRF-TOKEN'] = token.content;
}

window.axios.interceptors.response.use(
    (response) => response,
    (error) => {
        if (error.response?.status === 503 && error.response?.data?.maintenance) {
            window.location.reload();
        }
        return Promise.reject(error);
    }
);
