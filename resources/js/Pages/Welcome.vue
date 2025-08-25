<script setup>
import { Head, useForm } from '@inertiajs/vue3';

defineProps({
    canResetPassword: {
        type: Boolean,
        default: true,
    },
    status: {
        type: String,
        default: '',
    },
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>

    <Head title="Вход" />
    <div class="page page-center">
        <div class="container-tight py-4">
            <div class="card card-md">
                <div class="card-body">
                    <h2 class="card-title text-center mb-4">Войдите в аккаунт</h2>

                    <form @submit.prevent="submit" autocomplete="on">
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input v-model="form.email" type="email" class="form-control" placeholder="you@example.com"
                                required autocomplete="username" />
                            <div class="text-danger small" v-if="form.errors.email">{{ form.errors.email }}</div>
                        </div>

                        <div class="mb-2">
                            <label class="form-label">
                                Пароль
                                <span class="form-label-description" v-if="canResetPassword">
                                    <a :href="route('password.request')">Забыли пароль?</a>
                                </span>
                            </label>
                            <div class="input-group input-group-flat">
                                <input v-model="form.password" type="password" class="form-control"
                                    placeholder="Ваш пароль" required autocomplete="current-password" />
                            </div>
                            <div class="text-danger small" v-if="form.errors.password">{{ form.errors.password }}</div>
                        </div>

                        <div class="mb-2">
                            <label class="form-check">
                                <input class="form-check-input" type="checkbox" v-model="form.remember" />
                                <span class="form-check-label">Запомнить меня</span>
                            </label>
                        </div>

                        <div class="form-footer">
                            <button type="submit" class="btn btn-primary w-100" :disabled="form.processing">
                                <span v-if="!form.processing">Войти</span>
                                <span v-else class="spinner-border spinner-border-sm me-2" role="status"></span>
                                <span v-if="form.processing">Вход...</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div v-if="status" class="alert alert-success mt-3" role="alert">{{ status }}</div>
        </div>
    </div>
</template>
