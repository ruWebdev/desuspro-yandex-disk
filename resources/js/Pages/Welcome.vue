<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import DashByteLayout from '@/Layouts/DashByteLayout.vue';

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


    <div class="container">
        <div class="row justify-content-center align-items-center min-vh-100">
            <div class="col-md-6 col-lg-5 col-xl-4">
                <div class="card">
                    <div class="card-body p-4">
                        <div class="text-center mb-4">
                            <h2 class="mb-1">Войдите в аккаунт</h2>
                            <p class="text-muted">Введите свои учетные данные для входа</p>
                        </div>

                        <form @submit.prevent="submit" class="mb-4">
                            <div class="mb-3">
                                <label class="form-label">Email</label>
                                <input v-model="form.email" type="email" class="form-control form-control-lg"
                                    placeholder="you@example.com" required autocomplete="username"
                                    :class="{ 'is-invalid': form.errors.email }" />
                                <div class="invalid-feedback" v-if="form.errors.email">
                                    {{ form.errors.email }}
                                </div>
                            </div>

                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <label class="form-label mb-0">Пароль</label>
                                    <a v-if="canResetPassword" :href="route('password.request')" class="small">
                                        Забыли пароль?
                                    </a>
                                </div>
                                <input v-model="form.password" type="password" class="form-control form-control-lg"
                                    placeholder="Ваш пароль" required autocomplete="current-password"
                                    :class="{ 'is-invalid': form.errors.password }" />
                                <div class="invalid-feedback" v-if="form.errors.password">
                                    {{ form.errors.password }}
                                </div>
                            </div>

                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="remember"
                                        v-model="form.remember" />
                                    <label class="form-check-label" for="remember">
                                        Запомнить меня
                                    </label>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary btn-lg w-100" :disabled="form.processing">
                                <span v-if="!form.processing">Войти</span>
                                <span v-else class="spinner-border spinner-border-sm me-2" role="status"></span>
                                <span v-if="form.processing">Вход...</span>
                            </button>
                        </form>

                        <div v-if="status" class="alert alert-success" role="alert">
                            {{ status }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

</template>

<style scoped>
.min-vh-100 {
    min-height: 100vh;
}
</style>
