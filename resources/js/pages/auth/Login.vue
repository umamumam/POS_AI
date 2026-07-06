<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { register } from '@/routes';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

defineOptions({
    layout: {
        title: 'Masuk ke Akun Kasir',
        description: 'Silakan masukkan email dan password Anda untuk masuk ke sistem kasir',
    },
});

defineProps<{
    status?: string;
    canResetPassword: boolean;
}>();
</script>

<template>
    <Head title="Masuk" />

    <div
        v-if="status"
        class="mb-4 text-center text-sm font-medium text-green-600"
    >
        {{ status }}
    </div>

    <Form
        v-bind="store.form()"
        :reset-on-success="['password']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-6"
    >
        <div class="grid gap-6">
            <div class="grid gap-2">
                <Label for="email" class="text-xs font-bold text-foreground">Alamat Email</Label>
                <Input
                    id="email"
                    type="email"
                    name="email"
                    required
                    autofocus
                    :tabindex="1"
                    autocomplete="email"
                    placeholder="nama@email.com"
                    class="h-10 text-xs focus-visible:ring-orange-500"
                />
                <InputError :message="errors.email" />
            </div>

            <div class="grid gap-2">
                <div class="flex items-center justify-between">
                    <Label for="password" class="text-xs font-bold text-foreground">Password</Label>
                    <TextLink
                        v-if="canResetPassword"
                        :href="request()"
                        class="text-xs font-bold text-orange-600 hover:text-orange-700 transition-colors"
                        :tabindex="5"
                    >
                        Lupa password?
                    </TextLink>
                </div>
                <PasswordInput
                    id="password"
                    name="password"
                    required
                    :tabindex="2"
                    autocomplete="current-password"
                    placeholder="Masukkan password..."
                    class="h-10 text-xs focus-visible:ring-orange-500"
                />
                <InputError :message="errors.password" />
            </div>

            <div class="flex items-center justify-between">
                <Label for="remember" class="flex items-center space-x-3 cursor-pointer">
                    <Checkbox id="remember" name="remember" :tabindex="3" class="data-[state=checked]:bg-orange-600 data-[state=checked]:border-orange-600" />
                    <span class="text-xs font-medium text-muted-foreground">Ingat saya</span>
                </Label>
            </div>

            <Button
                type="submit"
                class="mt-2 w-full h-10 bg-gradient-to-r from-orange-600 to-amber-500 hover:from-orange-700 hover:to-amber-600 text-white font-bold transition-all duration-300 shadow-md shadow-orange-600/20 border-none rounded-lg text-xs active:scale-[0.98]"
                :tabindex="4"
                :disabled="processing"
                data-test="login-button"
            >
                <Spinner v-if="processing" class="mr-1" />
                Masuk
            </Button>
        </div>

        <div class="text-center text-xs text-muted-foreground">
            Belum memiliki akun?
            <TextLink :href="register()" :tabindex="5" class="font-bold text-orange-600 hover:text-orange-700 transition-colors">Daftar sekarang</TextLink>
        </div>
    </Form>
</template>
