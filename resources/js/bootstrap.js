import axios from 'axios';

import { useToast } from 'vue-toastification';

// Make axios available globally
window.axios = axios;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// Make toast available globally (optional convenience)
window.toast = useToast();
