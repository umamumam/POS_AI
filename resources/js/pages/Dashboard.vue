<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import PlaceholderPattern from '@/components/PlaceholderPattern.vue';
import { dashboard } from '@/routes';
import { 
    ShoppingCart, 
    ArrowRight, 
    Mic, 
    MicOff, 
    Sparkles, 
    Plus, 
    Minus, 
    Trash2, 
    AlertCircle, 
    X, 
    Printer,
    Search
} from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

interface Category {
    id: number;
    nama: string;
}

interface Product {
    id: number;
    nama: string;
    kode: string;
    harga_beli: number;
    harga_jual: number;
    stok: number;
    kategori_id: number;
    kategori?: Category;
}

interface CartItem {
    product: Product;
    qty: number;
}

interface ReceiptDetail {
    id: number;
    transaksi_id: number;
    produk_id: number;
    jumlah: number;
    harga: number;
    subtotal: number;
    created_at: string;
    updated_at: string;
    produk: Product;
}

interface Receipt {
    id: number;
    kode: string;
    total: number;
    bayar: number;
    kembalian: number;
    tanggaltransaksi: string;
    created_at: string;
    updated_at: string;
    details: ReceiptDetail[];
}

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

// CSRF Utility
const getCsrfToken = () => {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
};

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

const formatDate = (dateStr: string) => {
    if (!dateStr) return '';
    const date = new Date(dateStr);
    const months = [
        'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun',
        'Jul', 'Agt', 'Sep', 'Okt', 'Nov', 'Des'
    ];
    const day = String(date.getDate()).padStart(2, '0');
    const month = months[date.getMonth()];
    const year = date.getFullYear();
    return `${day} ${month} ${year}`;
};

// Cart State
const cart = ref<CartItem[]>([]);
const payAmount = ref<number | null>(null);
const showReceiptModal = ref(false);
const activeReceipt = ref<Receipt | null>(null);

const cartTotal = computed(() => {
    return cart.value.reduce((total, item) => total + item.product.harga_jual * item.qty, 0);
});

const changeAmount = computed(() => {
    if (payAmount.value === null || payAmount.value < cartTotal.value) return 0;
    return payAmount.value - cartTotal.value;
});

// Voice / Text State
const activeTabInput = ref<'voice' | 'manual'>('voice');
const isListening = ref(false);
const voiceText = ref('');
const isVoiceAnalyzing = ref(false);
const voiceError = ref('');

// Manual Search State
const manualSearchQuery = ref('');
const manualProducts = ref<Product[]>([]);
const isSearchingManual = ref(false);

let recognition: any = null;

const initSpeechRecognition = () => {
    const SpeechRecognition = (window as any).SpeechRecognition || (window as any).webkitSpeechRecognition;
    if (!SpeechRecognition) {
        voiceError.value = 'Browser Anda tidak mendukung input suara. Silakan ketik perintah secara manual di bawah ini.';
        return;
    }
    
    recognition = new SpeechRecognition();
    recognition.lang = 'id-ID';
    recognition.continuous = false;
    recognition.interimResults = false;

    recognition.onstart = () => {
        isListening.value = true;
        voiceError.value = '';
    };

    recognition.onresult = (event: any) => {
        const transcript = event.results[0][0].transcript;
        voiceText.value = (voiceText.value ? voiceText.value + ' ' : '') + transcript;
    };

    recognition.onerror = (event: any) => {
        console.error('Speech error:', event.error);
        if (event.error === 'not-allowed') {
            voiceError.value = 'Izin mikrofon ditolak. Harap izinkan akses mikrofon.';
        } else {
            voiceError.value = 'Gagal merekam suara: ' + event.error;
        }
        isListening.value = false;
    };

    recognition.onend = () => {
        isListening.value = false;
    };
};

const toggleListening = () => {
    if (!recognition) {
        initSpeechRecognition();
    }
    if (!recognition) return;

    if (isListening.value) {
        recognition.stop();
    } else {
        try {
            recognition.start();
        } catch (e) {
            console.error('Recognition start error:', e);
        }
    }
};

const analyzeVoiceText = async () => {
    if (!voiceText.value.trim() || isVoiceAnalyzing.value) return;

    isVoiceAnalyzing.value = true;
    voiceError.value = '';

    try {
        const token = getCsrfToken();
        const response = await fetch('/api/pos/analyze-text', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': token || '',
            },
            body: JSON.stringify({ text: voiceText.value }),
        });

        const data = await response.json();

        if (response.ok && data.success && data.matches) {
            // Append or add resolved matches to cart
            data.matches.forEach((match: any) => {
                const product = match.product;
                const qtyToAdd = match.qty;

                const existingIndex = cart.value.findIndex(
                    (item) => item.product.id === product.id,
                );

                if (existingIndex > -1) {
                    const newQty = cart.value[existingIndex].qty + qtyToAdd;
                    if (newQty <= product.stok) {
                        cart.value[existingIndex].qty = newQty;
                    } else {
                        cart.value[existingIndex].qty = product.stok;
                    }
                } else {
                    if (product.stok > 0) {
                        const finalQty = Math.min(qtyToAdd, product.stok);
                        cart.value.push({ product, qty: finalQty });
                    }
                }
            });
            // Clear text command on success
            voiceText.value = '';
        } else {
            voiceError.value = data.message || 'Tidak ada produk yang cocok dengan perintah teks Anda.';
        }
    } catch (err: any) {
        console.error('Voice/Text Analysis error:', err);
        voiceError.value = err.message || 'Gagal menganalisis perintah suara/teks.';
    } finally {
        isVoiceAnalyzing.value = false;
    }
};

// Fetch manual products from search API
const fetchManualProducts = async () => {
    isSearchingManual.value = true;
    try {
        const response = await fetch(`/api/products?q=${manualSearchQuery.value}`);
        const data = await response.json();
        manualProducts.value = data.data || [];
    } catch (err) {
        console.error('Gagal mengambil data produk manual:', err);
    } finally {
        isSearchingManual.value = false;
    }
};

const addManualProductToCart = (product: Product) => {
    const existingIndex = cart.value.findIndex(
        (item) => item.product.id === product.id,
    );

    if (existingIndex > -1) {
        const newQty = cart.value[existingIndex].qty + 1;
        if (newQty <= product.stok) {
            cart.value[existingIndex].qty = newQty;
        } else {
            alert(`Stok untuk ${product.nama} sudah mencapai batas maksimum.`);
        }
    } else {
        if (product.stok > 0) {
            cart.value.push({ product, qty: 1 });
        } else {
            alert(`Produk ${product.nama} habis.`);
        }
    }
    // Auto reset search field on click
    manualSearchQuery.value = '';
};

// Lifecycle
onMounted(() => {
    fetchManualProducts();
});

// Watch manual search input
watch(manualSearchQuery, () => {
    fetchManualProducts();
});

const updateQty = (productId: number, change: number) => {
    const index = cart.value.findIndex((item) => item.product.id === productId);
    if (index > -1) {
        const item = cart.value[index];
        const newQty = item.qty + change;

        if (newQty <= 0) {
            cart.value.splice(index, 1);
        } else if (newQty <= item.product.stok) {
            item.qty = newQty;
        } else {
            alert('Stok tidak mencukupi.');
        }
    }
};

const clearCart = () => {
    cart.value = [];
    payAmount.value = null;
};

const setQuickPay = (amount: number) => {
    payAmount.value = amount;
};

const checkoutTransaction = async () => {
    if (cart.value.length === 0 || isVoiceAnalyzing.value) return;

    if (payAmount.value === null || payAmount.value < cartTotal.value) {
        alert('Jumlah bayar kurang.');
        return;
    }

    isVoiceAnalyzing.value = true;

    try {
        const token = getCsrfToken();
        const itemsPayload = cart.value.map((item) => ({
            id: item.product.id,
            qty: item.qty,
            harga: item.product.harga_jual,
        }));

        const response = await fetch('/api/pos/checkout', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': token || '',
            },
            body: JSON.stringify({
                items: itemsPayload,
                bayar: payAmount.value,
            }),
        });

        const data = await response.json();

        if (response.ok && data.success) {
            activeReceipt.value = data.receipt;
            showReceiptModal.value = true;
            clearCart();
        } else {
            alert(data.message || 'Checkout gagal.');
        }
    } catch (err) {
        console.error('Checkout gagal:', err);
        alert('Terjadi kesalahan koneksi saat memproses checkout.');
    } finally {
        isVoiceAnalyzing.value = false;
    }
};

const printReceipt = () => {
    const printContent = document.getElementById('receipt-print-area-dashboard');
    if (!printContent) return;

    const printWindow = window.open('', '_blank');
    if (printWindow) {
        printWindow.document.write(`
            <html>
                <head>
                    <title>Cetak Struk</title>
                    <style>
                        body {
                            font-family: 'Courier New', Courier, monospace;
                            padding: 10px;
                            max-width: 280px;
                            margin: 0 auto;
                            color: #000;
                            font-size: 10px;
                            line-height: 1.4;
                        }
                        .text-center { text-align: center; }
                        .text-right { text-align: right; }
                        .font-bold { font-weight: bold; }
                        .uppercase { text-transform: uppercase; }
                        .tracking-wide { letter-spacing: 0.05em; }
                        .mt-0.5 { margin-top: 2px; }
                        .mt-1 { margin-top: 4px; }
                        .mt-1.5 { margin-top: 6px; }
                        .mt-3 { margin-top: 12px; }
                        .my-2 { margin-top: 8px; margin-bottom: 8px; }
                        .pb-1 { padding-bottom: 4px; }
                        .pt-1 { padding-top: 4px; }
                        .flex { display: flex; }
                        .justify-between { justify-content: space-between; }
                        .flex-col { flex-direction: column; }
                        .gap-1 { gap: 4px; }
                        .gap-1.5 { gap: 6px; }
                        .w-\\[90px\\\] { width: 90px; }
                        .inline-block { display: inline-block; }
                        .border-b { border-bottom: 1px dashed #000; }
                        .text-xs { font-size: 11px; }
                        .text-\\[9px\\\] { font-size: 9px; }
                        .text-\\[8px\\\] { font-size: 8px; }
                        .text-black { color: #000; }
                        .text-neutral-600 { color: #555; }
                    </style>
                </head>
                <body onload="window.print(); window.close();">
                    ${printContent.innerHTML}
                </body>
            </html>
        `);
        printWindow.document.close();
    }
};
</script>

<template>
    <Head title="Dashboard" />

    <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
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

        <!-- Voice, Text, & Manual POS Transaction Panel -->
        <div class="relative flex flex-col rounded-xl border border-sidebar-border bg-card p-6 shadow-sm">
            <div class="flex items-center gap-2.5 pb-4 border-b">
                <div class="rounded-lg bg-orange-500/15 p-2 text-orange-600">
                    <Mic class="h-5 w-5 animate-pulse" />
                </div>
                <div>
                    <h3 class="text-sm font-extrabold text-foreground">Transaksi Cepat via Suara, Teks, & Pencarian Manual AI</h3>
                    <p class="text-xs text-muted-foreground">Gunakan input suara, ketik perintah teks, atau cari produk secara manual untuk memproses transaksi belanja secara cepat.</p>
                </div>
            </div>

            <div class="mt-6 grid gap-6 lg:grid-cols-12">
                <!-- Left Column: Tabs for Voice/Text vs Manual Search -->
                <div class="flex flex-col gap-4 lg:col-span-5">
                    <!-- Navigation Tabs -->
                    <div class="grid grid-cols-2 gap-1 rounded-xl bg-muted p-1">
                        <button
                            @click="activeTabInput = 'voice'"
                            :class="[
                                'rounded-lg py-1.5 text-xs font-bold transition-all',
                                activeTabInput === 'voice'
                                    ? 'bg-background text-foreground shadow-sm'
                                    : 'text-muted-foreground hover:bg-background/40'
                            ]"
                        >
                            Input Suara / Teks AI
                        </button>
                        <button
                            @click="activeTabInput = 'manual'"
                            :class="[
                                'rounded-lg py-1.5 text-xs font-bold transition-all',
                                activeTabInput === 'manual'
                                    ? 'bg-background text-foreground shadow-sm'
                                    : 'text-muted-foreground hover:bg-background/40'
                            ]"
                        >
                            Cari Manual
                        </button>
                    </div>

                    <!-- TAB 1: VOICE / TEXT INPUT -->
                    <div v-if="activeTabInput === 'voice'" class="flex flex-col gap-4">
                        <div class="flex flex-col items-center justify-center py-6 border rounded-xl bg-muted/10 relative overflow-hidden">
                            <!-- Wave Animation -->
                            <div v-if="isListening" class="absolute inset-0 flex items-center justify-center gap-1 pointer-events-none opacity-20">
                                <div v-for="i in 10" :key="i" class="w-1.5 bg-orange-500 rounded-full animate-bounce" :style="{ height: Math.floor(Math.random() * 40 + 10) + 'px', animationDelay: (i * 0.1) + 's' }"></div>
                            </div>

                            <button
                                @click="toggleListening"
                                :class="[
                                    'flex h-20 w-20 items-center justify-center rounded-full shadow-lg transition-all active:scale-95 focus:outline-none z-10',
                                    isListening 
                                        ? 'bg-red-500 text-white animate-pulse ring-8 ring-red-500/20' 
                                        : 'bg-orange-500 text-white hover:bg-orange-600 ring-8 ring-orange-500/10'
                                ]"
                            >
                                <Mic v-if="!isListening" class="h-8 w-8" />
                                <MicOff v-else class="h-8 w-8 animate-bounce" />
                            </button>

                            <span class="mt-4 text-xs font-bold text-foreground z-10">
                                {{ isListening ? 'Mendengarkan... (Silakan Bicara)' : 'Ketuk & Mulai Bicara' }}
                            </span>
                            <span class="mt-1 text-[11px] text-muted-foreground z-10 text-center px-4">
                                Contoh: "beli pop mie cup besar tiga sama sedap soto dua renteng"
                            </span>
                        </div>

                        <div class="flex flex-col gap-1.5">
                            <label class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider">Perintah Suara / Teks Manual</label>
                            <textarea
                                v-model="voiceText"
                                placeholder="Hasil suara atau tulis perintah secara manual di sini..."
                                class="w-full h-28 p-3.5 text-xs font-medium border rounded-lg bg-background text-foreground focus:ring-1 focus:ring-orange-500 focus:outline-none resize-none"
                            ></textarea>
                        </div>

                        <div v-if="voiceError" class="flex items-center gap-2 rounded-lg border border-red-500/20 bg-red-500/5 p-3 text-xs text-red-600 font-medium">
                            <AlertCircle class="h-4 w-4 shrink-0" />
                            <span>{{ voiceError }}</span>
                        </div>

                        <button
                            @click="analyzeVoiceText"
                            :disabled="!voiceText.trim() || isVoiceAnalyzing"
                            class="flex h-10 w-full items-center justify-center gap-2 rounded-lg bg-orange-600 text-xs font-bold text-white shadow-md shadow-orange-600/15 hover:bg-orange-700 disabled:opacity-50 active:scale-[0.98] transition-all"
                        >
                            <Sparkles v-if="!isVoiceAnalyzing" class="h-4 w-4" />
                            <span v-else class="h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent"></span>
                            <span>{{ isVoiceAnalyzing ? 'Menganalisis Perintah...' : 'Analisis & Masukkan Produk' }}</span>
                        </button>
                    </div>

                    <!-- TAB 2: MANUAL SEARCH -->
                    <div v-else class="flex flex-col gap-3">
                        <div class="relative">
                            <Search class="absolute top-2.5 left-3 h-4 w-4 text-muted-foreground" />
                            <Input
                                type="text"
                                placeholder="Cari barcode / nama produk..."
                                v-model="manualSearchQuery"
                                class="h-9 pl-9 text-xs focus-visible:ring-orange-500"
                            />
                        </div>

                        <!-- Manual Product Cards Grid -->
                        <div class="flex-1 min-h-[300px] max-h-[360px] overflow-y-auto border rounded-xl bg-muted/5 p-3 flex flex-col gap-2">
                            <div v-if="isSearchingManual" class="flex flex-col items-center justify-center h-full text-center py-10">
                                <span class="h-6 w-6 animate-spin rounded-full border-2 border-orange-500 border-t-transparent"></span>
                                <span class="text-xs text-muted-foreground mt-2">Mencari produk...</span>
                            </div>

                            <div v-else-if="manualProducts.length === 0" class="flex flex-col items-center justify-center h-full text-center py-10">
                                <ShoppingCart class="h-8 w-8 text-muted-foreground/30 mb-2" />
                                <span class="text-xs text-muted-foreground font-semibold">Produk tidak ditemukan</span>
                            </div>

                            <div
                                v-else
                                v-for="prod in manualProducts"
                                :key="prod.id"
                                @click="addManualProductToCart(prod)"
                                class="flex items-center justify-between p-2.5 border rounded-lg bg-background hover:border-orange-500 cursor-pointer active:scale-[0.99] transition-all"
                            >
                                <div class="flex-1 min-w-0 pr-2">
                                    <h4 class="text-xs font-bold text-foreground truncate">{{ prod.nama }}</h4>
                                    <p class="text-[9px] text-muted-foreground mt-0.5">{{ prod.kode }}</p>
                                </div>
                                <div class="text-right shrink-0">
                                    <span class="text-xs font-extrabold text-foreground block">{{ formatRupiah(prod.harga_jual) }}</span>
                                    <span class="text-[9px] text-muted-foreground mt-0.5 block">Stok: {{ prod.stok }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Cart, Review, & Checkout -->
                <div class="flex flex-col gap-4 lg:col-span-7 border-t lg:border-t-0 lg:border-l lg:pl-6 pt-6 lg:pt-0">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-muted-foreground uppercase tracking-wider">Keranjang Belanja ({{ cart.length }} item)</span>
                        <button 
                            v-if="cart.length > 0" 
                            @click="clearCart" 
                            class="text-xs font-bold text-red-500 hover:text-red-700 transition-colors flex items-center gap-1"
                        >
                            <Trash2 class="h-3.5 w-3.5" /> Bersihkan
                        </button>
                    </div>

                    <!-- Cart Items List -->
                    <div class="flex-1 min-h-[180px] max-h-[300px] overflow-y-auto border rounded-xl bg-muted/5 p-4 flex flex-col gap-3">
                        <div v-if="cart.length === 0" class="flex flex-col items-center justify-center h-full text-center py-8">
                            <ShoppingCart class="h-10 w-10 text-muted-foreground/35 mb-2" />
                            <span class="text-xs font-semibold text-muted-foreground">Keranjang masih kosong</span>
                            <p class="text-[10px] text-muted-foreground/80 mt-1 max-w-[200px]">Silakan masukkan perintah suara/teks di kolom kiri atau cari produk secara manual.</p>
                        </div>
                        
                        <div 
                            v-else 
                            v-for="item in cart" 
                            :key="item.product.id"
                            class="flex items-center justify-between gap-4 border-b border-border pb-3 last:border-0 last:pb-0"
                        >
                            <div class="flex-1">
                                <h4 class="text-xs font-bold text-foreground">{{ item.product.nama }}</h4>
                                <span class="text-[10px] text-muted-foreground">Harga: {{ formatRupiah(item.product.harga_jual) }} | Stok: {{ item.product.stok }}</span>
                            </div>
                            
                            <div class="flex items-center gap-1 rounded-lg border bg-background p-1">
                                <button @click="updateQty(item.product.id, -1)" class="rounded p-1 text-muted-foreground hover:bg-muted">
                                    <Minus class="h-3 w-3" />
                                </button>
                                <span class="w-6 text-center text-xs font-bold text-foreground">{{ item.qty }}</span>
                                <button @click="updateQty(item.product.id, 1)" class="rounded p-1 text-muted-foreground hover:bg-muted">
                                    <Plus class="h-3 w-3" />
                                </button>
                            </div>

                            <div class="min-w-[80px] text-right text-xs font-bold text-foreground">
                                {{ formatRupiah(item.product.harga_jual * item.qty) }}
                            </div>
                        </div>
                    </div>

                    <!-- Calculation & Payment -->
                    <div v-if="cart.length > 0" class="flex flex-col gap-4 border-t pt-4">
                        <div class="flex items-center justify-between text-xs font-bold">
                            <span>TOTAL BELANJA</span>
                            <span class="text-sm font-extrabold text-orange-600">{{ formatRupiah(cartTotal) }}</span>
                        </div>

                        <!-- Payment -->
                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="flex flex-col gap-1.5">
                                <label class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider">Nominal Bayar Cash</label>
                                <div class="relative">
                                    <span class="absolute top-2 left-3 text-xs font-bold text-muted-foreground">Rp</span>
                                    <Input
                                        type="number"
                                        v-model.number="payAmount"
                                        placeholder="Masukkan jumlah bayar..."
                                        class="h-9 pl-8 text-xs font-bold focus-visible:ring-orange-500"
                                    />
                                </div>
                            </div>

                            <div class="flex flex-col justify-end">
                                <div class="flex flex-col gap-1">
                                    <button
                                        @click="setQuickPay(cartTotal)"
                                        class="w-full h-9 flex items-center justify-center rounded-lg border border-orange-500/30 bg-orange-500/5 hover:bg-orange-500/10 text-xs font-bold text-orange-600 active:scale-[0.98] transition-all"
                                    >
                                        Uang Pas ({{ formatRupiah(cartTotal) }})
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Quick Nominal suggestions -->
                        <div class="grid grid-cols-3 gap-2">
                            <button
                                v-for="amt in [20000, 50000, 100000]"
                                :key="amt"
                                v-show="amt > cartTotal"
                                @click="setQuickPay(amt)"
                                class="h-8 rounded-lg border bg-background text-[10px] font-bold hover:border-orange-500 active:scale-[0.98] transition-all"
                            >
                                {{ formatRupiah(amt) }}
                            </button>
                        </div>

                        <!-- Kembalian -->
                        <div v-if="payAmount !== null && payAmount >= cartTotal" class="flex justify-between rounded-lg border border-orange-500/15 bg-orange-500/5 p-3 text-xs font-bold text-orange-600">
                            <span>KEMBALIAN</span>
                            <span>{{ formatRupiah(changeAmount) }}</span>
                        </div>

                        <!-- Checkout Button -->
                        <button
                            @click="checkoutTransaction"
                            :disabled="payAmount === null || payAmount < cartTotal || isVoiceAnalyzing"
                            class="w-full h-10 flex items-center justify-center rounded-lg bg-orange-600 text-xs font-bold text-white shadow-lg shadow-orange-600/15 hover:bg-orange-700 disabled:opacity-50 active:scale-[0.98] transition-all"
                        >
                            <span>PROSES TRANSAKSI</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- DIALOG RECEIPT PRINT POPUP -->
    <div
        v-if="showReceiptModal && activeReceipt"
        class="fixed inset-0 z-50 flex animate-in items-center justify-center bg-black/60 p-3 fade-in"
    >
        <div
            class="flex max-h-[85%] w-full max-w-[320px] animate-in flex-col rounded-xl bg-card shadow-lg duration-150 zoom-in-95"
        >
            <div
                class="flex shrink-0 items-center justify-between border-b bg-muted/20 p-3"
            >
                <span class="text-xs font-bold">Transaksi Sukses</span>
                <button
                    @click="showReceiptModal = false"
                    class="rounded-full p-1 text-muted-foreground hover:bg-muted"
                >
                    <X class="h-4.5 w-4.5" />
                </button>
            </div>

            <!-- Print Container -->
            <div
                class="overflow-y-auto bg-white p-4 text-black"
                id="receipt-print-area-dashboard"
            >
                <div class="font-mono text-[10px]">
                    <!-- Header Info -->
                    <div
                        class="text-center text-xs font-bold tracking-wide uppercase"
                    >
                        <div>Agen Sosis</div>
                        <div class="mt-0.5">Lancar Manunggal</div>
                    </div>
                    <div
                        class="mt-1.5 text-center text-[9px] leading-tight"
                    >
                        <p>Jl. Sosis No. 1, Lancar Jaya</p>
                        <p class="mt-0.5">Telp: 0812-3456-7890</p>
                    </div>

                    <div
                        class="my-2 border-b border-dashed border-black"
                    ></div>

                    <!-- Meta Info -->
                    <div class="flex flex-col gap-0.5 text-[9px]">
                        <div class="flex justify-between">
                            <span>KODE:</span>
                            <span class="font-bold">{{ activeReceipt.kode }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span>TANGGAL:</span>
                            <span>{{ formatDate(activeReceipt.tanggaltransaksi) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span>KASIR:</span>
                            <span>Admin Dashboard</span>
                        </div>
                    </div>

                    <div
                        class="my-2 border-b border-dashed border-black"
                    ></div>

                    <!-- Products List -->
                    <div class="flex flex-col gap-1.5">
                        <div
                            v-for="det in activeReceipt.details"
                            :key="det.id"
                            class="flex flex-col"
                        >
                            <span class="font-bold">{{ det.produk.nama }}</span>
                            <div class="flex justify-between text-[9px] mt-0.5">
                                <span>{{ formatNumber(det.harga) }} x {{ det.jumlah }}</span>
                                <span>{{ formatNumber(det.subtotal) }}</span>
                            </div>
                        </div>
                    </div>

                    <div
                        class="my-2 border-b border-dashed border-black"
                    ></div>

                    <!-- Calculations / Footer Info -->
                    <div
                        class="flex justify-between text-[9px] font-bold"
                    >
                        <span>TOTAL</span>
                        <span>{{ formatNumber(activeReceipt.total) }}</span>
                    </div>
                    <div class="mt-1 flex justify-between text-[9px]">
                        <span>BAYAR CASH</span>
                        <span>{{ formatNumber(activeReceipt.bayar) }}</span>
                    </div>
                    <div class="mt-0.5 flex justify-between text-[9px]">
                        <span>KEMBALIAN</span>
                        <span>{{ formatNumber(activeReceipt.kembalian) }}</span>
                    </div>

                    <div
                        class="my-2 border-b border-dashed border-black"
                    ></div>

                    <div
                        class="mt-3 text-center text-[8px] leading-tight text-neutral-600"
                    >
                        <p class="text-[9px] font-bold text-black">
                            --- TERIMA KASIH ---
                        </p>
                        <p class="mt-0.5">
                            Barang yang sudah dibeli tidak dapat ditukar/dikembalikan.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Print Actions -->
            <div class="flex shrink-0 gap-2 border-t bg-muted/20 p-3">
                <Button
                    @click="showReceiptModal = false"
                    variant="outline"
                    class="h-8 flex-1 text-xs"
                >
                    Tutup
                </Button>
                <Button
                    @click="printReceipt"
                    class="flex h-8 flex-1 items-center justify-center gap-1 bg-primary text-xs text-primary-foreground"
                >
                    <Printer class="h-3.5 w-3.5" /> Cetak
                </Button>
            </div>
        </div>
    </div>
</template>
