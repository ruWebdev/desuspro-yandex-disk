<script>



</script>

<script setup>

import { usePage } from '@inertiajs/vue3'
import { ref, onMounted } from 'vue';

import TopBar from '@/navigation/TopNavigation.vue';

const page = usePage()

onMounted(() => {
    document.body.classList.remove('d-flex', 'flex-column', 'page-center')
    document.body.classList.add('layout-fluid', 'theme-light')
});

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