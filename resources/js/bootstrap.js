import axios from 'axios';
import { Dropdown, Modal, Toast, Tooltip, Popover, Offcanvas } from 'bootstrap';
import { useToast } from 'vue-toastification';

// Make axios available globally
window.axios = axios;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// Initialize Bootstrap components
document.addEventListener('DOMContentLoaded', function() {
    // Initialize all dropdowns
    const dropdownElementList = [].slice.call(document.querySelectorAll('[data-bs-toggle="dropdown"]'));
    dropdownElementList.map(function (dropdownToggleEl) {
        return new Dropdown(dropdownToggleEl);
    });

    // Initialize tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new Tooltip(tooltipTriggerEl);
    });

    // Initialize popovers
    const popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    popoverTriggerList.map(function (popoverTriggerEl) {
        return new Popover(popoverTriggerEl);
    });
});

// Make Bootstrap components available globally
window.bootstrap = {
    Dropdown,
    Modal,
    Toast,
    Tooltip,
    Popover,
    Offcanvas
};
// Make toast available globally (optional convenience)
window.toast = useToast();
