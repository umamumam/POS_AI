<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { store } from '@/routes/register';

defineProps<{
    passwordRules: string;
}>();

defineOptions({
    layout: {
        title: 'Daftar Akun Kasir Baru',
        description: 'Silakan isi formulir di bawah ini untuk membuat akun kasir baru',
    },
});
</script>

<template>
    <Head title="Daftar" />

    <Form
        v-bind="store.form()"
        :reset-on-success="['password', 'password_confirmation']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-6"
    >
        <div class="grid gap-6">
            <div class="grid gap-2">
                <Label for="name" class="text-xs font-bold text-foreground">Nama Lengkap</Label>
                <Input
                    id="name"
                    type="text"
                    required
                    autofocus
                    :tabindex="1"
                    autocomplete="name"
                    name="name"
                    placeholder="Nama lengkap Anda"
                    class="h-10 text-xs focus-visible:ring-orange-500"
                />
                <InputError :message="errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="email" class="text-xs font-bold text-foreground">Alamat Email</Label>
                <Input
                    id="email"
                    type="email"
                    required
                    :tabindex="2"
                    autocomplete="email"
                    name="email"
                    placeholder="nama@email.com"
                    class="h-10 text-xs focus-visible:ring-orange-500"
                />
                <InputError :message="errors.email" />
            </div>

            <div class="grid gap-2">
                <Label for="password" class="text-xs font-bold text-foreground">Password</Label>
                <PasswordInput
                    id="password"
                    required
                    :tabindex="3"
                    autocomplete="new-password"
                    name="password"
                    placeholder="Masukkan password..."
                    :passwordrules="passwordRules"
                    class="h-10 text-xs focus-visible:ring-orange-500"
                />
                <InputError :message="errors.password" />
            </div>

            <div class="grid gap-2">
                <Label for="password_confirmation" class="text-xs font-bold text-foreground">Konfirmasi Password</Label>
                <PasswordInput
                    id="password_confirmation"
                    required
                    :tabindex="4"
                    autocomplete="new-password"
                    name="password_confirmation"
                    placeholder="Ulangi password..."
                    :passwordrules="passwordRules"
                    class="h-10 text-xs focus-visible:ring-orange-500"
                />
                <InputError :message="errors.password_confirmation" />
            </div>

            <Button
                type="submit"
                class="mt-2 w-full h-10 bg-gradient-to-r from-orange-600 to-amber-500 hover:from-orange-700 hover:to-amber-600 text-white font-bold transition-all duration-300 shadow-md shadow-orange-600/20 border-none rounded-lg text-xs active:scale-[0.98]"
                tabindex="5"
                :disabled="processing"
                data-test="register-user-button"
            >
                <Spinner v-if="processing" class="mr-1" />
                Daftar Akun
            </Button>
        </div>

        <div class="text-center text-xs text-muted-foreground">
            Sudah memiliki akun?
            <TextLink
                :href="login()"
                class="font-bold text-orange-600 hover:text-orange-700 transition-colors"
                :tabindex="6"
                >Masuk</TextLink
            >
        </div>
    </Form>
</template>
