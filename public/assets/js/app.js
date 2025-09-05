import { createApp } from 'vue';
import FileManager from '../apps/FileManager.vue';

const app = createApp({});
app.component('file-manager', FileManager);
app.mount('#app');