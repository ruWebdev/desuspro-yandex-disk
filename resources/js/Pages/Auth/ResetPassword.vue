<script setup>
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({
    email: {
        type: String,
        required: true,
    },
    token: {
        type: String,
        required: true,
    },
});

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('password.store'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <Head title="Reset Password" />
    <div class="page page-center">
        <div class="container-tight py-4">
            <div class="text-center mb-4">
                <a href="/" class="navbar-brand navbar-brand-autodark">
                    <img src="/favicon.ico" height="36" alt="Logo" />
                </a>
            </div>

            <div class="card card-md">
                <div class="card-body">
                    <h2 class="card-title text-center mb-4">Reset your password</h2>

                    <form @submit.prevent="submit" autocomplete="on">
                        <div class="mb-3">
                            <label class="form-label">Email address</label>
                            <input v-model="form.email" type="email" class="form-control" required autofocus autocomplete="username" />
                            <div class="text-danger small" v-if="form.errors.email">{{ form.errors.email }}</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">New Password</label>
                            <input v-model="form.password" type="password" class="form-control" required autocomplete="new-password" />
                            <div class="text-danger small" v-if="form.errors.password">{{ form.errors.password }}</div>
                        </div>

                        <div class="mb-2">
                            <label class="form-label">Confirm Password</label>
                            <input v-model="form.password_confirmation" type="password" class="form-control" required autocomplete="new-password" />
                            <div class="text-danger small" v-if="form.errors.password_confirmation">{{ form.errors.password_confirmation }}</div>
                        </div>

                        <div class="form-footer">
                            <button type="submit" class="btn btn-primary w-100" :disabled="form.processing">
                                <span v-if="!form.processing">Reset Password</span>
                                <span v-else class="spinner-border spinner-border-sm me-2" role="status"></span>
                                <span v-if="form.processing">Submitting...</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</template>
