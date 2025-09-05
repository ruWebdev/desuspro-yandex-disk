<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>

    <Head title="Регистрация" />
    <div class="page page-center">
        <div class="container-tight py-4">

            <div class="card card-md">
                <div class="card-body">
                    <h2 class="card-title text-center mb-4">Создание аккаунта</h2>

                    <form @submit.prevent="submit" autocomplete="on">
                        <div class="mb-3">
                            <label class="form-label">Имя</label>
                            <input v-model="form.name" type="text" class="form-control" placeholder="Ваше имя" required
                                autocomplete="name" autofocus />
                            <div class="text-danger small" v-if="form.errors.name">{{ form.errors.name }}</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input v-model="form.email" type="email" class="form-control" placeholder="you@example.com"
                                required autocomplete="username" />
                            <div class="text-danger small" v-if="form.errors.email">{{ form.errors.email }}</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Пароль</label>
                            <input v-model="form.password" type="password" class="form-control" placeholder="Ваш пароль"
                                required autocomplete="new-password" />
                            <div class="text-danger small" v-if="form.errors.password">{{ form.errors.password }}</div>
                        </div>

                        <div class="mb-2">
                            <label class="form-label">Подтверждение пароля</label>
                            <input v-model="form.password_confirmation" type="password" class="form-control"
                                placeholder="Повторите пароль" required autocomplete="new-password" />
                            <div class="text-danger small" v-if="form.errors.password_confirmation">{{
                                form.errors.password_confirmation }}</div>
                        </div>

                        <div class="form-footer">
                            <button type="submit" class="btn btn-primary w-100" :disabled="form.processing">
                                <span v-if="!form.processing">Создать аккаунт</span>
                                <span v-else class="spinner-border spinner-border-sm me-2" role="status"></span>
                                <span v-if="form.processing">Создание...</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="text-center text-secondary mt-3">
                Уже зарегистрированы?
                <Link :href="route('login')">Войти</Link>
            </div>
        </div>
    </div>
</template>
