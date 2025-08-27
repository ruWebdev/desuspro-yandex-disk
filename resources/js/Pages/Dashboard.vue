<script setup>
import { router } from '@inertiajs/vue3';
import { onMounted } from 'vue';
import { usePage } from '@inertiajs/vue3';

const page = usePage();
const isManager = page.props.auth?.user?.roles?.includes('Manager');

onMounted(() => {
    if (isManager) {
        router.visit(route('tasks.all'), { replace: true });
    }
});
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
