<script setup>
import { Head, useForm } from '@inertiajs/vue3';

const form = useForm({
    password: '',
});

const submit = () => {
    form.post(route('password.confirm'), {
        onFinish: () => form.reset(),
    });
};
</script>

<template>

    <Head title="Подтверждение пароля" />
    <div class="page page-center">
        <div class="container-tight py-4">

            <div class="card card-md">
                <div class="card-body">
                    <h2 class="card-title text-center mb-2">Подтвердите пароль</h2>
                    <p class="text-secondary mb-4">Это защищённая область приложения. Пожалуйста, подтвердите пароль
                        перед продолжением.</p>

                    <form @submit.prevent="submit" autocomplete="on">
                        <div class="mb-2">
                            <label class="form-label">Пароль</label>
                            <input v-model="form.password" id="password" type="password" class="form-control" required
                                autocomplete="current-password" autofocus />
                            <div class="text-danger small" v-if="form.errors.password">{{ form.errors.password }}</div>
                        </div>

                        <div class="form-footer">
                            <button type="submit" class="btn btn-primary w-100" :disabled="form.processing">
                                <span v-if="!form.processing">Подтвердить</span>
                                <span v-else class="spinner-border spinner-border-sm me-2" role="status"></span>
                                <span v-if="form.processing">Отправка...</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</template>
