<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { dashboard } from '@/routes';
import { 
    ShoppingCart, 
    ArrowRight, 
    TrendingUp,
    Calendar,
    CircleDollarSign,
    Receipt,
    Percent,
    Crown
} from '@lucide/vue';
import { Chart, registerables } from 'chart.js';
import Swal from 'sweetalert2';
import { onMounted, ref } from 'vue';

Chart.register(...registerables);

const props = defineProps<{
    incomeToday: number;
    dailyGrowth: number;
    incomeThisMonth: number;
    monthlyGrowth: number;
    countToday: number;
    countGrowth: number;
    avgToday: number;
    avgGrowth: number;
    chartData: Array<{ label: string; sales: number }>;
    monthlyReport: Array<{ label: string; sales: number }>;
    topProducts: Array<{ name: string; sold: number; revenue: number }>;
    lowStockCount: number;
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

const formatNumber = (value: number) => {
    return new Intl.NumberFormat('id-ID').format(value);
};

const chartCanvasRef = ref<HTMLCanvasElement | null>(null);

onMounted(() => {
    if (chartCanvasRef.value) {
        const ctx = chartCanvasRef.value.getContext('2d');
        let gradientBg = 'rgba(230, 98, 57, 0.8)';
        
        if (ctx) {
            const gradient = ctx.createLinearGradient(0, 0, 0, 240);
            gradient.addColorStop(0, '#e66239');      // Solid orange at top
            gradient.addColorStop(1, 'rgba(230, 98, 57, 0.15)'); // Faded orange at bottom
            gradientBg = gradient;
        }

        new Chart(chartCanvasRef.value, {
            type: 'bar',
            data: {
                labels: props.chartData.map(d => d.label),
                datasets: [
                    {
                        label: 'Penjualan (Rp)',
                        data: props.chartData.map(d => d.sales),
                        backgroundColor: gradientBg,
                        borderColor: '#e66239',
                        borderWidth: 1.5,
                        borderRadius: 6,
                        borderSkipped: false,
                        barPercentage: 0.5
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
                                size: 10,
                                family: 'Poppins'
                            }
                        },
                        grid: {
                            color: 'rgba(120, 120, 120, 0.05)'
                        }
                    },
                    x: {
                        ticks: {
                            font: {
                                size: 10,
                                family: 'Poppins'
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

    // Show warning toast if there are low stock products
    if (props.lowStockCount > 0) {
        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 5000,
            timerProgressBar: true,
            didOpen: (toast) => {
                toast.addEventListener('mouseenter', Swal.stopTimer);
                toast.addEventListener('mouseleave', Swal.resumeTimer);
            }
        });

        Toast.fire({
            icon: 'warning',
            title: 'Pemberitahuan Stok Rendah',
            text: `Terdapat ${props.lowStockCount} produk dengan stok di bawah 20 item. Silakan periksa kembali.`
        });
    }
});
</script>

<template>
    <Head title="Dashboard" />

    <!-- Style Wrapper for Poppins and CSS variables -->
    <div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4 font-sans text-[#171717]" style="font-family: 'Poppins', sans-serif; font-size: 14px;">
        
        <!-- Header Info (Borderless & Flat layout) -->
        <div class="flex flex-col gap-1">
            <h2 class="text-xl font-extrabold tracking-tight text-[#171717] dark:text-white">Dashboard</h2>
            <p class="text-xs text-[#525252] dark:text-neutral-400">Selamat datang kembali di panel Lancar Manunggal.</p>
        </div>

        <!-- TOP ROW STATS: Borderless, Flat background colors matching user design theme -->
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            
            <!-- 1. Total Sales (Pendapatan Hari Ini) -->
            <div class="relative rounded-xl bg-orange-500/[0.04] p-5 flex flex-col justify-between min-h-[125px] border border-orange-500/10">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold text-[#525252] dark:text-neutral-400 uppercase tracking-wider">Pendapatan Hari Ini</span>
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-orange-500 text-white shadow-xs">
                        <CircleDollarSign class="h-4.5 w-4.5" />
                    </div>
                </div>
                <div class="mt-4">
                    <h3 class="text-lg font-extrabold text-[#171717] dark:text-white">{{ formatRupiah(incomeToday) }}</h3>
                    <p :class="['text-[11px] font-bold mt-1', dailyGrowth >= 0 ? 'text-green-600' : 'text-red-600']">
                        {{ dailyGrowth >= 0 ? '+' : '' }}{{ dailyGrowth }}% <span class="text-neutral-400 font-medium font-sans">vs kemarin</span>
                    </p>
                </div>
            </div>

            <!-- 2. Pendapatan Bulan Ini -->
            <div class="relative rounded-xl bg-emerald-500/[0.04] p-5 flex flex-col justify-between min-h-[125px] border border-emerald-500/10">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold text-[#525252] dark:text-neutral-400 uppercase tracking-wider">Pendapatan Bulan Ini</span>
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-500 text-white shadow-xs">
                        <Calendar class="h-4.5 w-4.5" />
                    </div>
                </div>
                <div class="mt-4">
                    <h3 class="text-lg font-extrabold text-[#171717] dark:text-white">{{ formatRupiah(incomeThisMonth) }}</h3>
                    <p :class="['text-[11px] font-bold mt-1', monthlyGrowth >= 0 ? 'text-green-600' : 'text-red-600']">
                        {{ monthlyGrowth >= 0 ? '+' : '' }}{{ monthlyGrowth }}% <span class="text-neutral-400 font-medium">vs bulan lalu</span>
                    </p>
                </div>
            </div>

            <!-- 3. Jumlah Transaksi Hari Ini -->
            <div class="relative rounded-xl bg-blue-500/[0.04] p-5 flex flex-col justify-between min-h-[125px] border border-blue-500/10">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold text-[#525252] dark:text-neutral-400 uppercase tracking-wider">Transaksi Hari Ini</span>
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-500 text-white shadow-xs">
                        <Receipt class="h-4.5 w-4.5" />
                    </div>
                </div>
                <div class="mt-4">
                    <h3 class="text-lg font-extrabold text-[#171717] dark:text-white">{{ formatNumber(countToday) }} Transaksi</h3>
                    <p :class="['text-[11px] font-bold mt-1', countGrowth >= 0 ? 'text-green-600' : 'text-red-600']">
                        {{ countGrowth >= 0 ? '+' : '' }}{{ countGrowth }}% <span class="text-neutral-400 font-medium">vs kemarin</span>
                    </p>
                </div>
            </div>

            <!-- 4. Rata-rata Nilai Transaksi -->
            <div class="relative rounded-xl bg-amber-500/[0.04] p-5 flex flex-col justify-between min-h-[125px] border border-amber-500/10">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold text-[#525252] dark:text-neutral-400 uppercase tracking-wider">Rata-rata Penjualan</span>
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-amber-500 text-white shadow-xs">
                        <Percent class="h-4.5 w-4.5" />
                    </div>
                </div>
                <div class="mt-4">
                    <h3 class="text-lg font-extrabold text-[#171717] dark:text-white">{{ formatRupiah(avgToday) }}</h3>
                    <p :class="['text-[11px] font-bold mt-1', avgGrowth >= 0 ? 'text-green-600' : 'text-red-600']">
                        {{ avgGrowth >= 0 ? '+' : '' }}{{ avgGrowth }}% <span class="text-neutral-400 font-medium">vs kemarin</span>
                    </p>
                </div>
            </div>

        </div>

        <!-- THREE MIDDLE VALUES: Total Profit, Total Payment Returns, Total Expenses (Clean layout, no cards border) -->
        <div class="grid gap-6 md:grid-cols-3 bg-neutral-50 dark:bg-zinc-900/40 p-5 rounded-xl border border-neutral-100 dark:border-neutral-800/80">
            <!-- 1. Total Profit -->
            <div class="flex flex-col gap-1">
                <span class="text-xs font-bold text-[#525252] dark:text-neutral-400 uppercase tracking-wider">Total Profit Estimasi</span>
                <div class="flex items-center justify-between mt-1">
                    <span class="text-lg font-extrabold text-[#171717] dark:text-white">{{ formatRupiah(incomeThisMonth * 0.25) }}</span>
                    <span class="text-xs font-bold text-green-600 bg-green-500/10 px-2 py-0.5 rounded-full">+25% Margin</span>
                </div>
            </div>

            <!-- 2. Pendapatan Harian Rerata -->
            <div class="flex flex-col gap-1 border-t md:border-t-0 md:border-l border-neutral-200 dark:border-neutral-800 md:pl-6 pt-3 md:pt-0">
                <span class="text-xs font-bold text-[#525252] dark:text-neutral-400 uppercase tracking-wider">Rerata Pendapatan Bulanan</span>
                <div class="flex items-center justify-between mt-1">
                    <span class="text-lg font-extrabold text-[#171717] dark:text-white">{{ formatRupiah(incomeThisMonth / 30) }} / hari</span>
                    <span class="text-xs font-bold text-orange-600 bg-orange-500/10 px-2 py-0.5 rounded-full">Stabil</span>
                </div>
            </div>

            <!-- 3. Total Target Bulanan -->
            <div class="flex flex-col gap-1 border-t md:border-t-0 md:border-l border-neutral-200 dark:border-neutral-800 md:pl-6 pt-3 md:pt-0">
                <span class="text-xs font-bold text-[#525252] dark:text-neutral-400 uppercase tracking-wider">Target Bulanan Toko</span>
                <div class="flex items-center justify-between mt-1">
                    <span class="text-lg font-extrabold text-[#171717] dark:text-white">{{ formatRupiah(50000000) }}</span>
                    <span class="text-xs font-bold text-blue-600 bg-blue-500/10 px-2 py-0.5 rounded-full">
                        {{ Math.round((incomeThisMonth / 50000000) * 100) }}% Tercapai
                    </span>
                </div>
            </div>
        </div>

        <!-- BOTTOM GRID: SALES CHART & STATS (Clean layout, seamless cards) -->
        <div class="grid gap-6 lg:grid-cols-12 mt-2">
            
            <!-- Graphic Chart panel (Clean flat card) -->
            <div class="lg:col-span-8 flex flex-col rounded-xl border border-neutral-100 dark:border-neutral-800/80 bg-white dark:bg-zinc-900/40 p-5 shadow-xs min-h-[340px]">
                <div class="flex flex-col gap-0.5 mb-4">
                    <h3 class="text-xs font-bold text-[#525252] dark:text-neutral-400 uppercase tracking-wider">Grafik Penjualan 7 Hari Terakhir</h3>
                    <p class="text-[11px] text-muted-foreground">Monitor perkembangan performa omset harian kasir Anda.</p>
                </div>
                <div class="flex-1 relative min-h-[240px]">
                    <canvas ref="chartCanvasRef"></canvas>
                </div>
            </div>

            <!-- Side column: Monthly Report & Top Products lists combined borderless style -->
            <div class="lg:col-span-4 flex flex-col gap-6">
                <!-- 1. Top Products -->
                <div class="rounded-xl border border-neutral-100 dark:border-neutral-800/80 bg-white dark:bg-zinc-900/40 p-5 shadow-xs flex-1">
                    <div class="flex items-center justify-between mb-3.5">
                        <h3 class="text-xs font-bold text-[#525252] dark:text-neutral-400 uppercase tracking-wider">Produk Terlaris</h3>
                        <Crown class="h-4.5 w-4.5 text-orange-500" />
                    </div>
                    <div class="flex flex-col gap-2.5 max-h-[180px] overflow-y-auto pr-1">
                        <div 
                            v-for="(prod, idx) in topProducts" 
                            :key="idx"
                            class="flex items-center justify-between p-2 rounded-lg bg-neutral-50 dark:bg-zinc-900/20"
                        >
                            <div class="flex items-center gap-2 min-w-0">
                                <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded bg-orange-500/10 text-orange-600 text-[10px] font-extrabold">
                                    {{ idx + 1 }}
                                </span>
                                <div class="min-w-0">
                                    <h4 class="text-[11px] font-bold text-[#171717] dark:text-white truncate">{{ prod.name }}</h4>
                                    <p class="text-[8.5px] text-neutral-400 mt-0.5">{{ formatRupiah(prod.revenue) }}</p>
                                </div>
                            </div>
                            <span class="text-[11px] font-extrabold text-orange-600 shrink-0">{{ prod.sold }} sold</span>
                        </div>
                    </div>
                </div>

                <!-- 2. Monthly Report list -->
                <div class="rounded-xl border border-neutral-100 dark:border-neutral-800/80 bg-white dark:bg-zinc-900/40 p-5 shadow-xs flex-1">
                    <div class="flex items-center justify-between mb-3.5">
                        <h3 class="text-xs font-bold text-[#525252] dark:text-neutral-400 uppercase tracking-wider">Penjualan Bulanan</h3>
                        <span class="rounded bg-orange-500/10 px-2 py-0.5 text-[9px] font-bold text-orange-600">Omset</span>
                    </div>
                    <div class="flex flex-col gap-2.5 max-h-[180px] overflow-y-auto pr-1">
                        <div 
                            v-for="(rep, idx) in monthlyReport" 
                            :key="idx"
                            class="flex items-center justify-between p-2 rounded-lg bg-neutral-50 dark:bg-zinc-900/20 hover:border-orange-500/30 transition-all border border-transparent"
                        >
                            <span class="text-[11px] font-bold text-[#171717] dark:text-white">{{ rep.label }}</span>
                            <span class="text-[11px] font-extrabold text-orange-600">{{ formatRupiah(rep.sales) }}</span>
                        </div>
                    </div>
                </div>
            </div>

        </div>

    </div>
</template>
