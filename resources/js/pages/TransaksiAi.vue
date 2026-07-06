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
    ListFilter,
    Edit,
} from '@lucide/vue';
import Swal from 'sweetalert2';
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
                title: 'Transaksi',
                href: '/transaksi-ai',
            },
        ],
    },
});

// CSRF Utility
const getCsrfToken = () => {
    return document
        .querySelector('meta[name="csrf-token"]')
        ?.getAttribute('content');
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
        'Jan',
        'Feb',
        'Mar',
        'Apr',
        'Mei',
        'Jun',
        'Jul',
        'Agt',
        'Sep',
        'Okt',
        'Nov',
        'Des',
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
    return cart.value.reduce(
        (total, item) => total + item.product.harga_jual * item.qty,
        0,
    );
});

const changeAmount = computed(() => {
    if (payAmount.value === null || payAmount.value < cartTotal.value) return 0;
    return payAmount.value - cartTotal.value;
});

// Voice / Text State
const activeTabInput = ref<'voice' | 'manual'>('manual');
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
    const SpeechRecognition =
        (window as any).SpeechRecognition ||
        (window as any).webkitSpeechRecognition;
    if (!SpeechRecognition) {
        voiceError.value =
            'Browser Anda tidak mendukung input suara. Silakan ketik perintah secara manual di bawah ini.';
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
        voiceText.value =
            (voiceText.value ? voiceText.value + ' ' : '') + transcript;
    };

    recognition.onerror = (event: any) => {
        console.error('Speech error:', event.error);
        if (event.error === 'not-allowed') {
            voiceError.value =
                'Izin mikrofon ditolak. Harap izinkan akses mikrofon.';
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
            voiceError.value =
                data.message ||
                'Tidak ada produk yang cocok dengan perintah teks Anda.';
        }
    } catch (err: any) {
        console.error('Voice/Text Analysis error:', err);
        voiceError.value =
            err.message || 'Gagal menganalisis perintah suara/teks.';
    } finally {
        isVoiceAnalyzing.value = false;
    }
};

const fetchManualProducts = async () => {
    isSearchingManual.value = true;
    try {
        const response = await fetch(
            `/api/products?q=${manualSearchQuery.value}`,
        );
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

// Fetch transactions list with server-side pagination and search
const isViewingAllTransactions = ref(false);
const transactionsPagination = ref<any>(null);
const currentPageTransactions = ref(1);

const fetchTodayTransactions = async (page = 1) => {
    isLoadingTransactions.value = true;
    currentPageTransactions.value = page;
    try {
        let url = isViewingAllTransactions.value
            ? `/api/transactions/today?all=true&page=${page}`
            : `/api/transactions/today?page=${page}`;

        if (transactionSearchQuery.value.trim()) {
            url += `&q=${encodeURIComponent(transactionSearchQuery.value)}`;
        }

        const response = await fetch(url);
        const data = await response.json();
        todayTransactions.value = data.data || [];
        transactionsPagination.value = data;
    } catch (err) {
        console.error('Gagal mengambil data transaksi:', err);
    } finally {
        isLoadingTransactions.value = false;
    }
};

let transactionSearchTimeout: any = null;
watch(transactionSearchQuery, () => {
    clearTimeout(transactionSearchTimeout);
    transactionSearchTimeout = setTimeout(() => {
        fetchTodayTransactions(1);
    }, 400);
});

const cameFromList = ref(false);

// Edit Transaction States
const showEditTransactionModal = ref(false);
const editingTransaction = ref<Receipt | null>(null);
const editTransactionCart = ref<
    Array<{ product: Product; qty: number; harga: number }>
>([]);
const editTransactionPay = ref<number>(0);
const editTransactionSearch = ref('');
const editTransactionSearchProducts = ref<Product[]>([]);
const isSearchingEditTrx = ref(false);

const editTransactionTotal = computed(() => {
    return editTransactionCart.value.reduce(
        (total, item) => total + item.harga * item.qty,
        0,
    );
});

const editTransactionChange = computed(() => {
    if (editTransactionPay.value < editTransactionTotal.value) return 0;
    return editTransactionPay.value - editTransactionTotal.value;
});

const openEditTransactionModal = (trx: Receipt) => {
    editingTransaction.value = trx;

    // Copy transaction details into edit transaction cart
    editTransactionCart.value = trx.details.map((detail) => ({
        product: detail.produk,
        qty: detail.jumlah,
        harga: detail.harga,
    }));

    editTransactionPay.value = trx.bayar;
    editTransactionSearch.value = '';
    editTransactionSearchProducts.value = [];

    // Close the list modal first
    showTransactionsModal.value = false;
    cameFromList.value = true;
    showEditTransactionModal.value = true;
};

// Search products to add inside edit modal
const fetchEditTrxSearchProducts = async () => {
    if (!editTransactionSearch.value.trim()) {
        editTransactionSearchProducts.value = [];
        return;
    }
    isSearchingEditTrx.value = true;
    try {
        const response = await fetch(
            `/api/products?q=${encodeURIComponent(editTransactionSearch.value)}`,
        );
        const data = await response.json();
        editTransactionSearchProducts.value = data.data || [];
    } catch (err) {
        console.error('Gagal mengambil produk pencarian edit:', err);
    } finally {
        isSearchingEditTrx.value = false;
    }
};

let editTrxSearchTimeout: any = null;
watch(editTransactionSearch, () => {
    clearTimeout(editTrxSearchTimeout);
    editTrxSearchTimeout = setTimeout(() => {
        fetchEditTrxSearchProducts();
    }, 300);
});

const addProductToEditCart = (prod: Product) => {
    const existingIndex = editTransactionCart.value.findIndex(
        (item) => item.product.id === prod.id,
    );
    if (existingIndex > -1) {
        const newQty = editTransactionCart.value[existingIndex].qty + 1;
        if (newQty <= prod.stok) {
            editTransactionCart.value[existingIndex].qty = newQty;
        } else {
            alert(`Stok untuk ${prod.nama} sudah mencapai batas maksimum.`);
        }
    } else {
        if (prod.stok > 0) {
            editTransactionCart.value.push({
                product: prod,
                qty: 1,
                harga: prod.harga_jual,
            });
        } else {
            alert(`Produk ${prod.nama} habis.`);
        }
    }
    editTransactionSearch.value = '';
    editTransactionSearchProducts.value = [];
};

const updateEditCartQty = (productId: number, change: number) => {
    const index = editTransactionCart.value.findIndex(
        (item) => item.product.id === productId,
    );
    if (index > -1) {
        const item = editTransactionCart.value[index];
        const newQty = item.qty + change;
        if (newQty <= 0) {
            editTransactionCart.value.splice(index, 1);
        } else {
            item.qty = newQty;
        }
    }
};

const removeProductFromEditCart = (productId: number) => {
    const index = editTransactionCart.value.findIndex(
        (item) => item.product.id === productId,
    );
    if (index > -1) {
        editTransactionCart.value.splice(index, 1);
    }
};

const closeEditTransactionModal = () => {
    showEditTransactionModal.value = false;
    editingTransaction.value = null;
    if (cameFromList.value) {
        showTransactionsModal.value = true;
        cameFromList.value = false;
    }
};

const submitUpdateTransaction = async () => {
    if (!editingTransaction.value || editTransactionCart.value.length === 0)
        return;

    if (editTransactionPay.value < editTransactionTotal.value) {
        alert('Jumlah pembayaran kurang dari total belanja.');
        return;
    }

    try {
        const token = getCsrfToken();
        const itemsPayload = editTransactionCart.value.map((item) => ({
            id: item.product.id,
            qty: item.qty,
            harga: item.harga,
        }));

        const response = await fetch(
            `/api/transactions/${editingTransaction.value.id}`,
            {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-XSRF-TOKEN': token || '',
                },
                body: JSON.stringify({
                    items: itemsPayload,
                    total: editTransactionTotal.value,
                    bayar: editTransactionPay.value,
                }),
            },
        );

        const data = await response.json();

        if (response.ok && data.success) {
            showEditTransactionModal.value = false;
            activeReceipt.value = data.receipt;
            showReceiptModal.value = true;

            Swal.fire({
                icon: 'success',
                title: 'Berhasil!',
                text: 'Transaksi berhasil diperbarui.',
                confirmButtonColor: '#ea580c',
                timer: 2000,
            });
        } else {
            alert(data.message || 'Gagal memperbarui transaksi.');
        }
    } catch (err) {
        console.error('Gagal memperbarui transaksi:', err);
        alert('Terjadi kesalahan saat memproses pembaruan transaksi.');
    }
};

const openTransactionsModal = (viewAll = false) => {
    isViewingAllTransactions.value = viewAll === true;
    transactionSearchQuery.value = '';
    cameFromList.value = false;
    showTransactionsModal.value = true;
    fetchTodayTransactions(1);
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
    const printContent = document.getElementById(
        'receipt-print-area-transaksi-ai-always',
    );
    if (!printContent) return;

    const printWindow = window.open('', '_blank');
    if (printWindow) {
        printWindow.document.write(`
            <html>
                <head>
                    <title>Nota Transaksi #${activeReceipt.value?.kode || ''}</title>
                    <style>
                        @page {
                            margin: 6mm;
                        }
                        body {
                            font-family: Arial, sans-serif !important;
                            font-size: 13pt !important;
                            line-height: 1.4 !important;
                            width: 52mm !important;
                            color: #000 !important;
                            margin: 0 !important;
                            padding: 0 !important;
                        }
                        * {
                            font-family: Arial, sans-serif !important;
                            box-sizing: border-box;
                        }
                        h3 {
                            font-size: 13pt !important;
                            font-weight: bold;
                            margin: 0 0 2mm 0;
                            text-align: center;
                        }
                        p { margin: 0; font-size: 10pt !important; }
                        div[style*="text-align: center"] { text-align: center; }
                        div[style*="margin-bottom"] { margin-bottom: 2mm; }
                        div[style*="text-align: center"][style*="margin-top"] {
                            text-align: center;
                            margin-top: 2mm;
                            font-size: 10pt !important;
                        }
                        div[style*="border-bottom"] {
                            border: none;
                            border-top: 1px dashed #000;
                            margin: 2mm 0;
                        }
                        table {
                            width: 100% !important;
                            border-collapse: collapse;
                            margin-bottom: 1mm;
                        }
                        td, th {
                            font-size: 13pt !important;
                            padding: 0.5mm 0 !important;
                            vertical-align: top;
                        }
                        [style*="text-align: right"] { text-align: right; white-space: nowrap; }
                        [style*="font-size: 16px"] { font-size: 13pt !important; }
                        [style*="font-size: 15px"] { font-size: 12.5pt !important; }
                        [style*="font-size: 14px"] { font-size: 11pt !important; }
                        [style*="font-size: 13px"] { font-size: 10.5pt !important; }
                        [style*="font-size: 12px"] { font-size: 10pt !important; }
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
    <Head title="Transaksi Kasir" />

    <div
        class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4 font-sans text-[#171717]"
        style="font-family: 'Poppins', sans-serif"
    >
        <!-- Header Info (Borderless & Flat layout) -->
        <div
            class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center"
        >
            <div class="flex items-center gap-2.5">
                <div class="rounded-lg bg-orange-500/10 p-2 text-orange-600">
                    <Mic class="h-5 w-5 animate-pulse" />
                </div>
                <div>
                    <h2
                        class="text-xl font-extrabold tracking-tight text-[#171717] dark:text-white"
                    >
                        Transaksi Cepat
                    </h2>
                    <p class="text-xs text-[#525252] dark:text-neutral-400">
                        Gunakan input suara, ketik perintah teks, atau cari
                        produk secara manual untuk memproses transaksi belanja
                        secara cepat.
                    </p>
                </div>
            </div>

            <!-- Action Buttons: Daftar Transaksi & Semua Transaksi -->
            <div class="flex shrink-0 flex-wrap items-center gap-2">
                <!-- Tombol Daftar Transaksi Hari Ini -->
                <button
                    @click="openTransactionsModal(false)"
                    class="flex items-center gap-1.5 rounded-lg border border-orange-500/30 bg-orange-500/5 px-3 py-1.5 text-xs font-bold text-orange-600 shadow-sm transition-all hover:bg-orange-500/10 active:scale-95"
                >
                    <ListFilter class="h-4 w-4" />
                    <span>Daftar Transaksi</span>
                </button>

                <!-- Tombol Semua Transaksi -->
                <button
                    @click="openTransactionsModal(true)"
                    class="bg-neutral-55 flex items-center gap-1.5 rounded-lg border border-neutral-200 px-3 py-1.5 text-xs font-bold text-[#171717] shadow-xs transition-all hover:bg-neutral-100 active:scale-95 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white"
                >
                    <ListFilter class="h-4 w-4 text-neutral-500" />
                    <span>Semua Transaksi</span>
                </button>
            </div>
        </div>

        <div class="mt-2 grid gap-6 lg:grid-cols-12">
            <!-- Left Column: Tabs for Voice/Text vs Manual Search -->
            <div class="flex flex-col gap-4 lg:col-span-5">
                <!-- Navigation Tabs -->
                <div class="grid grid-cols-2 gap-1 rounded-xl bg-muted p-1">
                    <button
                        @click="activeTabInput = 'manual'"
                        :class="[
                            'rounded-lg py-1.5 text-xs font-bold transition-all',
                            activeTabInput === 'manual'
                                ? 'bg-background text-foreground shadow-sm'
                                : 'text-muted-foreground hover:bg-background/40',
                        ]"
                    >
                        Cari Manual
                    </button>
                    <button
                        @click="activeTabInput = 'voice'"
                        :class="[
                            'rounded-lg py-1.5 text-xs font-bold transition-all',
                            activeTabInput === 'voice'
                                ? 'bg-background text-foreground shadow-sm'
                                : 'text-muted-foreground hover:bg-background/40',
                        ]"
                    >
                        Input Suara / Teks AI
                    </button>
                </div>

                <!-- TAB 1: MANUAL SEARCH -->
                <div
                    v-if="activeTabInput === 'manual'"
                    class="flex flex-col gap-3"
                >
                    <div class="relative">
                        <Search
                            class="absolute top-2.5 left-3 h-4 w-4 text-muted-foreground"
                        />
                        <Input
                            type="text"
                            placeholder="Cari barcode / nama produk..."
                            v-model="manualSearchQuery"
                            class="h-9 pl-9 text-xs focus-visible:ring-orange-500"
                        />
                    </div>

                    <!-- Manual Product Cards Grid -->
                    <div
                        class="flex max-h-[360px] min-h-[300px] flex-1 flex-col gap-2 overflow-y-auto rounded-xl border bg-muted/5 p-3"
                    >
                        <div
                            v-if="isSearchingManual"
                            class="flex h-full flex-col items-center justify-center py-10 text-center"
                        >
                            <span
                                class="h-6 w-6 animate-spin rounded-full border-2 border-orange-500 border-t-transparent"
                            ></span>
                            <span class="mt-2 text-xs text-muted-foreground"
                                >Mencari produk...</span
                            >
                        </div>

                        <div
                            v-else-if="manualProducts.length === 0"
                            class="flex h-full flex-col items-center justify-center py-10 text-center"
                        >
                            <ShoppingCart
                                class="mb-2 h-8 w-8 text-muted-foreground/30"
                            />
                            <span
                                class="text-xs font-semibold text-muted-foreground"
                                >Produk tidak ditemukan</span
                            >
                        </div>

                        <div
                            v-else
                            v-for="prod in manualProducts"
                            :key="prod.id"
                            @click="addManualProductToCart(prod)"
                            class="flex cursor-pointer items-center justify-between rounded-lg border bg-background p-2.5 transition-all hover:border-orange-500 active:scale-[0.99]"
                        >
                            <div class="min-w-0 flex-1 pr-2">
                                <h4
                                    class="truncate text-xs font-bold text-foreground"
                                >
                                    {{ prod.nama }}
                                </h4>
                                <p
                                    class="mt-0.5 text-[9px] text-muted-foreground"
                                >
                                    {{ prod.kode }}
                                </p>
                            </div>
                            <div class="shrink-0 text-right">
                                <span
                                    class="block text-xs font-extrabold text-foreground"
                                    >{{ formatRupiah(prod.harga_jual) }}</span
                                >
                                <span
                                    class="mt-0.5 block text-[9px] text-muted-foreground"
                                    >Stok: {{ prod.stok }}</span
                                >
                            </div>
                        </div>
                    </div>
                </div>

                <!-- TAB 2: VOICE / TEXT INPUT -->
                <div v-else class="flex flex-col gap-4">
                    <div
                        class="relative flex flex-col items-center justify-center overflow-hidden rounded-xl border bg-muted/10 py-6"
                    >
                        <!-- Wave Animation -->
                        <div
                            v-if="isListening"
                            class="pointer-events-none absolute inset-0 flex items-center justify-center gap-1 opacity-20"
                        >
                            <div
                                v-for="i in 10"
                                :key="i"
                                class="w-1.5 animate-bounce rounded-full bg-orange-500"
                                :style="{
                                    height:
                                        Math.floor(Math.random() * 40 + 10) +
                                        'px',
                                    animationDelay: i * 0.1 + 's',
                                }"
                            ></div>
                        </div>

                        <button
                            @click="toggleListening"
                            :class="[
                                'z-10 flex h-20 w-20 items-center justify-center rounded-full shadow-lg transition-all focus:outline-none active:scale-95',
                                isListening
                                    ? 'animate-pulse bg-red-500 text-white ring-8 ring-red-500/20'
                                    : 'bg-orange-500 text-white ring-8 ring-orange-500/10 hover:bg-orange-600',
                            ]"
                        >
                            <Mic v-if="!isListening" class="h-8 w-8" />
                            <MicOff v-else class="h-8 w-8 animate-bounce" />
                        </button>

                        <span
                            class="z-10 mt-4 text-xs font-bold text-foreground"
                        >
                            {{
                                isListening
                                    ? 'Mendengarkan... (Silakan Bicara)'
                                    : 'Ketuk & Mulai Bicara'
                            }}
                        </span>
                        <span
                            class="z-10 mt-1 px-4 text-center text-[11px] text-muted-foreground"
                        >
                            Contoh: "beli pop mie cup besar tiga sama sedap soto
                            dua renteng"
                        </span>
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <label
                            class="text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                            >Perintah Suara / Teks Manual</label
                        >
                        <textarea
                            v-model="voiceText"
                            placeholder="Hasil suara atau tulis perintah secara manual di sini..."
                            class="h-28 w-full resize-none rounded-lg border bg-background p-3.5 text-xs font-medium text-foreground focus:ring-1 focus:ring-orange-500 focus:outline-none"
                        ></textarea>
                    </div>

                    <div
                        v-if="voiceError"
                        class="flex items-center gap-2 rounded-lg border border-red-500/20 bg-red-500/5 p-3 text-xs font-medium text-red-600"
                    >
                        <AlertCircle class="h-4 w-4 shrink-0" />
                        <span>{{ voiceError }}</span>
                    </div>

                    <button
                        @click="analyzeVoiceText"
                        :disabled="!voiceText.trim() || isVoiceAnalyzing"
                        class="flex h-10 w-full items-center justify-center gap-2 rounded-lg bg-orange-600 text-xs font-bold text-white shadow-md shadow-orange-600/15 transition-all hover:bg-orange-700 active:scale-[0.98] disabled:opacity-50"
                    >
                        <Sparkles v-if="!isVoiceAnalyzing" class="h-4 w-4" />
                        <span
                            v-else
                            class="h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent"
                        ></span>
                        <span>{{
                            isVoiceAnalyzing
                                ? 'Menganalisis Perintah...'
                                : 'Analisis & Masukkan Produk'
                        }}</span>
                    </button>
                </div>
            </div>

            <!-- Right Column: Cart, Review, & Checkout -->
            <div
                class="flex flex-col gap-4 border-t pt-6 lg:col-span-7 lg:border-t-0 lg:border-l lg:pt-0 lg:pl-6"
            >
                <div class="flex items-center justify-between">
                    <span
                        class="text-xs font-bold tracking-wider text-muted-foreground uppercase"
                        >Keranjang Belanja ({{ cart.length }} item)</span
                    >
                    <button
                        v-if="cart.length > 0"
                        @click="clearCart"
                        class="flex items-center gap-1 text-xs font-bold text-red-500 transition-colors hover:text-red-700"
                    >
                        <Trash2 class="h-3.5 w-3.5" /> Bersihkan
                    </button>
                </div>

                <!-- Cart Items List -->
                <div
                    class="flex max-h-[300px] min-h-[180px] flex-1 flex-col gap-3 overflow-y-auto rounded-xl border bg-muted/5 p-4"
                >
                    <div
                        v-if="cart.length === 0"
                        class="flex h-full flex-col items-center justify-center py-8 text-center"
                    >
                        <ShoppingCart
                            class="mb-2 h-10 w-10 text-muted-foreground/35"
                        />
                        <span
                            class="text-xs font-semibold text-muted-foreground"
                            >Keranjang masih kosong</span
                        >
                        <p
                            class="mt-1 max-w-[200px] text-[10px] text-muted-foreground/80"
                        >
                            Silakan masukkan perintah suara/teks di kolom kiri
                            atau cari produk secara manual.
                        </p>
                    </div>

                    <div
                        v-else
                        v-for="item in cart"
                        :key="item.product.id"
                        class="flex items-center justify-between gap-4 border-b border-border pb-3 last:border-0 last:pb-0"
                    >
                        <div class="flex-1">
                            <h4 class="text-xs font-bold text-foreground">
                                {{ item.product.nama }}
                            </h4>
                            <span class="text-[10px] text-muted-foreground"
                                >Harga:
                                {{ formatRupiah(item.product.harga_jual) }} |
                                Stok: {{ item.product.stok }}</span
                            >
                        </div>

                        <div class="flex items-center gap-3">
                            <!-- Quantity Controls -->
                            <div
                                class="flex items-center gap-1 rounded-lg border bg-background p-1"
                            >
                                <button
                                    @click="updateQty(item.product.id, -1)"
                                    class="rounded p-1 text-muted-foreground hover:bg-muted"
                                >
                                    <Minus class="h-3 w-3" />
                                </button>
                                <span
                                    class="w-6 text-center text-xs font-bold text-foreground"
                                    >{{ item.qty }}</span
                                >
                                <button
                                    @click="updateQty(item.product.id, 1)"
                                    class="rounded p-1 text-muted-foreground hover:bg-muted"
                                >
                                    <Plus class="h-3 w-3" />
                                </button>
                            </div>

                            <!-- Tombol Hapus Explicit -->
                            <button
                                @click="removeProductFromCart(item.product.id)"
                                class="rounded-lg p-1.5 text-red-500 transition-colors hover:bg-red-50 hover:text-red-700"
                                title="Hapus Produk"
                            >
                                <Trash2 class="h-4.5 w-4.5" />
                            </button>
                        </div>

                        <div
                            class="min-w-[80px] text-right text-xs font-bold text-foreground"
                        >
                            {{
                                formatRupiah(item.product.harga_jual * item.qty)
                            }}
                        </div>
                    </div>
                </div>

                <!-- Calculation & Payment -->
                <div
                    v-if="cart.length > 0"
                    class="flex flex-col gap-4 border-t pt-4"
                >
                    <div
                        class="flex items-center justify-between text-xs font-bold"
                    >
                        <span>TOTAL BELANJA</span>
                        <span class="text-sm font-extrabold text-orange-600">{{
                            formatRupiah(cartTotal)
                        }}</span>
                    </div>

                    <!-- Payment -->
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="flex flex-col gap-1.5">
                            <label
                                class="text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                                >Nominal Bayar Cash</label
                            >
                            <div class="relative">
                                <span
                                    class="absolute top-2 left-3 text-xs font-bold text-muted-foreground"
                                    >Rp</span
                                >
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
                                    class="flex h-9 w-full items-center justify-center rounded-lg border border-orange-500/30 bg-orange-500/5 text-xs font-bold text-orange-600 transition-all hover:bg-orange-500/10 active:scale-[0.98]"
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
                            class="h-8 rounded-lg border bg-background text-[10px] font-bold transition-all hover:border-orange-500 active:scale-[0.98]"
                        >
                            {{ formatRupiah(amt) }}
                        </button>
                    </div>

                    <!-- Kembalian -->
                    <div
                        v-if="payAmount !== null && payAmount >= cartTotal"
                        class="flex justify-between rounded-lg border border-orange-500/15 bg-orange-500/5 p-3 text-xs font-bold text-orange-600"
                    >
                        <span>KEMBALIAN</span>
                        <span>{{ formatRupiah(changeAmount) }}</span>
                    </div>

                    <!-- Checkout Button -->
                    <button
                        @click="checkoutTransaction"
                        :disabled="
                            payAmount === null ||
                            payAmount < cartTotal ||
                            isVoiceAnalyzing
                        "
                        class="flex h-10 w-full items-center justify-center rounded-lg bg-orange-600 text-xs font-bold text-white shadow-lg shadow-orange-600/15 transition-all hover:bg-orange-700 active:scale-[0.98] disabled:opacity-50"
                    >
                        <span>PROSES TRANSAKSI</span>
                    </button>
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
                <span class="text-xs font-bold text-foreground"
                    >Struk Nota Belanja</span
                >
                <button
                    @click="closeReceiptModal"
                    class="rounded-full p-1 text-muted-foreground hover:bg-muted"
                >
                    <X class="h-4.5 w-4.5" />
                </button>
            </div>

            <!-- Print Container -->
            <div
                class="flex justify-center overflow-y-auto bg-white p-4 text-black"
                id="receipt-print-area-transaksi-ai"
            >
                <div
                    style="
                        font-family:
                            'Plus Jakarta Sans',
                            DejaVu Sans,
                            sans-serif;
                        font-size: 15px;
                        line-height: 1.2;
                        width: 200px;
                    "
                >
                    <!-- Header Info -->
                    <div style="text-align: center; margin-bottom: 5px">
                        <h3
                            style="
                                margin: 0 0 5px 0;
                                font-size: 14px;
                                font-weight: bold;
                            "
                        >
                            Agen Sosis <br />
                            Lancar Manunggal
                        </h3>
                        <p style="margin: 0; font-size: 11px">
                            Jl. Raya Tayu-Jepara Km 7 <br />
                            depan Kantor Pos Ngablak
                        </p>
                        <p style="margin: 0; font-size: 11px">
                            HP: 085201454015
                        </p>
                    </div>

                    <div
                        style="border-bottom: 1px dashed #000; margin: 5px 0"
                    ></div>

                    <!-- Meta Info -->
                    <table
                        style="
                            width: 100%;
                            border-collapse: collapse;
                            margin-bottom: 5px;
                            font-size: 12px;
                        "
                    >
                        <tr>
                            <td
                                style="
                                    text-align: left;
                                    width: 90px;
                                    padding: 1px 0;
                                "
                            >
                                No Transaksi
                            </td>
                            <td style="text-align: left; padding: 1px 0">
                                : {{ activeReceipt.kode }}
                            </td>
                        </tr>
                        <tr>
                            <td style="text-align: left; padding: 1px 0">
                                Tanggal
                            </td>
                            <td style="text-align: left; padding: 1px 0">
                                :
                                {{ formatDate(activeReceipt.tanggaltransaksi) }}
                            </td>
                        </tr>
                    </table>

                    <div
                        style="border-bottom: 1px dashed #000; margin: 5px 0"
                    ></div>

                    <!-- Products List Table matching nota.blade.php -->
                    <table
                        style="
                            width: 100%;
                            border-collapse: collapse;
                            margin-bottom: 5px;
                            font-size: 14px;
                        "
                    >
                        <thead>
                            <tr>
                                <th
                                    style="
                                        text-align: left;
                                        padding: 1px 0;
                                        font-weight: bold;
                                    "
                                >
                                    Produk
                                </th>
                                <th
                                    style="
                                        text-align: right;
                                        padding: 1px 0;
                                        font-weight: bold;
                                        white-space: nowrap;
                                    "
                                >
                                    Subtotal
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <template
                                v-for="det in activeReceipt.details"
                                :key="det.id"
                            >
                                <tr>
                                    <td
                                        style="
                                            text-align: left;
                                            vertical-align: top;
                                            padding: 1px 0;
                                        "
                                    >
                                        {{ det.produk.nama }}
                                    </td>
                                    <td
                                        rowspan="2"
                                        style="
                                            text-align: right;
                                            vertical-align: bottom;
                                            padding: 1px 0;
                                            white-space: nowrap;
                                        "
                                    >
                                        {{ formatNumber(det.subtotal) }}
                                    </td>
                                </tr>
                                <tr>
                                    <td
                                        style="
                                            text-align: left;
                                            font-size: 13px;
                                            padding-bottom: 5px;
                                            padding-top: 1px;
                                        "
                                    >
                                        {{ formatNumber(det.harga) }} x
                                        {{ det.jumlah }}
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>

                    <div
                        style="border-bottom: 1px dashed #000; margin: 5px 0"
                    ></div>

                    <!-- Summary Table -->
                    <table
                        style="
                            width: 100%;
                            border-collapse: collapse;
                            margin-bottom: 5px;
                            font-size: 14px;
                        "
                    >
                        <tr>
                            <td style="text-align: left; padding: 1px 0">
                                Total
                            </td>
                            <th
                                style="
                                    text-align: right;
                                    padding: 1px 0;
                                    font-weight: bold;
                                "
                            >
                                : Rp {{ formatNumber(activeReceipt.total) }}
                            </th>
                        </tr>
                        <tr>
                            <td style="text-align: left; padding: 1px 0">
                                Bayar
                            </td>
                            <th
                                style="
                                    text-align: right;
                                    padding: 1px 0;
                                    font-weight: bold;
                                "
                            >
                                : Rp {{ formatNumber(activeReceipt.bayar) }}
                            </th>
                        </tr>
                        <tr>
                            <td style="text-align: left; padding: 1px 0">
                                Kembalian
                            </td>
                            <th
                                style="
                                    text-align: right;
                                    padding: 1px 0;
                                    font-weight: bold;
                                "
                            >
                                : Rp {{ formatNumber(activeReceipt.kembalian) }}
                            </th>
                        </tr>
                    </table>

                    <div
                        style="border-bottom: 1px dashed #000; margin: 5px 0"
                    ></div>

                    <!-- Footer -->
                    <div
                        style="
                            text-align: center;
                            margin-top: 5px;
                            font-size: 13px;
                        "
                    >
                        <p style="margin: 0; line-height: 1.3">
                            Terima kasih telah berbelanja! <br />
                            Barang yang sudah dibeli <br />
                            tidak dapat dikembalikan.
                        </p>
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
            <div
                class="flex shrink-0 items-center justify-between border-b bg-muted/20 px-4 py-3"
            >
                <div class="flex items-center gap-2">
                    <ListFilter class="h-5 w-5 text-orange-600" />
                    <span class="text-sm font-extrabold text-foreground">
                        {{
                            isViewingAllTransactions
                                ? 'Daftar Semua Transaksi'
                                : 'Daftar Transaksi Hari Ini'
                        }}
                    </span>
                </div>
                <button
                    @click="showTransactionsModal = false"
                    class="rounded-full p-1 text-muted-foreground hover:bg-muted"
                >
                    <X class="h-4.5 w-4.5" />
                </button>
            </div>

            <!-- Body -->
            <div class="flex flex-1 flex-col gap-4 overflow-y-auto p-4">
                <!-- Search bar & entries info -->
                <div
                    class="flex flex-col justify-between gap-3 rounded-xl border bg-muted/10 p-3 md:flex-row md:items-center"
                >
                    <span
                        class="text-xs font-bold tracking-wider text-muted-foreground uppercase"
                    >
                        {{ transactionsPagination?.total || 0 }} Transaksi
                        Ditemukan
                    </span>
                    <div class="relative w-full md:w-64">
                        <Search
                            class="absolute top-2.5 left-3 h-4 w-4 text-muted-foreground"
                        />
                        <Input
                            type="text"
                            placeholder="Cari kode transaksi..."
                            v-model="transactionSearchQuery"
                            class="h-9 pl-9 text-xs focus-visible:ring-orange-500"
                        />
                    </div>
                </div>

                <!-- Transactions Table -->
                <div class="overflow-x-auto rounded-xl border bg-background">
                    <table class="w-full border-collapse text-left text-xs">
                        <thead>
                            <tr
                                class="border-b bg-muted/30 text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                            >
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
                            <tr
                                v-if="isLoadingTransactions"
                                class="border-b last:border-0"
                            >
                                <td
                                    colspan="7"
                                    class="p-8 text-center text-muted-foreground"
                                >
                                    <span
                                        class="inline-block h-6 w-6 animate-spin rounded-full border-2 border-orange-500 border-t-transparent"
                                    ></span>
                                    <p class="mt-2 text-xs">
                                        Memuat data transaksi...
                                    </p>
                                </td>
                            </tr>
                            <tr
                                v-else-if="todayTransactions.length === 0"
                                class="border-b last:border-0"
                            >
                                <td
                                    colspan="7"
                                    class="p-8 text-center font-semibold text-muted-foreground"
                                >
                                    Tidak ada transaksi ditemukan.
                                </td>
                            </tr>
                            <tr
                                v-else
                                v-for="(trx, idx) in todayTransactions"
                                :key="trx.id"
                                class="border-b font-medium text-foreground transition-colors last:border-0 hover:bg-muted/5"
                            >
                                <td class="p-3">
                                    {{
                                        (currentPageTransactions - 1) * 10 +
                                        idx +
                                        1
                                    }}
                                </td>
                                <td class="p-3 font-bold text-orange-600">
                                    {{ trx.kode }}
                                </td>
                                <td class="p-3">
                                    {{ formatDate(trx.tanggaltransaksi) }}
                                </td>
                                <td class="p-3 font-extrabold">
                                    {{ formatRupiah(trx.total) }}
                                </td>
                                <td class="p-3">
                                    {{ formatRupiah(trx.bayar) }}
                                </td>
                                <td class="p-3 text-muted-foreground">
                                    {{ formatRupiah(trx.kembalian) }}
                                </td>
                                <td class="p-3">
                                    <div
                                        class="flex items-center justify-center gap-1.5"
                                    >
                                        <!-- Detail Button -->
                                        <button
                                            @click="viewReceiptDetail(trx)"
                                            class="flex h-7 w-7 items-center justify-center rounded-lg bg-blue-500 text-white shadow-sm transition-colors hover:bg-blue-600"
                                            title="Lihat Detail Nota"
                                        >
                                            <Search class="h-3.5 w-3.5" />
                                        </button>
                                        <!-- Print Button -->
                                        <button
                                            @click="
                                                printTransactionReceipt(trx)
                                            "
                                            class="flex h-7 w-7 items-center justify-center rounded-lg bg-emerald-500 text-white shadow-sm transition-colors hover:bg-emerald-600"
                                            title="Cetak Ulang Nota"
                                        >
                                            <Printer class="h-3.5 w-3.5" />
                                        </button>
                                        <!-- Edit Button -->
                                        <button
                                            @click="
                                                openEditTransactionModal(trx)
                                            "
                                            class="flex h-7 w-7 items-center justify-center rounded-lg bg-amber-500 text-white shadow-sm transition-colors hover:bg-amber-600"
                                            title="Ubah Transaksi"
                                        >
                                            <Edit class="h-3.5 w-3.5" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination Controls -->
                <div
                    v-if="
                        transactionsPagination &&
                        transactionsPagination.last_page > 1
                    "
                    class="mt-1 flex items-center justify-between border-t pt-3 text-xs"
                >
                    <span
                        class="text-[10px] font-bold text-muted-foreground uppercase"
                    >
                        Halaman {{ transactionsPagination.current_page }} dari
                        {{ transactionsPagination.last_page }}
                    </span>

                    <div class="flex items-center gap-1.5">
                        <button
                            v-for="(link, lIdx) in transactionsPagination.links"
                            :key="lIdx"
                            v-show="
                                link.url &&
                                link.label.includes('Previous') === false &&
                                link.label.includes('Next') === false
                            "
                            @click="
                                fetchTodayTransactions(parseInt(link.label))
                            "
                            :class="[
                                'rounded border px-2.5 py-1 text-xs font-bold transition-all',
                                link.active
                                    ? 'border-orange-600 bg-orange-600 text-white shadow-xs'
                                    : 'bg-background text-muted-foreground hover:bg-muted',
                            ]"
                            v-html="link.label"
                        />
                    </div>
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

    <!-- DIALOG UBAH TRANSAKSI MODAL -->
    <div
        v-if="showEditTransactionModal && editingTransaction"
        class="fixed inset-0 z-50 flex animate-in items-center justify-center bg-black/60 p-4 fade-in"
    >
        <div
            class="flex max-h-[90%] w-full max-w-[640px] animate-in flex-col rounded-xl bg-card shadow-xl duration-150 zoom-in-95"
        >
            <!-- Header -->
            <div
                class="flex shrink-0 items-center justify-between border-b bg-muted/20 px-4 py-3"
            >
                <div class="flex items-center gap-2">
                    <Edit class="h-5 w-5 text-orange-600" />
                    <span class="text-sm font-extrabold text-foreground"
                        >Ubah Transaksi #{{ editingTransaction.kode }}</span
                    >
                </div>
                <button
                    @click="closeEditTransactionModal"
                    class="rounded-full p-1 text-muted-foreground hover:bg-muted"
                >
                    <X class="h-4.5 w-4.5" />
                </button>
            </div>

            <!-- Body -->
            <div class="flex flex-1 flex-col gap-4 overflow-y-auto p-4">
                <!-- Search Product to Add to Cart -->
                <div class="relative flex flex-col gap-1.5">
                    <label
                        class="text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                        >Tambah Produk Baru ke Transaksi</label
                    >
                    <div class="relative">
                        <Search
                            class="absolute top-2.5 left-3 h-4 w-4 text-muted-foreground"
                        />
                        <Input
                            type="text"
                            placeholder="Cari nama produk atau scan barcode..."
                            v-model="editTransactionSearch"
                            class="h-9 pl-9 text-xs focus-visible:ring-orange-500"
                        />
                    </div>

                    <!-- Search Results Dropdown -->
                    <div
                        v-if="editTransactionSearchProducts.length > 0"
                        class="absolute top-16 right-0 left-0 z-30 max-h-48 overflow-y-auto rounded-lg border bg-popover p-1 text-popover-foreground shadow-md"
                    >
                        <div
                            v-for="prod in editTransactionSearchProducts"
                            :key="prod.id"
                            @click="addProductToEditCart(prod)"
                            class="flex cursor-pointer items-center justify-between rounded px-3 py-2 text-xs font-semibold transition-colors hover:bg-muted"
                        >
                            <span>{{ prod.nama }}</span>
                            <span class="text-orange-600">{{
                                formatRupiah(prod.harga_jual)
                            }}</span>
                        </div>
                    </div>
                </div>

                <!-- Cart Items List -->
                <div class="flex flex-col gap-2">
                    <label
                        class="text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                        >Item Transaksi</label
                    >
                    <div
                        class="max-h-60 overflow-hidden overflow-y-auto rounded-xl border bg-background"
                    >
                        <table class="w-full border-collapse text-left text-xs">
                            <thead>
                                <tr
                                    class="border-b bg-muted/30 text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                                >
                                    <th class="p-3">PRODUK</th>
                                    <th class="p-3 text-right">HARGA</th>
                                    <th class="p-3 text-center">QTY</th>
                                    <th class="p-3 text-right">SUBTOTAL</th>
                                    <th class="p-3 text-center">AKSI</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="item in editTransactionCart"
                                    :key="item.product.id"
                                    class="border-b font-medium transition-colors last:border-0 hover:bg-muted/5"
                                >
                                    <td class="p-3">
                                        <div class="font-bold text-foreground">
                                            {{ item.product.nama }}
                                        </div>
                                        <div
                                            class="text-[10px] text-muted-foreground"
                                        >
                                            {{ item.product.kode }}
                                        </div>
                                    </td>
                                    <td class="p-3 text-right">
                                        {{ formatRupiah(item.harga) }}
                                    </td>
                                    <td class="p-3">
                                        <div
                                            class="flex items-center justify-center gap-1.5"
                                        >
                                            <button
                                                type="button"
                                                @click="
                                                    updateEditCartQty(
                                                        item.product.id,
                                                        -1,
                                                    )
                                                "
                                                class="flex h-5 w-5 items-center justify-center rounded bg-muted text-foreground transition-all hover:bg-muted-foreground/10 active:scale-90"
                                            >
                                                <Minus class="h-3 w-3" />
                                            </button>
                                            <span
                                                class="w-6 text-center font-bold"
                                                >{{ item.qty }}</span
                                            >
                                            <button
                                                type="button"
                                                @click="
                                                    updateEditCartQty(
                                                        item.product.id,
                                                        1,
                                                    )
                                                "
                                                class="flex h-5 w-5 items-center justify-center rounded bg-muted text-foreground transition-all hover:bg-muted-foreground/10 active:scale-90"
                                            >
                                                <Plus class="h-3 w-3" />
                                            </button>
                                        </div>
                                    </td>
                                    <td
                                        class="p-3 text-right font-extrabold text-orange-600"
                                    >
                                        {{
                                            formatRupiah(item.harga * item.qty)
                                        }}
                                    </td>
                                    <td class="p-3 text-center">
                                        <button
                                            type="button"
                                            @click="
                                                removeProductFromEditCart(
                                                    item.product.id,
                                                )
                                            "
                                            class="rounded p-1 text-red-500 hover:bg-red-50"
                                        >
                                            <Trash2 class="h-4 w-4" />
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Payment inputs and calculations -->
                <div
                    class="mt-2 grid gap-4 rounded-xl border bg-muted/10 p-4 sm:grid-cols-2"
                >
                    <div class="flex flex-col gap-1.5">
                        <label
                            class="text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                            >Nominal Bayar (Cash)</label
                        >
                        <div class="relative">
                            <span
                                class="absolute top-2.5 left-3 text-xs font-bold text-muted-foreground"
                                >Rp</span
                            >
                            <Input
                                type="number"
                                v-model.number="editTransactionPay"
                                placeholder="0"
                                class="h-9 pl-8 text-xs font-bold focus-visible:ring-orange-500"
                            />
                        </div>
                        <!-- Uang Pas Button -->
                        <button
                            @click="editTransactionPay = editTransactionTotal"
                            class="flex h-8 w-full items-center justify-center rounded-lg border border-orange-500/30 bg-orange-500/5 text-xs font-bold text-orange-600 transition-all hover:bg-orange-500/10 active:scale-[0.98]"
                        >
                            Uang Pas ({{ formatRupiah(editTransactionTotal) }})
                        </button>
                        <!-- Quick Nominal Buttons -->
                        <div class="grid grid-cols-3 gap-1.5">
                            <button
                                v-for="amt in [20000, 50000, 100000]"
                                :key="amt"
                                v-show="amt > editTransactionTotal"
                                @click="editTransactionPay = amt"
                                class="h-7 rounded-lg border bg-background text-[10px] font-bold transition-all hover:border-orange-500 active:scale-[0.98]"
                            >
                                {{ formatRupiah(amt) }}
                            </button>
                        </div>
                    </div>

                    <div class="flex flex-col justify-center gap-1 text-right">
                        <div
                            class="text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                        >
                            Total Belanja Baru
                        </div>
                        <div
                            class="text-lg font-extrabold text-[#171717] dark:text-white"
                        >
                            {{ formatRupiah(editTransactionTotal) }}
                        </div>
                        <div
                            class="mt-1 text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                        >
                            Kembalian
                        </div>
                        <div class="text-sm font-bold text-orange-600">
                            {{ formatRupiah(editTransactionChange) }}
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div
                class="flex shrink-0 justify-end gap-2 border-t bg-muted/20 p-3"
            >
                <Button
                    @click="closeEditTransactionModal"
                    variant="outline"
                    class="h-8 text-xs font-bold"
                >
                    Batal
                </Button>
                <Button
                    @click="submitUpdateTransaction"
                    :disabled="
                        editTransactionCart.length === 0 ||
                        editTransactionPay < editTransactionTotal
                    "
                    class="h-8 bg-orange-600 text-xs font-bold text-white hover:bg-orange-700 disabled:opacity-50"
                >
                    Simpan Perubahan
                </Button>
            </div>
        </div>
    </div>

    <!-- Hidden Print Target container that is ALWAYS in the DOM -->
    <div style="display: none">
        <div id="receipt-print-area-transaksi-ai-always" v-if="activeReceipt">
            <div
                style="
                    font-family:
                        'Plus Jakarta Sans',
                        DejaVu Sans,
                        sans-serif;
                    font-size: 16px;
                    line-height: 1.4;
                    width: 220px;
                "
            >
                <!-- Header Info -->
                <div style="text-align: center; margin-bottom: 5px">
                    <h3
                        style="
                            margin: 0 0 5px 0;
                            font-size: 15px;
                            font-weight: bold;
                        "
                    >
                        Agen Sosis <br />
                        Lancar Manunggal
                    </h3>
                    <p style="margin: 0; font-size: 12px">
                        Jl. Raya Tayu-Jepara Km 7 <br />
                        depan Kantor Pos Ngablak
                    </p>
                    <p style="margin: 0; font-size: 12px">HP: 085201454015</p>
                </div>

                <div
                    style="border-bottom: 1px dashed #000; margin: 5px 0"
                ></div>

                <!-- Meta Info -->
                <table
                    style="
                        width: 100%;
                        border-collapse: collapse;
                        margin-bottom: 5px;
                        font-size: 13px;
                    "
                >
                    <tr>
                        <td
                            style="
                                text-align: left;
                                width: 95px;
                                padding: 3px 0;
                            "
                        >
                            Transaksi
                        </td>
                        <td style="text-align: left; padding: 3px 0">
                            : {{ activeReceipt.kode }}
                        </td>
                    </tr>
                    <tr>
                        <td style="text-align: left; padding: 3px 0">
                            Tanggal
                        </td>
                        <td style="text-align: left; padding: 3px 0">
                            : {{ formatDate(activeReceipt.tanggaltransaksi) }}
                        </td>
                    </tr>
                </table>

                <div
                    style="border-bottom: 1px dashed #000; margin: 5px 0"
                ></div>

                <!-- Products List Table matching nota.blade.php -->
                <table
                    style="
                        width: 100%;
                        border-collapse: collapse;
                        margin-bottom: 5px;
                        font-size: 16px;
                    "
                >
                    <thead>
                        <tr>
                            <th
                                style="
                                    text-align: left;
                                    padding: 3px 0;
                                    font-weight: bold;
                                "
                            >
                                Produk
                            </th>
                            <th
                                style="
                                    text-align: right;
                                    padding: 3px 0;
                                    font-weight: bold;
                                    white-space: nowrap;
                                "
                            >
                                Subtotal
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <template
                            v-for="det in activeReceipt.details"
                            :key="det.id"
                        >
                            <tr>
                                <td
                                    style="
                                        text-align: left;
                                        vertical-align: top;
                                        padding: 3px 0;
                                    "
                                >
                                    {{ det.produk.nama }}
                                </td>
                                <td
                                    rowspan="2"
                                    style="
                                        text-align: right;
                                        vertical-align: bottom;
                                        padding: 3px 0;
                                        white-space: nowrap;
                                    "
                                >
                                    {{ formatNumber(det.subtotal) }}
                                </td>
                            </tr>
                            <tr>
                                <td
                                    style="
                                        text-align: left;
                                        font-size: 14px;
                                        padding-bottom: 7px;
                                        padding-top: 1px;
                                    "
                                >
                                    {{ formatNumber(det.harga) }} x
                                    {{ det.jumlah }}
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>

                <div
                    style="border-bottom: 1px dashed #000; margin: 5px 0"
                ></div>

                <!-- Summary Table -->
                <table
                    style="
                        width: 100%;
                        border-collapse: collapse;
                        margin-bottom: 5px;
                        font-size: 15px;
                    "
                >
                    <tr>
                        <td style="text-align: left; padding: 3px 0">Total</td>
                        <th
                            style="
                                text-align: right;
                                padding: 3px 0;
                                font-weight: bold;
                            "
                        >
                            : Rp {{ formatNumber(activeReceipt.total) }}
                        </th>
                    </tr>
                    <tr>
                        <td style="text-align: left; padding: 3px 0">Bayar</td>
                        <th
                            style="
                                text-align: right;
                                padding: 3px 0;
                                font-weight: bold;
                            "
                        >
                            : Rp {{ formatNumber(activeReceipt.bayar) }}
                        </th>
                    </tr>
                    <tr>
                        <td style="text-align: left; padding: 3px 0">
                            Kembalian
                        </td>
                        <th
                            style="
                                text-align: right;
                                padding: 3px 0;
                                font-weight: bold;
                            "
                        >
                            : Rp {{ formatNumber(activeReceipt.kembalian) }}
                        </th>
                    </tr>
                </table>

                <div
                    style="border-bottom: 1px dashed #000; margin: 5px 0"
                ></div>

                <!-- Footer -->
                <div
                    style="text-align: center; margin-top: 5px; font-size: 14px"
                >
                    <p style="margin: 0; line-height: 1.4">
                        Terima kasih telah berbelanja! <br />
                        Barang yang sudah dibeli <br />
                        tidak dapat dikembalikan.
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>
