import axios from 'axios';

const api = axios.create({
    baseURL: '',
    withCredentials: true,
    headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    },
});

api.defaults.withCredentials = true;
api.defaults.withXSRFToken = true;

api.interceptors.response.use(
    (res) => res,
    (error) => {
        return Promise.reject(error);
    },
);

export default api;
export { api };
