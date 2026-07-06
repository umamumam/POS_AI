<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
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
    Search,
    ListFilter
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
                title: 'POS AI',
                href: '/transaksi-ai',
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

// Today's Transactions State
const showTransactionsModal = ref(false);
const todayTransactions = ref<Receipt[]>([]);
const isLoadingTransactions = ref(false);
const transactionSearchQuery = ref('');

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
    manualSearchQuery.value = '';
};

onMounted(() => {
    fetchManualProducts();
});

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

const removeProductFromCart = (productId: number) => {
    const index = cart.value.findIndex((item) => item.product.id === productId);
    if (index > -1) {
        cart.value.splice(index, 1);
    }
};

const clearCart = () => {
    cart.value = [];
    payAmount.value = null;
};

const setQuickPay = (amount: number) => {
    payAmount.value = amount;
};

// Fetch today's transactions list
const fetchTodayTransactions = async () => {
    isLoadingTransactions.value = true;
    try {
        const response = await fetch('/api/transactions/today');
        const data = await response.json();
        todayTransactions.value = data;
    } catch (err) {
        console.error('Gagal mengambil data transaksi hari ini:', err);
    } finally {
        isLoadingTransactions.value = false;
    }
};

const filteredTransactions = computed(() => {
    if (!transactionSearchQuery.value.trim()) return todayTransactions.value;
    return todayTransactions.value.filter((t) => 
        t.kode.toLowerCase().includes(transactionSearchQuery.value.toLowerCase())
    );
});

const cameFromList = ref(false);

const openTransactionsModal = () => {
    cameFromList.value = false;
    showTransactionsModal.value = true;
    fetchTodayTransactions();
};

const printTransactionReceipt = (receipt: Receipt) => {
    activeReceipt.value = receipt;
    setTimeout(() => {
        printReceipt();
    }, 150);
};

const viewReceiptDetail = (receipt: Receipt) => {
    cameFromList.value = true;
    showTransactionsModal.value = false;
    activeReceipt.value = receipt;
    showReceiptModal.value = true;
};

const closeReceiptModal = () => {
    showReceiptModal.value = false;
    if (cameFromList.value) {
        showTransactionsModal.value = true;
        cameFromList.value = false;
    }
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
                total: cartTotal.value,
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
    const printContent = document.getElementById('receipt-print-area-transaksi-ai-always');
    if (!printContent) return;

    const printWindow = window.open('', '_blank');
    if (printWindow) {
        printWindow.document.write(`
            <html>
                <head>
                    <title>Nota Transaksi #${activeReceipt.value?.kode || ''}</title>
                    <style>
                        @page {
                            margin: 0;
                        }
                        body {
                            font-family: 'Plus Jakarta Sans', DejaVu Sans, sans-serif;
                            font-size: 13px;
                            line-height: 1.2;
                            padding: 20px;
                            width: 200px;
                            color: #000;
                            margin: 0;
                        }
                        .header, .footer {
                            text-align: center;
                            margin-bottom: 5px;
                        }
                        .details, .summary {
                            width: 100%;
                            border-collapse: collapse;
                            margin-bottom: 5px;
                        }
                        .details td, .details th {
                            padding: 1px 0;
                        }
                        .text-left {
                            text-align: left;
                        }
                        .text-right {
                            text-align: right;
                            white-space: nowrap;
                        }
                        .separator {
                            border-bottom: 1px dashed #000;
                            margin: 5px 0;
                        }
                        .summary td:last-child, .summary th:last-child {
                            text-align: right;
                            padding-right: 10px;
                            white-space: nowrap;
                        }
                        .total-row {
                            font-weight: bold;
                        }
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
    <Head title="POS AI" />

    <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
        <!-- Voice, Text, & Manual POS Transaction Panel -->
        <div class="relative flex flex-col rounded-xl border border-sidebar-border bg-card p-6 shadow-sm">
            <div class="flex items-center justify-between pb-4 border-b flex-wrap gap-3">
                <div class="flex items-center gap-2.5">
                    <div class="rounded-lg bg-orange-500/15 p-2 text-orange-600">
                        <Mic class="h-5 w-5 animate-pulse" />
                    </div>
                    <div>
                        <h3 class="text-sm font-extrabold text-foreground">Transaksi Cepat via Suara, Teks, & Pencarian Manual AI</h3>
                        <p class="text-xs text-muted-foreground">Gunakan input suara, ketik perintah teks, atau cari produk secara manual untuk memproses transaksi belanja secara cepat.</p>
                    </div>
                </div>
                
                <!-- Tombol Daftar Transaksi Hari Ini -->
                <button
                    @click="openTransactionsModal"
                    class="flex items-center gap-1.5 rounded-lg border border-orange-500/30 bg-orange-500/5 px-3 py-1.5 text-xs font-bold text-orange-600 shadow-sm transition-all hover:bg-orange-500/10 active:scale-95"
                >
                    <ListFilter class="h-4 w-4" />
                    <span>Daftar Transaksi</span>
                </button>
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
                            
                            <div class="flex items-center gap-3">
                                <!-- Quantity Controls -->
                                <div class="flex items-center gap-1 rounded-lg border bg-background p-1">
                                    <button @click="updateQty(item.product.id, -1)" class="rounded p-1 text-muted-foreground hover:bg-muted">
                                        <Minus class="h-3 w-3" />
                                    </button>
                                    <span class="w-6 text-center text-xs font-bold text-foreground">{{ item.qty }}</span>
                                    <button @click="updateQty(item.product.id, 1)" class="rounded p-1 text-muted-foreground hover:bg-muted">
                                        <Plus class="h-3 w-3" />
                                    </button>
                                </div>

                                <!-- Tombol Hapus Explicit -->
                                <button 
                                    @click="removeProductFromCart(item.product.id)" 
                                    class="rounded-lg p-1.5 text-red-500 hover:bg-red-50 hover:text-red-700 transition-colors"
                                    title="Hapus Produk"
                                >
                                    <Trash2 class="h-4.5 w-4.5" />
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

    <!-- DIALOG RECEIPT PRINT POPUP (Nota Format matches nota.blade.php exactly) -->
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
                <span class="text-xs font-bold text-foreground">Struk Nota Belanja</span>
                <button
                    @click="closeReceiptModal"
                    class="rounded-full p-1 text-muted-foreground hover:bg-muted"
                >
                    <X class="h-4.5 w-4.5" />
                </button>
            </div>

            <!-- Print Container -->
            <div
                class="overflow-y-auto bg-white p-4 text-black flex justify-center"
                id="receipt-print-area-transaksi-ai"
            >
                <div style="font-family: 'Plus Jakarta Sans', DejaVu Sans, sans-serif; font-size: 13px; line-height: 1.2; width: 200px;">
                    <!-- Header Info -->
                    <div style="text-align: center; margin-bottom: 5px;">
                        <h3 style="margin: 0 0 5px 0; font-size: 14px; font-weight: bold;">Agen Sosis <br> Lancar Manunggal</h3>
                        <p style="margin: 0; font-size: 11px;">Jl. Raya Tayu-Jepara Km 7 <br> depan Kantor Pos Ngablak</p>
                        <p style="margin: 0; font-size: 11px;">HP: 085201454015</p>
                    </div>

                    <div style="border-bottom: 1px dashed #000; margin: 5px 0;"></div>

                    <!-- Meta Info -->
                    <table style="width: 100%; border-collapse: collapse; margin-bottom: 5px; font-size: 12px;">
                        <tr>
                            <td style="text-align: left; width: 90px; padding: 1px 0;">No Transaksi</td>
                            <td style="text-align: left; padding: 1px 0;">: {{ activeReceipt.kode }}</td>
                        </tr>
                        <tr>
                            <td style="text-align: left; padding: 1px 0;">Tanggal</td>
                            <td style="text-align: left; padding: 1px 0;">: {{ formatDate(activeReceipt.tanggaltransaksi) }}</td>
                        </tr>
                    </table>

                    <div style="border-bottom: 1px dashed #000; margin: 5px 0;"></div>

                    <!-- Products List Table matching nota.blade.php -->
                    <table style="width: 100%; border-collapse: collapse; margin-bottom: 5px; font-size: 12px;">
                        <thead>
                            <tr>
                                <th style="text-align: left; padding: 1px 0; font-weight: bold;">Produk</th>
                                <th style="text-align: right; padding: 1px 0; font-weight: bold; white-space: nowrap;">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template v-for="det in activeReceipt.details" :key="det.id">
                                <tr>
                                    <td style="text-align: left; vertical-align: top; padding: 1px 0;">
                                        {{ det.produk.nama }}
                                    </td>
                                    <td rowspan="2" style="text-align: right; vertical-align: bottom; padding: 1px 0; white-space: nowrap;">
                                        {{ formatNumber(det.subtotal) }}
                                    </td>
                                </tr>
                                <tr>
                                    <td style="text-align: left; font-size: 11px; padding-bottom: 5px; padding-top: 1px;">
                                        {{ formatNumber(det.harga) }} x {{ det.jumlah }}
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>

                    <div style="border-bottom: 1px dashed #000; margin: 5px 0;"></div>

                    <!-- Summary Table -->
                    <table style="width: 100%; border-collapse: collapse; margin-bottom: 5px; font-size: 12px;">
                        <tr>
                            <td style="text-align: left; padding: 1px 0;">Total</td>
                            <th style="text-align: right; padding: 1px 0; font-weight: bold;">: Rp {{ formatNumber(activeReceipt.total) }}</th>
                        </tr>
                        <tr>
                            <td style="text-align: left; padding: 1px 0;">Bayar</td>
                            <th style="text-align: right; padding: 1px 0; font-weight: bold;">: Rp {{ formatNumber(activeReceipt.bayar) }}</th>
                        </tr>
                        <tr>
                            <td style="text-align: left; padding: 1px 0;">Kembalian</td>
                            <th style="text-align: right; padding: 1px 0; font-weight: bold;">: Rp {{ formatNumber(activeReceipt.kembalian) }}</th>
                        </tr>
                    </table>

                    <div style="border-bottom: 1px dashed #000; margin: 5px 0;"></div>

                    <!-- Footer -->
                    <div style="text-align: center; margin-top: 5px; font-size: 11px;">
                        <p style="margin: 0; line-height: 1.3;">Terima kasih telah berbelanja! <br>
                            Barang yang sudah dibeli <br>
                            tidak dapat dikembalikan.</p>
                    </div>
                </div>
            </div>

            <!-- Print Actions -->
            <div class="flex shrink-0 gap-2 border-t bg-muted/20 p-3">
                <Button
                    @click="closeReceiptModal"
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

    <!-- DIALOG DAFTAR TRANSAKSI HARI INI MODAL -->
    <div
        v-if="showTransactionsModal"
        class="fixed inset-0 z-50 flex animate-in items-center justify-center bg-black/60 p-4 fade-in"
    >
        <div
            class="flex max-h-[90%] w-full max-w-[800px] animate-in flex-col rounded-xl bg-card shadow-xl duration-150 zoom-in-95"
        >
            <!-- Header -->
            <div class="flex shrink-0 items-center justify-between border-b bg-muted/20 px-4 py-3">
                <div class="flex items-center gap-2">
                    <ListFilter class="h-5 w-5 text-orange-600" />
                    <span class="text-sm font-extrabold text-foreground">Daftar Transaksi Hari Ini</span>
                </div>
                <button
                    @click="showTransactionsModal = false"
                    class="rounded-full p-1 text-muted-foreground hover:bg-muted"
                >
                    <X class="h-4.5 w-4.5" />
                </button>
            </div>

            <!-- Body -->
            <div class="flex-1 overflow-y-auto p-4 flex flex-col gap-4">
                <!-- Search bar & entries info -->
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-3 bg-muted/10 p-3 border rounded-xl">
                    <span class="text-xs font-bold text-muted-foreground uppercase tracking-wider">
                        {{ filteredTransactions.length }} Transaksi Ditemukan
                    </span>
                    <div class="relative w-full md:w-64">
                        <Search class="absolute top-2.5 left-3 h-4 w-4 text-muted-foreground" />
                        <Input
                            type="text"
                            placeholder="Cari kode transaksi..."
                            v-model="transactionSearchQuery"
                            class="h-9 pl-9 text-xs focus-visible:ring-orange-500"
                        />
                    </div>
                </div>

                <!-- Transactions Table -->
                <div class="overflow-x-auto border rounded-xl bg-background">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead>
                            <tr class="bg-muted/30 border-b text-muted-foreground font-bold uppercase tracking-wider text-[10px]">
                                <th class="p-3">NO</th>
                                <th class="p-3">KODE TRANSAKSI</th>
                                <th class="p-3">TANGGAL</th>
                                <th class="p-3">TOTAL</th>
                                <th class="p-3">BAYAR</th>
                                <th class="p-3">KEMBALIAN</th>
                                <th class="p-3 text-center">ACTIONS</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="isLoadingTransactions" class="border-b last:border-0">
                                <td colspan="7" class="p-8 text-center text-muted-foreground">
                                    <span class="h-6 w-6 inline-block animate-spin rounded-full border-2 border-orange-500 border-t-transparent"></span>
                                    <p class="mt-2 text-xs">Memuat data transaksi...</p>
                                </td>
                            </tr>
                            <tr v-else-if="filteredTransactions.length === 0" class="border-b last:border-0">
                                <td colspan="7" class="p-8 text-center text-muted-foreground font-semibold">
                                    Tidak ada transaksi hari ini yang cocok.
                                </td>
                            </tr>
                            <tr 
                                v-else
                                v-for="(trx, idx) in filteredTransactions" 
                                :key="trx.id"
                                class="border-b last:border-0 hover:bg-muted/5 transition-colors font-medium text-foreground"
                            >
                                <td class="p-3">{{ idx + 1 }}</td>
                                <td class="p-3 font-bold text-orange-600">{{ trx.kode }}</td>
                                <td class="p-3">{{ formatDate(trx.tanggaltransaksi) }}</td>
                                <td class="p-3 font-extrabold">{{ formatRupiah(trx.total) }}</td>
                                <td class="p-3">{{ formatRupiah(trx.bayar) }}</td>
                                <td class="p-3 text-muted-foreground">{{ formatRupiah(trx.kembalian) }}</td>
                                <td class="p-3">
                                    <div class="flex items-center justify-center gap-1.5">
                                        <!-- Detail Button -->
                                        <button
                                            @click="viewReceiptDetail(trx)"
                                            class="flex h-7 w-7 items-center justify-center rounded-lg bg-blue-500 text-white hover:bg-blue-600 transition-colors shadow-sm"
                                            title="Lihat Detail Nota"
                                        >
                                            <Search class="h-3.5 w-3.5" />
                                        </button>
                                        <!-- Print Button -->
                                        <button
                                            @click="printTransactionReceipt(trx)"
                                            class="flex h-7 w-7 items-center justify-center rounded-lg bg-emerald-500 text-white hover:bg-emerald-600 transition-colors shadow-sm"
                                            title="Cetak Ulang Nota"
                                        >
                                            <Printer class="h-3.5 w-3.5" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Footer -->
            <div class="flex shrink-0 justify-end border-t bg-muted/20 p-3">
                <Button
                    @click="showTransactionsModal = false"
                    class="h-8 text-xs font-bold"
                >
                    Tutup
                </Button>
            </div>
        </div>
    </div>

    <!-- Hidden Print Target container that is ALWAYS in the DOM -->
    <div style="display: none;">
        <div id="receipt-print-area-transaksi-ai-always" v-if="activeReceipt">
            <div style="font-family: 'Plus Jakarta Sans', DejaVu Sans, sans-serif; font-size: 13px; line-height: 1.2; width: 200px;">
                <!-- Header Info -->
                <div style="text-align: center; margin-bottom: 5px;">
                    <h3 style="margin: 0 0 5px 0; font-size: 14px; font-weight: bold;">Agen Sosis <br> Lancar Manunggal</h3>
                    <p style="margin: 0; font-size: 11px;">Jl. Raya Tayu-Jepara Km 7 <br> depan Kantor Pos Ngablak</p>
                    <p style="margin: 0; font-size: 11px;">HP: 085201454015</p>
                </div>

                <div style="border-bottom: 1px dashed #000; margin: 5px 0;"></div>

                <!-- Meta Info -->
                <table style="width: 100%; border-collapse: collapse; margin-bottom: 5px; font-size: 12px;">
                    <tr>
                        <td style="text-align: left; width: 90px; padding: 1px 0;">No Transaksi</td>
                        <td style="text-align: left; padding: 1px 0;">: {{ activeReceipt.kode }}</td>
                    </tr>
                    <tr>
                        <td style="text-align: left; padding: 1px 0;">Tanggal</td>
                        <td style="text-align: left; padding: 1px 0;">: {{ formatDate(activeReceipt.tanggaltransaksi) }}</td>
                    </tr>
                </table>

                <div style="border-bottom: 1px dashed #000; margin: 5px 0;"></div>

                <!-- Products List Table matching nota.blade.php -->
                <table style="width: 100%; border-collapse: collapse; margin-bottom: 5px; font-size: 12px;">
                    <thead>
                        <tr>
                            <th style="text-align: left; padding: 1px 0; font-weight: bold;">Produk</th>
                            <th style="text-align: right; padding: 1px 0; font-weight: bold; white-space: nowrap;">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template v-for="det in activeReceipt.details" :key="det.id">
                            <tr>
                                <td style="text-align: left; vertical-align: top; padding: 1px 0;">
                                    {{ det.produk.nama }}
                                </td>
                                <td rowspan="2" style="text-align: right; vertical-align: bottom; padding: 1px 0; white-space: nowrap;">
                                    {{ formatNumber(det.subtotal) }}
                                </td>
                            </tr>
                            <tr>
                                <td style="text-align: left; font-size: 11px; padding-bottom: 5px; padding-top: 1px;">
                                    {{ formatNumber(det.harga) }} x {{ det.jumlah }}
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>

                <div style="border-bottom: 1px dashed #000; margin: 5px 0;"></div>

                <!-- Summary Table -->
                <table style="width: 100%; border-collapse: collapse; margin-bottom: 5px; font-size: 12px;">
                    <tr>
                        <td style="text-align: left; padding: 1px 0;">Total</td>
                        <th style="text-align: right; padding: 1px 0; font-weight: bold;">: Rp {{ formatNumber(activeReceipt.total) }}</th>
                    </tr>
                    <tr>
                        <td style="text-align: left; padding: 1px 0;">Bayar</td>
                        <th style="text-align: right; padding: 1px 0; font-weight: bold;">: Rp {{ formatNumber(activeReceipt.bayar) }}</th>
                    </tr>
                    <tr>
                        <td style="text-align: left; padding: 1px 0;">Kembalian</td>
                        <th style="text-align: right; padding: 1px 0; font-weight: bold;">: Rp {{ formatNumber(activeReceipt.kembalian) }}</th>
                    </tr>
                </table>

                <div style="border-bottom: 1px dashed #000; margin: 5px 0;"></div>

                <!-- Footer -->
                <div style="text-align: center; margin-top: 5px; font-size: 11px;">
                    <p style="margin: 0; line-height: 1.3;">Terima kasih telah berbelanja! <br>
                        Barang yang sudah dibeli <br>
                        tidak dapat dikembalikan.</p>
                </div>
            </div>
        </div>
    </div>
</template>
