<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { Mic, Search, ShieldCheck } from '@lucide/vue';
import { home } from '@/routes';

const page = usePage();
const name = page.props.name;

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div
        class="relative grid min-h-screen flex-col items-center justify-center lg:max-w-none lg:grid-cols-12 lg:px-0 bg-background"
    >
        <!-- Left Side: Full Splash Image -->
        <div
            class="relative hidden h-full flex-col lg:flex lg:col-span-5 overflow-hidden select-none"
        >
            <!-- Full Background Image -->
            <img 
                src="/images/auth_store.png" 
                alt="Ilustrasi Toko Kasir" 
                class="absolute inset-0 w-full h-full object-cover"
            />
            <!-- Dark Overlay to blend with design and ensure readability -->
            <div class="absolute inset-0 bg-gradient-to-t from-black/90 via-black/30 to-black/60" />
            
            <!-- Top Branding Overlay -->
            <Link
                :href="home()"
                class="relative z-20 flex items-center gap-2.5 text-lg font-bold tracking-tight text-white m-10"
            >
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-tr from-orange-600 to-amber-500 shadow-md">
                    <AppLogoIcon class="size-6 fill-current text-white" />
                </div>
                <div class="flex flex-col">
                    <span class="font-extrabold leading-none">{{ name || 'POS AI' }}</span>
                    <span class="text-[10px] font-semibold text-neutral-400">Lancar Manunggal</span>
                </div>
            </Link>

            <!-- Bottom Features overlay card -->
            <div class="relative z-20 mt-auto mx-10 mb-2 p-3.5 rounded-xl border border-white/10 bg-black/45 backdrop-blur-md">
                <h4 class="text-[10px] font-bold text-orange-400 mb-2 uppercase tracking-wider">Fitur POS Pintar:</h4>
                <div class="grid grid-cols-3 gap-2">
                    <div class="flex items-center gap-1.5">
                        <div class="flex h-6.5 w-6.5 items-center justify-center rounded bg-white/5 text-orange-500">
                            <Mic class="h-3.5 w-3.5" />
                        </div>
                        <span class="text-[10px] font-semibold text-white leading-tight">Input Suara AI</span>
                    </div>
                    <div class="flex items-center gap-1.5 border-l border-white/10 pl-2.5">
                        <div class="flex h-6.5 w-6.5 items-center justify-center rounded bg-white/5 text-orange-500">
                            <Search class="h-3.5 w-3.5" />
                        </div>
                        <span class="text-[10px] font-semibold text-white leading-tight">Cari Manual</span>
                    </div>
                    <div class="flex items-center gap-1.5 border-l border-white/10 pl-2.5">
                        <div class="flex h-6.5 w-6.5 items-center justify-center rounded bg-white/5 text-orange-500">
                            <ShieldCheck class="h-3.5 w-3.5" />
                        </div>
                        <span class="text-[10px] font-semibold text-white leading-tight">Aman & Lokal</span>
                    </div>
                </div>
            </div>

            <!-- Bottom Copyright Overlay -->
            <div class="relative z-20 text-[10px] text-neutral-500 mx-10 mb-10">
                &copy; 2026 {{ name || 'POS AI' }} - Hak Cipta Dilindungi.
            </div>
        </div>

        <!-- Right Side: Forms Wrapper -->
        <div class="flex min-h-screen items-center justify-center p-6 sm:p-10 lg:col-span-7 bg-background">
            <div class="mx-auto flex w-full max-w-sm flex-col justify-center gap-6">
                <!-- Mobile Branding (Logo and Title, hidden on desktop) -->
                <div class="flex flex-col items-center gap-2 text-center lg:hidden">
                    <Link
                        :href="home()"
                        class="flex flex-col items-center gap-2 font-medium"
                    >
                        <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-gradient-to-tr from-orange-600 to-amber-500 shadow-md">
                            <AppLogoIcon class="size-7 fill-current text-white" />
                        </div>
                    </Link>
                    <div class="mt-2 space-y-1">
                        <h2 class="text-lg font-bold tracking-tight text-[#171717] dark:text-white">{{ name || 'POS AI' }}</h2>
                        <p class="text-xs text-muted-foreground">Lancar Manunggal</p>
                    </div>
                </div>

                <div class="flex flex-col gap-2 text-center">
                    <h1 class="text-2xl font-extrabold tracking-tight text-[#171717] dark:text-white" v-if="title">
                        {{ title }}
                    </h1>
                    <p class="text-sm text-muted-foreground" v-if="description">
                        {{ description }}
                    </p>
                </div>
                <slot />
            </div>
        </div>
    </div>
</template>
