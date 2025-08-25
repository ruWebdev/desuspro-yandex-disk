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
    <Head title="Login" />
    <div class="page page-center">
        <div class="container-tight py-4">
            <div class="text-center mb-4">
                <a href="/" class="navbar-brand navbar-brand-autodark">
                    <img src="/favicon.ico" height="36" alt="Logo" />
                </a>
            </div>

            <div class="card card-md">
                <div class="card-body">
                    <h2 class="card-title text-center mb-4">Sign in to your account</h2>

                    <form @submit.prevent="submit" autocomplete="on">
                        <div class="mb-3">
                            <label class="form-label">Email address</label>
                            <input v-model="form.email" type="email" class="form-control" placeholder="your@email.com" required autocomplete="username" />
                            <div class="text-danger small" v-if="form.errors.email">{{ form.errors.email }}</div>
                        </div>

                        <div class="mb-2">
                            <label class="form-label">
                                Password
                                <span class="form-label-description" v-if="canResetPassword">
                                    <a :href="route('password.request')">Forgot password?</a>
                                </span>
                            </label>
                            <div class="input-group input-group-flat">
                                <input v-model="form.password" type="password" class="form-control" placeholder="Your password" required autocomplete="current-password" />
                            </div>
                            <div class="text-danger small" v-if="form.errors.password">{{ form.errors.password }}</div>
                        </div>

                        <div class="mb-2">
                            <label class="form-check">
                                <input class="form-check-input" type="checkbox" v-model="form.remember" />
                                <span class="form-check-label">Remember me</span>
                            </label>
                        </div>

                        <div class="form-footer">
                            <button type="submit" class="btn btn-primary w-100" :disabled="form.processing">
                                <span v-if="!form.processing">Sign in</span>
                                <span v-else class="spinner-border spinner-border-sm me-2" role="status"></span>
                                <span v-if="form.processing">Signing in...</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div v-if="status" class="alert alert-success mt-3" role="alert">{{ status }}</div>
        </div>
    </div>
</template>
