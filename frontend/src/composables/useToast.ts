import { toast } from 'vue3-toastify';

export function useToast() {
    const success = (message: string) => {
        toast.success(message, { autoClose: 3000 });
    };

    const error = (message: string) => {
        toast.error(message, { autoClose: 4000 });
    };

    const warning = (message: string) => {
        toast.warning(message, { autoClose: 3500 });
    };

    const info = (message: string) => {
        toast.info(message, { autoClose: 3000 });
    };

    return { success, error, warning, info };
}
