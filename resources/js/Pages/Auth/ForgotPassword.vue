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
    <Head title="Forgot Password" />
    <div class="page page-center">
        <div class="container-tight py-4">
            <div class="text-center mb-4">
                <a href="/" class="navbar-brand navbar-brand-autodark">
                    <img src="/favicon.ico" height="36" alt="Logo" />
                </a>
            </div>

            <div class="card card-md">
                <div class="card-body">
                    <h2 class="card-title text-center mb-4">Forgot your password?</h2>
                    <p class="text-secondary mb-3">
                        Enter your email and we'll send you a link to reset your password.
                    </p>

                    <div v-if="status" class="alert alert-success" role="alert">{{ status }}</div>

                    <form @submit.prevent="submit" autocomplete="on">
                        <div class="mb-3">
                            <label class="form-label">Email address</label>
                            <input v-model="form.email" type="email" class="form-control" placeholder="you@example.com" required autofocus autocomplete="username" />
                            <div class="text-danger small" v-if="form.errors.email">{{ form.errors.email }}</div>
                        </div>

                        <div class="form-footer">
                            <button type="submit" class="btn btn-primary w-100" :disabled="form.processing">
                                <span v-if="!form.processing">Email Password Reset Link</span>
                                <span v-else class="spinner-border spinner-border-sm me-2" role="status"></span>
                                <span v-if="form.processing">Sending...</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</template>
