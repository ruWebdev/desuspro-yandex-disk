<script>



</script>

<script setup>

import { usePage } from '@inertiajs/vue3'
import { ref, onMounted, watch, onBeforeUnmount } from 'vue';

import TopBar from '@/navigation/TopNavigation.vue';

const page = usePage()

function applyBodyScrollPolicy(url) {
    // Скрывать полосу прокрутки только на страницах дашборда
    const isDashboard = url && typeof url === 'string' &&
        (url === '/' || url === '/dashboard' || url.startsWith('/dashboard/') || url.startsWith('?') || url === '');

    if (isDashboard) {
        // Применить к html и body для кросс-браузерной совместимости
        const style = 'hidden';
        document.documentElement.style.overflowY = style;
        document.body.style.overflowY = style;

        // Также проверить любые родительские контейнеры прокрутки
        const containers = ['.page-wrapper', '#app', '#main-content-area', '#main-content-container'];
        containers.forEach(selector => {
            const el = document.querySelector(selector);
            if (el) el.style.overflowY = style;
        });
    } else {
        // Сбросить поведение прокрутки
        const style = '';
        document.documentElement.style.overflowY = style;
        document.body.style.overflowY = style;

        const containers = ['.page-wrapper', '#app', '#main-content-area', '#main-content-container'];
        containers.forEach(selector => {
            const el = document.querySelector(selector);
            if (el) el.style.overflowY = style;
        });
    }
}

onMounted(() => {
    document.body.classList.remove('d-flex', 'flex-column', 'page-center')
    document.body.classList.add('layout-fluid', 'theme-light')
    applyBodyScrollPolicy(page.url)
})

watch(() => page.url, (newUrl) => {
    applyBodyScrollPolicy(newUrl)
})

onBeforeUnmount(() => {
    // восстановить прокрутку при размонтировании layout для безопасности
    document.documentElement.style.overflowY = '';
    document.body.style.overflowY = '';
})

</script>


<template>
    <div class="page">
        <TopBar>
        </TopBar>



        <div class="page-wrapper">
            <slot />
        </div>

    </div>
</template>