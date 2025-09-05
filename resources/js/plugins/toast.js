import { useToast } from 'vue-toastification';
import 'vue-toastification/dist/index.css';

// Toast configuration
export const toastConfig = {
    position: 'bottom-right',
    timeout: 5000,
    closeOnClick: true,
    pauseOnFocusLoss: true,
    pauseOnHover: true,
    draggable: true,
    draggablePercent: 0.6,
    showCloseButtonOnHover: false,
    hideProgressBar: false,
    closeButton: 'button',
    icon: true,
    rtl: false
};

// Toast types
export const toastTypes = {
    SUCCESS: 'success',
    ERROR: 'error',
    WARNING: 'warning',
    INFO: 'info'
};

// Toast service
export const useToastService = () => {
    const toast = useToast();

    const show = (message, type = toastTypes.INFO, options = {}) => {
        const toastOptions = { ...toastConfig, ...options };

        switch (type) {
            case toastTypes.SUCCESS:
                return toast.success(message, toastOptions);
            case toastTypes.ERROR:
                return toast.error(message, { ...toastOptions, timeout: 8000 });
            case toastTypes.WARNING:
                return toast.warning(message, toastOptions);
            case toastTypes.INFO:
            default:
                return toast.info(message, toastOptions);
        }
    };

    return {
        show,
        success: (message, options) => show(message, toastTypes.SUCCESS, options),
        error: (message, options) => show(message, toastTypes.ERROR, options),
        warning: (message, options) => show(message, toastTypes.WARNING, options),
        info: (message, options) => show(message, toastTypes.INFO, options),
        clear: () => toast.clear(),
        dismiss: (id) => toast.dismiss(id),
        update: (id, options) => toast.update(id, options, true)
    };
};

// Plugin installation
export default {
    install: (app) => {
        app.config.globalProperties.$toast = useToastService();
        app.provide('toast', useToastService());
    }
};
