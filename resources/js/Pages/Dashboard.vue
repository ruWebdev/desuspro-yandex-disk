<script setup>
import TablerLayout from '@/Layouts/TablerLayout.vue';
import { Head } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';

const yandex = ref({ connected: false, expires_at: null, loading: true, error: null });

onMounted(async () => {
    try {
        const res = await fetch(route('integrations.yandex.status'));
        if (!res.ok) throw new Error('Failed to fetch status');
        const data = await res.json();
        yandex.value = { connected: !!data.connected, expires_at: data.expires_at, loading: false, error: null };
    } catch (e) {
        yandex.value = { connected: false, expires_at: null, loading: false, error: 'Не удалось получить статус Яндекс.Диска' };
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

        <div class="row row-deck mt-3">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Интеграция с Яндекс.Диском</h3>
                    </div>
                    <div class="card-body">
                        <template v-if="yandex.loading">
                            Проверка статуса...
                        </template>
                        <template v-else>
                            <div v-if="yandex.connected" class="d-flex align-items-center gap-2">
                                <span class="status status-green"></span>
                                <span>Подключено</span>
                                <span v-if="yandex.expires_at" class="text-secondary">(истекает: {{ new Date(yandex.expires_at).toLocaleString() }})</span>
                            </div>
                            <div v-else class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="status status-red"></span>
                                    <span>Не подключено</span>
                                </div>
                                <a :href="route('integrations.yandex.connect')" class="btn btn-primary">
                                    Подключить Яндекс.Диск
                                </a>
                            </div>
                            <div v-if="yandex.error" class="text-danger mt-2">{{ yandex.error }}</div>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </TablerLayout>
</template>
