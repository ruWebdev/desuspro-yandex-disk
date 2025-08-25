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
    <Head title="Email Verification" />
    <div class="page page-center">
        <div class="container-tight py-4">
            <div class="text-center mb-4">
                <a href="/" class="navbar-brand navbar-brand-autodark">
                    <img src="/favicon.ico" height="36" alt="Logo" />
                </a>
            </div>

            <div class="card card-md">
                <div class="card-body">
                    <h2 class="card-title text-center mb-2">Verify your email</h2>
                    <p class="text-secondary mb-3">
                        Thanks for signing up! Before getting started, please verify your email address by clicking the link we just emailed to you.
                        If you didn't receive the email, we will gladly send you another.
                    </p>

                    <div class="alert alert-success" v-if="verificationLinkSent" role="alert">
                        A new verification link has been sent to the email address you provided during registration.
                    </div>

                    <form @submit.prevent="submit">
                        <div class="d-flex justify-content-between align-items-center gap-2">
                            <button type="submit" class="btn btn-primary" :disabled="form.processing">
                                <span v-if="!form.processing">Resend Verification Email</span>
                                <span v-else class="spinner-border spinner-border-sm me-2" role="status"></span>
                                <span v-if="form.processing">Sending...</span>
                            </button>

                            <Link :href="route('logout')" method="post" as="button" class="btn btn-link">
                                Log Out
                            </Link>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</template>
