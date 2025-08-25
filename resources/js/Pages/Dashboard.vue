<script setup>
import TablerLayout from '@/Layouts/TablerLayout.vue';
import { Head } from '@inertiajs/vue3';
</script>

<template>
    <Head title="Панель" />

    <TablerLayout>
        <template #header>
            Панель
        </template>

        <div class="row row-deck">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        Вы вошли в систему!
                    </div>
                </div>
            </div>
        </div>
    </TablerLayout>
</template>
