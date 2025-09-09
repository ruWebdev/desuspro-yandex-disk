<script>



</script>

<script setup>

import { usePage } from '@inertiajs/vue3'
import { ref, onMounted, watch, onBeforeUnmount } from 'vue';

import TopBar from '@/navigation/TopNavigation.vue';

const page = usePage()

function applyBodyScrollPolicy(url) {
    // Hide scrollbar only on dashboard pages
    const isDashboard = url && typeof url === 'string' &&
        (url === '/' || url === '/dashboard' || url.startsWith('/dashboard/') || url.startsWith('?') || url === '');

    if (isDashboard) {
        // Apply to both html and body for cross-browser compatibility
        const style = 'hidden';
        document.documentElement.style.overflowY = style;
        document.body.style.overflowY = style;

        // Also check for any parent scroll containers
        const containers = ['.page-wrapper', '#app', '#main-content-area', '#main-content-container'];
        containers.forEach(selector => {
            const el = document.querySelector(selector);
            if (el) el.style.overflowY = style;
        });
    } else {
        // Reset scroll behavior
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
    // restore scroll on layout unmount as a safety
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