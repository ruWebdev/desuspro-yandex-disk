<script setup>
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    status: {
        type: String,
    },
});

const form = useForm({});

const submit = () => {
    form.post(route('verification.send'));
};

const verificationLinkSent = computed(
    () => props.status === 'verification-link-sent',
);
</script>

<template>

    <Head title="Подтверждение Email" />
    <div class="page page-center">
        <div class="container-tight py-4">


            <div class="card card-md">
                <div class="card-body">
                    <h2 class="card-title text-center mb-2">Подтвердите ваш email</h2>
                    <p class="text-secondary mb-3">
                        Спасибо за регистрацию! Прежде чем начать, подтвердите адрес электронной почты, перейдя по
                        ссылке из письма, которое мы вам отправили.
                        Если письмо не пришло, мы с радостью отправим его ещё раз.
                    </p>

                    <div class="alert alert-success" v-if="verificationLinkSent" role="alert">
                        Новая ссылка для подтверждения отправлена на указанный при регистрации адрес электронной почты.
                    </div>

                    <form @submit.prevent="submit">
                        <div class="d-flex justify-content-between align-items-center gap-2">
                            <button type="submit" class="btn btn-primary" :disabled="form.processing">
                                <span v-if="!form.processing">Отправить письмо ещё раз</span>
                                <span v-else class="spinner-border spinner-border-sm me-2" role="status"></span>
                                <span v-if="form.processing">Отправка...</span>
                            </button>

                            <Link :href="route('logout')" method="post" as="button" class="btn btn-link">
                            Выйти
                            </Link>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</template>
