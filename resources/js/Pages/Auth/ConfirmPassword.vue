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
    <Head title="Confirm Password" />
    <div class="page page-center">
        <div class="container-tight py-4">
            <div class="text-center mb-4">
                <a href="/" class="navbar-brand navbar-brand-autodark">
                    <img src="/favicon.ico" height="36" alt="Logo" />
                </a>
            </div>

            <div class="card card-md">
                <div class="card-body">
                    <h2 class="card-title text-center mb-2">Confirm your password</h2>
                    <p class="text-secondary mb-4">This is a secure area of the application. Please confirm your password before continuing.</p>

                    <form @submit.prevent="submit" autocomplete="on">
                        <div class="mb-2">
                            <label class="form-label">Password</label>
                            <input v-model="form.password" id="password" type="password" class="form-control" required autocomplete="current-password" autofocus />
                            <div class="text-danger small" v-if="form.errors.password">{{ form.errors.password }}</div>
                        </div>

                        <div class="form-footer">
                            <button type="submit" class="btn btn-primary w-100" :disabled="form.processing">
                                <span v-if="!form.processing">Confirm</span>
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
