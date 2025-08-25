<script setup>
import TablerLayout from '@/Layouts/TablerLayout.vue';
import { Head } from '@inertiajs/vue3';
</script>

<template>
    <Head title="Dashboard" />

    <TablerLayout>
        <template #header>
            Dashboard
        </template>

        <div class="row row-deck">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        You're logged in!
                    </div>
                </div>
            </div>
        </div>
    </TablerLayout>
</template>
