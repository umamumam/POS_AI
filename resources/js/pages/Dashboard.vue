<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { dashboard } from '@/routes';
import { 
    ShoppingCart, 
    ArrowRight, 
    Sparkles,
    CalendarDays
} from '@lucide/vue';
import { Chart, registerables } from 'chart.js';
import { onMounted, ref } from 'vue';

Chart.register(...registerables);

const props = defineProps<{
    incomeToday: number;
    dailyGrowth: number;
    incomeThisMonth: number;
    monthlyGrowth: number;
    chartData: Array<{ label: string; sales: number }>;
    monthlyReport: Array<{ label: string; sales: number }>;
    topProducts: Array<{ name: string; sold: number; revenue: number }>;
}>();

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

// Formatting utilities
const formatRupiah = (value: number) => {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
    }).format(value);
};

const chartCanvasRef = ref<HTMLCanvasElement | null>(null);

onMounted(() => {
    if (chartCanvasRef.value) {
        new Chart(chartCanvasRef.value, {
            type: 'line',
            data: {
                labels: props.chartData.map(d => d.label),
                datasets: [
                    {
                        label: 'Penjualan (Rp)',
                        data: props.chartData.map(d => d.sales),
                        borderColor: '#ea580c',
                        backgroundColor: 'rgba(234, 88, 12, 0.05)',
                        fill: true,
                        tension: 0.4,
                        borderWidth: 2.5,
                        pointBackgroundColor: '#ea580c',
                        pointHoverRadius: 6,
                        pointRadius: 3
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return 'Rp ' + new Intl.NumberFormat('id-ID').format(Number(value));
                            },
                            font: {
                                size: 10
                            }
                        },
                        grid: {
                            color: 'rgba(120, 120, 120, 0.08)'
                        }
                    },
                    x: {
                        ticks: {
                            font: {
                                size: 10
                            }
                        },
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });
    }
});
</script>

<template>
    <Head title="Dashboard" />

    <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
        <!-- 3 Column Top Grid: POS Shortcut + Income Summary + Top Products -->
        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
            <!-- 1. Kartu Pintasan POS Kasir -->
            <div
                class="relative overflow-hidden rounded-xl border border-sidebar-border bg-gradient-to-br from-orange-500/10 to-amber-500/10 p-5 flex flex-col justify-between"
            >
                <div class="flex items-start justify-between">
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-orange-500 text-white shadow-md shadow-orange-500/20">
                        <ShoppingCart class="h-5 w-5" />
                    </div>
                    <span class="rounded-full bg-orange-500/10 px-2.5 py-0.5 text-[10px] font-bold text-orange-600">POS Kasir</span>
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

            <!-- 2. Ringkasan Pendapatan Card -->
            <div class="relative overflow-hidden rounded-xl border border-sidebar-border bg-card p-5 flex flex-col justify-between">
                <div class="flex items-center justify-between">
                    <h3 class="text-xs font-bold text-muted-foreground uppercase tracking-wider">Ringkasan Pendapatan</h3>
                    <span class="text-orange-500 bg-orange-500/10 p-1.5 rounded-lg">
                        <Sparkles class="h-4 w-4" />
                    </span>
                </div>
                <div class="mt-4 flex flex-col gap-3">
                    <!-- Pendapatan Harian -->
                    <div class="flex items-center justify-between border-b pb-2 border-border/50">
                        <div>
                            <span class="text-[9px] font-bold text-muted-foreground uppercase">Hari Ini</span>
                            <h4 class="text-sm font-extrabold text-foreground">{{ formatRupiah(incomeToday) }}</h4>
                        </div>
                        <div class="text-right">
                            <span :class="['text-[10px] font-extrabold block', dailyGrowth >= 0 ? 'text-green-600' : 'text-red-600']">
                                {{ dailyGrowth >= 0 ? '+' : '' }}{{ dailyGrowth }}%
                            </span>
                            <span class="text-[8px] text-muted-foreground">vs kemarin</span>
                        </div>
                    </div>
                    <!-- Pendapatan Bulanan -->
                    <div class="flex items-center justify-between">
                        <div>
                            <span class="text-[9px] font-bold text-muted-foreground uppercase">Bulan Ini</span>
                            <h4 class="text-sm font-extrabold text-foreground">{{ formatRupiah(incomeThisMonth) }}</h4>
                        </div>
                        <div class="text-right">
                            <span :class="['text-[10px] font-extrabold block', monthlyGrowth >= 0 ? 'text-green-600' : 'text-red-600']">
                                {{ monthlyGrowth >= 0 ? '+' : '' }}{{ monthlyGrowth }}%
                            </span>
                            <span class="text-[8px] text-muted-foreground">vs bulan lalu</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 3. Top Produk Terlaris Card -->
            <div class="relative flex flex-col rounded-xl border border-sidebar-border bg-card p-5 shadow-sm justify-between">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-xs font-bold text-muted-foreground uppercase tracking-wider">Top Produk Terlaris</h3>
                    <span class="text-blue-500 bg-blue-500/10 p-1.5 rounded-lg">
                        <ShoppingCart class="h-4 w-4" />
                    </span>
                </div>
                <div class="flex flex-col gap-2 overflow-y-auto max-h-[140px] pr-1">
                    <div 
                        v-for="(prod, idx) in topProducts" 
                        :key="idx"
                        class="flex items-center justify-between p-2 border rounded-lg bg-muted/5"
                    >
                        <div class="flex items-center gap-1.5 min-w-0">
                            <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded bg-orange-500/15 text-orange-600 text-[10px] font-extrabold">
                                {{ idx + 1 }}
                            </span>
                            <div class="min-w-0">
                                <h4 class="text-[11px] font-bold text-foreground truncate">{{ prod.name }}</h4>
                                <p class="text-[8px] text-muted-foreground mt-0.5">{{ formatRupiah(prod.revenue) }}</p>
                            </div>
                        </div>
                        <div class="text-right shrink-0 ml-2">
                            <span class="text-xs font-extrabold text-orange-600">{{ prod.sold }}x</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Bottom Row: Chart (8 cols) + Monthly Sales Report (4 cols) -->
        <div class="grid gap-4 md:grid-cols-12 mt-2">
            <!-- Daily Sales Chart -->
            <div class="md:col-span-8 flex flex-col rounded-xl border border-sidebar-border bg-card p-5 shadow-sm min-h-[320px]">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h3 class="text-xs font-bold text-muted-foreground uppercase tracking-wider">Grafik Penjualan (7 Hari Terakhir)</h3>
                        <p class="text-[11px] text-muted-foreground mt-0.5">Analisis pendapatan transaksi harian Anda.</p>
                    </div>
                </div>
                <div class="flex-1 relative min-h-[220px]">
                    <canvas ref="chartCanvasRef"></canvas>
                </div>
            </div>

            <!-- Monthly Sales Report -->
            <div class="md:col-span-4 flex flex-col rounded-xl border border-sidebar-border bg-card p-5 shadow-sm justify-between">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h3 class="text-xs font-bold text-muted-foreground uppercase tracking-wider">Laporan Penjualan Bulanan</h3>
                        <p class="text-[11px] text-muted-foreground mt-0.5">Rekapitulasi omset per bulan.</p>
                    </div>
                    <span class="text-orange-500 bg-orange-500/10 p-1.5 rounded-lg">
                        <CalendarDays class="h-4 w-4" />
                    </span>
                </div>
                <div class="flex flex-col gap-2 overflow-y-auto max-h-[240px] flex-1">
                    <div 
                        v-for="(rep, idx) in monthlyReport" 
                        :key="idx"
                        class="flex items-center justify-between p-2.5 border rounded-lg bg-muted/5 hover:border-orange-500/30 transition-all"
                    >
                        <div>
                            <h4 class="text-[11px] font-bold text-foreground">{{ rep.label }}</h4>
                        </div>
                        <div class="text-right">
                            <span class="text-xs font-extrabold text-orange-600 block">{{ formatRupiah(rep.sales) }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
