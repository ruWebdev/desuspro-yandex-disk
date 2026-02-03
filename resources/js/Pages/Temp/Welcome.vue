<script setup>

import Checkbox from '@/Components/Checkbox.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({
    canResetPassword: {
        type: Boolean,
    },
    status: {
        type: String,
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

    <Head title="Вход в систему" />

    <div v-if="status" class="mb-4 font-medium text-sm text-green-600">
        {{ status }}
    </div>

    <div class="page page-center">
        <div class="container container-tight py-4">
            <div class="card card-md">
                <div class="card-body">
                    <h2 class="h2 text-center mb-4">Добро пожаловать</h2>
                    <form @submit.prevent="submit">
                        <div class="mb-3">
                            <label class="form-label">E-mail</label>
                            <TextInput id="email" type="email" class="form-control" v-model="form.email" required
                                autofocus autocomplete="username" placeholder="Укажите ваш E-mail" />
                            <InputError class="mt-2" :message="form.errors.email" />
                        </div>
                        <div class="mb-2">
                            <label class="form-label">
                                Пароль
                            </label>
                            <TextInput id="password" type="password" class="form-control" v-model="form.password"
                                required autocomplete="current-password" />

                            <InputError class="mt-2" :message="form.errors.password" />
                        </div>
                        <div class="mb-2">
                            <label class="form-check">
                                <input type="checkbox" class="form-check-input" />
                                <span class="form-check-label">Запомнить меня на этом устройстве</span>
                            </label>
                        </div>
                        <div class="form-footer">
                            <PrimaryButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                                Войти в систему
                            </PrimaryButton>
                        </div>
                    </form>
                </div>
            </div>
            <div class="text-center text-muted mt-3">
                Для получения пароля от аккаунта обратитесь к Администратору
            </div>
        </div>
    </div>
</template>
