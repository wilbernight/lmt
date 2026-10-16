import axios from 'axios';
import { toast } from '@/composables/useToast';

// Same-origin only; axios reads the XSRF-TOKEN cookie and sends it back as X-XSRF-TOKEN.
const http = axios.create({
    headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
    withCredentials: false,
    xsrfCookieName: 'XSRF-TOKEN',
    xsrfHeaderName: 'X-XSRF-TOKEN',
});

http.interceptors.request.use((config) => {
    const socketId = window.Echo?.socketId?.();
    if (socketId) config.headers['X-Socket-ID'] = socketId;
    return config;
});

http.interceptors.response.use(
    (response) => response,
    (error) => {
        const status = error.response?.status;
        const data = error.response?.data;

        if (status === 422) {
            const first = data?.errors ? Object.values(data.errors)[0]?.[0] : data?.message;
            toast.error(first || 'اطلاعات واردشده معتبر نیست.');
        } else if (status === 403) {
            toast.error('اجازه انجام این کار را ندارید.');
        } else if (status === 404) {
            toast.error('مورد درخواستی پیدا نشد.');
        } else if (status === 419) {
            toast.error('نشست منقضی شد؛ صفحه را تازه کنید.');
        } else if (status === 429) {
            toast.error('درخواست‌ها زیاد است؛ کمی صبر کنید.');
        } else if (status >= 500) {
            toast.error('خطای سرور. دوباره تلاش کنید.');
        } else if (!error.response) {
            toast.error('ارتباط با سرور برقرار نشد.');
        }

        return Promise.reject(error);
    },
);

export default http;
