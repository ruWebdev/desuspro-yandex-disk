<script>

// Импорт разметки для проекта
import MainLayout from '@/Layouts/MainLayout.vue';
import axios from 'axios';

export default {
    layout: MainLayout
};

</script>

<script setup>
import ContentLayout from '@/Layouts/ContentLayout.vue';
import { Head } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';

const yandex = ref({ connected: false, expires_at: null, loading: true, error: null });

const checkYandexStatus = async () => {
    try {
        const res = await fetch(route('integrations.yandex.status'));
        if (!res.ok) throw new Error('Failed to fetch status');
        const data = await res.json();
        yandex.value = {
            connected: !!data.connected,
            expires_at: data.expires_at,
            loading: false,
            error: null
        };
    } catch (e) {
        yandex.value = {
            connected: false,
            expires_at: null,
            loading: false,
            error: 'Не удалось получить статус Яндекс.Диска'
        };
    }
};

onMounted(checkYandexStatus);
</script>

<template>

    <Head title="Токен Яндекс.Диска" />

    <ContentLayout>
        <div class="page-header d-print-none">
            <div class="container-xl">
                <div class="row g-2 align-items-center">
                    <div class="col">
                        <h2 class="page-title">
                            Управление токеном Яндекс.Диска
                        </h2>
                    </div>
                </div>
            </div>
        </div>

        <div class="page-body">
            <div class="container-xl">
                <div class="card">
                    <div class="card-body">
                        <div v-if="yandex.loading" class="d-flex align-items-center justify-content-center py-4">
                            <div class="spinner-border" role="status">
                                <span class="visually-hidden">Загрузка...</span>
                            </div>
                            <span class="ms-2">Проверка статуса...</span>
                        </div>

                        <div v-else class="row align-items-center">
                            <div class="col-12">
                                <div v-if="yandex.connected" class="d-flex flex-column gap-3">
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="status status-success"></span>
                                        <h3 class="mb-0">Яндекс.Диск подключен</h3>
                                    </div>
                                    <div v-if="yandex.expires_at" class="text-muted">
                                        Токен действителен до: {{ new Date(yandex.expires_at).toLocaleString() }}
                                    </div>
                                    <div>
                                        <a :href="route('integrations.yandex.connect')" class="btn btn-primary">
                                            Обновить токен
                                        </a>
                                    </div>
                                </div>

                                <div v-else class="d-flex flex-column gap-3">
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="status status-danger"></span>
                                        <h3 class="mb-0">Яндекс.Диск не подключен</h3>
                                    </div>
                                    <p>Для работы с Яндекс.Диском необходимо авторизоваться через OAuth</p>
                                    <div>
                                        <a :href="route('integrations.yandex.connect')" class="btn btn-primary">
                                            Подключить Яндекс.Диск
                                        </a>
                                    </div>
                                </div>

                                <div v-if="yandex.error" class="alert alert-danger mt-3 mb-0">
                                    {{ yandex.error }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </ContentLayout>
</template>

<style scoped>
.status {
    display: inline-block;
    width: 0.75rem;
    height: 0.75rem;
    border-radius: 50%;
}

.status-success {
    background-color: #2fb344;
}

.status-danger {
    background-color: #d63939;
}
</style>
