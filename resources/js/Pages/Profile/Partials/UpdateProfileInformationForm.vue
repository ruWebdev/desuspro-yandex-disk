<script setup>
import InputError from '@/components/InputError.vue';
import InputLabel from '@/components/InputLabel.vue';
import PrimaryButton from '@/components/PrimaryButton.vue';
import TextInput from '@/components/TextInput.vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';

defineProps({
    mustVerifyEmail: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const user = usePage().props.auth.user;

const form = useForm({
    name: user.name,
    last_name: user.last_name ?? '',
    first_name: user.first_name ?? '',
    middle_name: user.middle_name ?? '',
    email: user.email,
});
</script>

<template>
    <section>
        <header>
            <h2 class="text-lg font-medium text-gray-900">
                Профиль пользователя
            </h2>

            <p class="mt-1 text-sm text-gray-600">
                Обновите данные профиля и адрес электронной почты.
            </p>
        </header>

        <form
            @submit.prevent="form.patch(route('profile.update'))"
            class="mt-6 space-y-6"
        >
            <div>
                <InputLabel for="name" value="Отображаемое имя" />

                <TextInput
                    id="name"
                    type="text"
                    class="mt-1 block w-full"
                    v-model="form.name"
                    required
                    autofocus
                    autocomplete="name"
                />

                <InputError class="mt-2" :message="form.errors.name" />
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div>
                    <InputLabel for="last_name" value="Фамилия" />
                    <TextInput
                        id="last_name"
                        type="text"
                        class="mt-1 block w-full"
                        v-model="form.last_name"
                        autocomplete="family-name"
                    />
                    <InputError class="mt-2" :message="form.errors.last_name" />
                </div>
                <div>
                    <InputLabel for="first_name" value="Имя" />
                    <TextInput
                        id="first_name"
                        type="text"
                        class="mt-1 block w-full"
                        v-model="form.first_name"
                        autocomplete="given-name"
                    />
                    <InputError class="mt-2" :message="form.errors.first_name" />
                </div>
                <div>
                    <InputLabel for="middle_name" value="Отчество" />
                    <TextInput
                        id="middle_name"
                        type="text"
                        class="mt-1 block w-full"
                        v-model="form.middle_name"
                        autocomplete="additional-name"
                    />
                    <InputError class="mt-2" :message="form.errors.middle_name" />
                </div>
            </div>

            <div>
                <InputLabel for="email" value="Email" />

                <TextInput
                    id="email"
                    type="email"
                    class="mt-1 block w-full"
                    v-model="form.email"
                    required
                    autocomplete="username"
                />

                <InputError class="mt-2" :message="form.errors.email" />
            </div>

            <div v-if="mustVerifyEmail && user.email_verified_at === null">
                <p class="mt-2 text-sm text-gray-800">
                    Ваш email не подтверждён.
                    <Link
                        :href="route('verification.send')"
                        method="post"
                        as="button"
                        class="rounded-md text-sm text-gray-600 underline hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                    >
                        Нажмите здесь, чтобы отправить письмо с подтверждением повторно.
                    </Link>
                </p>

                <div
                    v-show="status === 'verification-link-sent'"
                    class="mt-2 text-sm font-medium text-green-600"
                >
                    На ваш email отправлена новая ссылка для подтверждения.
                </div>
            </div>

            <div class="flex items-center gap-4">
                <PrimaryButton :disabled="form.processing">Сохранить</PrimaryButton>

                <Transition
                    enter-active-class="transition ease-in-out"
                    enter-from-class="opacity-0"
                    leave-active-class="transition ease-in-out"
                    leave-to-class="opacity-0"
                >
                    <p
                        v-if="form.recentlySuccessful"
                        class="text-sm text-gray-600"
                    >
                        Сохранено.
                    </p>
                </Transition>
            </div>

            <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <div class="text-sm text-gray-600">Статус доступа</div>
                    <span
                        :class="['inline-flex mt-1 items-center rounded px-2 py-0.5 text-xs font-medium', user.is_blocked ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800']"
                    >{{ user.is_blocked ? 'Заблокирован' : 'Разрешен' }}</span>
                </div>
                <div>
                    <div class="text-sm text-gray-600">Аккаунт создан</div>
                    <div class="mt-1 text-sm">{{ new Date(user.created_at).toLocaleString() }}</div>
                </div>
            </div>
        </form>
    </section>
</template>
