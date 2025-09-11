import axios from 'axios';

// Сделать axios доступным глобально
window.axios = axios;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
