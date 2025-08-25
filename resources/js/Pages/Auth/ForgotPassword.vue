<script setup>
import { Head, useForm } from '@inertiajs/vue3';

defineProps({
    status: {
        type: String,
    },
});

const form = useForm({
    email: '',
});

const submit = () => {
    form.post(route('password.email'));
};
</script>

<template>

    <Head title="Забыли пароль" />
    <div class="page page-center">
        <div class="container-tight py-4">


            <div class="card card-md">
                <div class="card-body">
                    <h2 class="card-title text-center mb-4">Забыли пароль?</h2>
                    <p class="text-secondary mb-3">
                        Укажите ваш email, и мы отправим ссылку для сброса пароля.
                    </p>

                    <div v-if="status" class="alert alert-success" role="alert">{{ status }}</div>

                    <form @submit.prevent="submit" autocomplete="on">
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input v-model="form.email" type="email" class="form-control" placeholder="you@example.com"
                                required autofocus autocomplete="username" />
                            <div class="text-danger small" v-if="form.errors.email">{{ form.errors.email }}</div>
                        </div>

                        <div class="form-footer">
                            <button type="submit" class="btn btn-primary w-100" :disabled="form.processing">
                                <span v-if="!form.processing">Отправить ссылку для сброса</span>
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
