import axios from 'axios';

const api = axios.create({
    baseURL: '/api/v1',
    withCredentials: true,
    headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    },
});

const token = document.head
    ?.querySelector('meta[name="csrf-token"]')
    ?.getAttribute('content');

if (token) {
    api.defaults.headers.common['X-CSRF-TOKEN'] = token;
}

// api.get('/sanctum/csrf-cookie').catch(() => { });

api.interceptors.response.use(
    (res) => res,
    (error) => {
        return Promise.reject(error);
    },
);

// expose for convenient imports
export default api;
export { api };
