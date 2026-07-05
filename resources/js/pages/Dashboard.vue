<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import PlaceholderPattern from '@/components/PlaceholderPattern.vue';
import { dashboard } from '@/routes';
import { ShoppingCart, ArrowRight } from '@lucide/vue';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: dashboard(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Dashboard" />

    <div
        class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4"
    >
        <div class="grid auto-rows-min gap-4 md:grid-cols-3">
            <!-- Kartu Pintasan POS Kasir -->
            <div
                class="relative overflow-hidden rounded-xl border border-sidebar-border bg-gradient-to-br from-orange-500/10 to-amber-500/10 p-5 flex flex-col justify-between"
            >
                <div class="flex items-start justify-between">
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-orange-500 text-white shadow-md shadow-orange-500/20">
                        <ShoppingCart class="h-5 w-5" />
                    </div>
                    <span class="rounded-full bg-orange-500/10 px-2.5 py-0.5 text-[10px] font-bold text-orange-600">POS Aktif</span>
                </div>
                <div class="mt-4">
                    <h4 class="text-sm font-bold text-foreground">Aplikasi Kasir (POS)</h4>
                    <p class="mt-1 text-xs text-muted-foreground">
                        Buka mesin kasir untuk memindai barang menggunakan kamera AI dan memproses transaksi belanja secara instan.
                    </p>
                </div>
                <div class="mt-5">
                    <Link
                        href="/"
                        class="inline-flex w-full items-center justify-center gap-1.5 rounded-lg bg-orange-600 px-4 py-2 text-xs font-bold text-white shadow-lg shadow-orange-600/15 hover:bg-orange-700 active:scale-95 transition-all"
                    >
                        Buka Aplikasi Kasir <ArrowRight class="h-3.5 w-3.5" />
                    </Link>
                </div>
            </div>

            <div
                class="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
            >
                <PlaceholderPattern />
            </div>
            <div
                class="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
            >
                <PlaceholderPattern />
            </div>
        </div>
        <div
            class="relative min-h-[100vh] flex-1 rounded-xl border border-sidebar-border/70 md:min-h-min dark:border-sidebar-border"
        >
            <PlaceholderPattern />
        </div>
    </div>
</template>
