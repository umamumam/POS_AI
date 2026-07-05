<script setup lang="ts">
import { ref, onMounted, onBeforeUnmount, computed, watch } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import {
    Camera,
    Search,
    Plus,
    Minus,
    Trash2,
    ShoppingCart,
    Check,
    RefreshCw,
    Image as ImageIcon,
    Printer,
    Sparkles,
    AlertCircle,
    X,
    Home as HomeIcon,
    Package as PackageIcon,
    History as HistoryIcon,
    Settings as SettingsIcon,
    User as UserIcon,
    LogOut as LogOutIcon,
    LogIn as LogInIcon,
    ArrowRight,
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

interface Receipt {
    id: number;
    kode: string;
    total: number;
    bayar: number;
    kembalian: number;
    tanggaltransaksi: string;
    details: Array<{
        id: number;
        jumlah: number;
        harga: number;
        subtotal: number;
        produk: Product;
    }>;
}

const props = defineProps<{
    categories: Category[];
    initialProducts: Product[];
    recentTransactions: Receipt[];
    apiKeyConfigured: boolean;
}>();

// Active Tab View: 'home' | 'products' | 'transactions' | 'settings'
const activeTab = ref<'home' | 'products' | 'transactions' | 'settings'>(
    'home',
);

// POS Core State
const products = ref<Product[]>(props.initialProducts);
const categories = ref<Category[]>(props.categories);
const selectedCategory = ref<number | null>(null);
const searchQuery = ref('');
const cart = ref<CartItem[]>([]);
const payAmount = ref<number | null>(null);

// UI States
const isAnalyzing = ref(false);
const showCartDrawer = ref(false);
const showReceiptModal = ref(false);
const activeReceipt = ref<Receipt | null>(null);
const errorMessage = ref('');
const recentTransactions = ref<Receipt[]>(props.recentTransactions || []);

// Scanning Match Result Overlay
const scanMatch = ref<{
    success: boolean;
    product: Product | null;
    confidence?: number;
    reason?: string;
    matches?: Array<{
        product: Product;
        qty: number;
        confidence: number;
        reason: string;
    }>;
} | null>(null);
const capturedImage = ref<string | null>(null);

// Camera State
const videoRef = ref<HTMLVideoElement | null>(null);
const canvasRef = ref<HTMLCanvasElement | null>(null);
const stream = ref<MediaStream | null>(null);
const cameraActive = ref(false);
const cameraDevices = ref<MediaDeviceInfo[]>([]);
const selectedDeviceId = ref<string>('');
const showCameraError = ref(false);

// Format currency
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
        'Agu',
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

// Cart Computeds
const cartTotal = computed(() => {
    return cart.value.reduce(
        (total, item) => total + item.product.harga_jual * item.qty,
        0,
    );
});

const cartCount = computed(() => {
    return cart.value.reduce((total, item) => total + item.qty, 0);
});

const changeAmount = computed(() => {
    if (payAmount.value === null || payAmount.value < cartTotal.value) return 0;
    return payAmount.value - cartTotal.value;
});

const isCartEmpty = computed(() => cart.value.length === 0);

// API CSRF
function getCsrfToken() {
    const match = document.cookie.match(new RegExp('(^| )XSRF-TOKEN=([^;]+)'));
    if (match) return decodeURIComponent(match[2]);
    return null;
}

// Fetch products when category or search changes (Manual Product Catalog tab)
const fetchProducts = async () => {
    try {
        let url = `/api/products?q=${encodeURIComponent(searchQuery.value)}`;
        if (selectedCategory.value) {
            url += `&category_id=${selectedCategory.value}`;
        }

        const response = await fetch(url, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (response.ok) {
            const data = await response.json();
            products.value = data.data || data;
        }
    } catch (err) {
        console.error('Gagal mengambil produk:', err);
    }
};

let searchTimeout: NodeJS.Timeout;
watch([searchQuery, selectedCategory], () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        fetchProducts();
    }, 300);
});

// Camera controls
const initCamera = async (deviceId?: string) => {
    if (activeTab.value !== 'home') return;

    try {
        stopCamera();
        showCameraError.value = false;

        const constraints: MediaStreamConstraints = {
            video: deviceId
                ? { deviceId: { exact: deviceId } }
                : { facingMode: 'environment' },
            audio: false,
        };

        const mediaStream =
            await navigator.mediaDevices.getUserMedia(constraints);
        stream.value = mediaStream;

        if (videoRef.value) {
            videoRef.value.srcObject = mediaStream;
            videoRef.value.play();
        }

        cameraActive.value = true;

        const devices = await navigator.mediaDevices.enumerateDevices();
        cameraDevices.value = devices.filter(
            (device) => device.kind === 'videoinput',
        );

        const activeTrack = mediaStream.getVideoTracks()[0];
        if (activeTrack) {
            selectedDeviceId.value = activeTrack.getSettings().deviceId || '';
        }
    } catch (err) {
        console.error('Kamera gagal diakses:', err);
        showCameraError.value = true;
        cameraActive.value = false;
    }
};

const stopCamera = () => {
    if (stream.value) {
        stream.value.getTracks().forEach((track) => track.stop());
        stream.value = null;
    }
    if (videoRef.value) {
        videoRef.value.srcObject = null;
    }
    cameraActive.value = false;
};

// Auto start/stop camera based on active tab
watch(activeTab, (newTab) => {
    if (newTab === 'home') {
        setTimeout(() => initCamera(), 100);
    } else {
        stopCamera();
        scanMatch.value = null;
        capturedImage.value = null;
    }

    if (newTab === 'transactions') {
        fetchRecentTransactions();
    }
});

// Fetch recent transactions
const fetchRecentTransactions = async () => {
    // We can just fetch transactions, or load them from local history
};

// Capture Photo and Analyze using Gemini
const captureAndAnalyze = async () => {
    if (!videoRef.value || !canvasRef.value || isAnalyzing.value) return;

    isAnalyzing.value = true;
    scanMatch.value = null;
    errorMessage.value = '';

    try {
        const video = videoRef.value;
        const canvas = canvasRef.value;
        const context = canvas.getContext('2d');

        if (context) {
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            context.drawImage(video, 0, 0, canvas.width, canvas.height);
            const base64Image = canvas.toDataURL('image/jpeg', 0.8);
            capturedImage.value = base64Image;

            const token = getCsrfToken();
            const response = await fetch('/api/pos/analyze', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-XSRF-TOKEN': token || '',
                },
                body: JSON.stringify({ image: base64Image }),
            });

            const data = await response.json();

            if (response.ok && data.success) {
                scanMatch.value = {
                    success: true,
                    matches: data.matches
                };
            } else {
                scanMatch.value = {
                    success: false,
                    reason: data.message || 'Produk tidak dikenali.',
                };
            }
        }
    } catch (err: any) {
        console.error('Analisis gambar gagal:', err);
        errorMessage.value = err.message || 'Gagal menganalisis gambar.';
    } finally {
        isAnalyzing.value = false;
    }
};

// Fallback upload file analysis
const handleImageUpload = async (event: Event) => {
    const target = event.target as HTMLInputElement;
    if (!target.files || target.files.length === 0) return;

    const file = target.files[0];
    const reader = new FileReader();

    isAnalyzing.value = true;
    scanMatch.value = null;
    errorMessage.value = '';

    reader.onload = async (e) => {
        try {
            const base64Image = e.target?.result as string;
            capturedImage.value = base64Image;

            const token = getCsrfToken();
            const response = await fetch('/api/pos/analyze', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-XSRF-TOKEN': token || '',
                },
                body: JSON.stringify({ image: base64Image }),
            });

            const data = await response.json();

            if (response.ok && data.success) {
                scanMatch.value = {
                    success: true,
                    matches: data.matches
                };
            } else {
                scanMatch.value = {
                    success: false,
                    reason: data.message || 'Produk tidak dikenali.',
                };
            }
        } catch (err: any) {
            console.error('Gagal menganalisis file:', err);
            errorMessage.value = err.message || 'Gagal memproses file.';
        } finally {
            isAnalyzing.value = false;
            target.value = '';
        }
    };

    reader.readAsDataURL(file);
};

// Add to Cart
const addToCart = (product: Product) => {
    const existingIndex = cart.value.findIndex(
        (item) => item.product.id === product.id,
    );

    if (existingIndex > -1) {
        if (cart.value[existingIndex].qty < product.stok) {
            cart.value[existingIndex].qty += 1;
        } else {
            alert(`Stok untuk ${product.nama} sudah mencapai batas maksimum.`);
            return;
        }
    } else {
        if (product.stok > 0) {
            cart.value.push({ product, qty: 1 });
        } else {
            alert(`Produk ${product.nama} habis.`);
            return;
        }
    }
    scanMatch.value = null;
    capturedImage.value = null;
    searchQuery.value = ''; // Reset input pencarian produk
    
    // Auto redirect to Home and open the Cart Drawer immediately
    activeTab.value = 'home';
    showCartDrawer.value = true;
};

// Add all scanned matches to Cart
const addMatchesToCart = () => {
    if (!scanMatch.value || !scanMatch.value.matches) return;

    scanMatch.value.matches.forEach((match) => {
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
                alert(`Stok untuk ${product.nama} terbatas. Hanya ditambahkan sampai batas stok (${product.stok}).`);
            }
        } else {
            if (product.stok > 0) {
                const finalQty = Math.min(qtyToAdd, product.stok);
                cart.value.push({ product, qty: finalQty });
                if (qtyToAdd > product.stok) {
                    alert(`Stok untuk ${product.nama} terbatas. Hanya ditambahkan ${product.stok}.`);
                }
            } else {
                alert(`Produk ${product.nama} habis.`);
            }
        }
    });
    scanMatch.value = null;
    capturedImage.value = null;
};

// Checkout
const checkoutTransaction = async () => {
    if (isCartEmpty.value || isAnalyzing.value) return;

    if (payAmount.value === null || payAmount.value < cartTotal.value) {
        alert('Jumlah bayar kurang.');
        return;
    }

    isAnalyzing.value = true;

    try {
        const payload = {
            items: cart.value.map((item) => ({
                id: item.product.id,
                qty: item.qty,
                harga: item.product.harga_jual,
            })),
            total: cartTotal.value,
            bayar: payAmount.value,
        };

        const token = getCsrfToken();
        const response = await fetch('/api/pos/checkout', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': token || '',
            },
            body: JSON.stringify(payload),
        });

        const data = await response.json();

        if (response.ok && data.success) {
            activeReceipt.value = data.receipt;
            recentTransactions.value.unshift(data.receipt);
            showReceiptModal.value = true;
            showCartDrawer.value = false;

            cart.value.forEach((item) => {
                const prod = products.value.find(
                    (p) => p.id === item.product.id,
                );
                if (prod) prod.stok -= item.qty;
            });

            clearCart();
        } else {
            alert(data.message || 'Transaksi gagal.');
        }
    } catch (err: any) {
        console.error('Checkout gagal:', err);
    } finally {
        isAnalyzing.value = false;
    }
};

const clearCart = () => {
    cart.value = [];
    payAmount.value = null;
    scanMatch.value = null;
};

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

const printReceipt = () => {
    const printContent = document.getElementById('receipt-print-area');
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

const setQuickPay = (amount: number) => {
    payAmount.value = amount;
};

// Lifecycle
onMounted(() => {
    initCamera();
});

onBeforeUnmount(() => {
    stopCamera();
});
</script>

<template>
    <Head title="POS Lancar Manunggal" />

    <!-- CLEAN CENTERED VIEW WRAPPER -->
    <div
        class="flex min-h-screen items-stretch justify-center bg-neutral-100 antialiased md:items-center dark:bg-neutral-950"
    >
        <!-- MOBILE SCREEN CONTAINER -->
        <!-- On Desktop: Centers as a nice app card | On Mobile: Fills 100% of viewport -->
        <div
            class="relative flex h-[100dvh] w-full max-w-md flex-col overflow-hidden border-border bg-background md:h-[90vh] md:rounded-2xl md:border md:shadow-lg"
        >
            <!-- 1. Header -->
            <header
                class="flex shrink-0 items-center justify-between border-b border-border bg-card px-4 pt-4 pb-3"
            >
                <div class="flex items-center gap-2">
                    <div class="rounded-lg bg-primary/10 p-1.5 text-primary">
                        <ShoppingCart class="h-5 w-5" />
                    </div>
                    <span
                        class="text-sm font-extrabold tracking-wide text-foreground"
                    >
                        Lancar Manunggal
                    </span>
                </div>

                <!-- Auth Info / Actions -->
                <div class="flex items-center gap-2">
                    <Link
                        v-if="$page.props.auth.user"
                        method="post"
                        as="button"
                        href="/logout"
                        class="flex items-center gap-1 rounded-lg bg-secondary px-3 py-1.5 text-xs font-semibold text-secondary-foreground transition-colors hover:bg-muted"
                        title="Log Out"
                    >
                        <LogOutIcon class="h-3.5 w-3.5" /> Logout
                    </Link>
                    <Link
                        v-else
                        href="/login"
                        class="flex items-center gap-1 rounded-lg bg-primary px-3 py-1.5 text-xs font-bold text-primary-foreground transition-opacity hover:opacity-90"
                        title="Log In"
                    >
                        <LogInIcon class="h-3.5 w-3.5" /> Login
                    </Link>
                </div>
            </header>

            <!-- 2. Screen View Content -->
            <main
                class="relative flex flex-1 flex-col overflow-y-auto bg-muted/5 pb-20"
            >
                <!-- TAB A: HOME (Camera Scanner View) -->
                <div v-if="activeTab === 'home'" class="flex flex-1 flex-col">
                    <!-- Search bar link -->
                    <div class="p-3">
                        <div class="relative" @click="activeTab = 'products'">
                            <Search
                                class="absolute top-3 left-3 h-5 w-5 text-muted-foreground"
                            />
                            <Input
                                type="text"
                                placeholder="Cari Nama Produk..."
                                class="pointer-events-none h-11 cursor-pointer rounded-xl border-border bg-card pl-10 text-sm"
                            />
                        </div>
                    </div>

                    <!-- WebRTC Camera Stream Frame -->
                    <div class="relative flex flex-col px-3 pb-2 shrink-0">
                        <div
                            class="relative flex h-[270px] w-full items-center justify-center overflow-hidden rounded-2xl border border-border bg-black shadow-inner shrink-0"
                        >
                            <!-- Tampilkan Preview Hasil Foto jika ada -->
                            <img
                                v-if="capturedImage"
                                :src="capturedImage"
                                class="h-full w-full object-cover rounded-2xl"
                                alt="Hasil Foto Produk"
                            />
                            <!-- Tampilkan Stream Video Aktif jika tidak ada foto terambil -->
                            <video
                                v-else
                                ref="videoRef"
                                autoplay
                                playsinline
                                muted
                                class="h-full w-full object-cover"
                                v-show="cameraActive"
                            ></video>
                            <canvas ref="canvasRef" class="hidden"></canvas>

                            <!-- Glowing Scanner target corners -->
                            <div
                                v-if="!capturedImage"
                                class="pointer-events-none absolute inset-0 z-10 flex items-center justify-center"
                            >
                                <div
                                    class="relative h-44 w-44 rounded-2xl border-[3px] border-green-500/80"
                                >
                                    <div
                                        class="absolute -top-1 -left-1 h-6 w-6 rounded-tl-md border-t-4 border-l-4 border-green-500"
                                    ></div>
                                    <div
                                        class="absolute -top-1 -right-1 h-6 w-6 rounded-tr-md border-t-4 border-r-4 border-green-500"
                                    ></div>
                                    <div
                                        class="absolute -bottom-1 -left-1 h-6 w-6 rounded-bl-md border-b-4 border-l-4 border-green-500"
                                    ></div>
                                    <div
                                        class="absolute -right-1 -bottom-1 h-6 w-6 rounded-br-md border-r-4 border-b-4 border-green-500"
                                    ></div>
                                </div>
                            </div>

                            <div
                                v-if="!capturedImage"
                                class="pointer-events-none absolute z-10 h-8 w-8 animate-ping rounded-full border border-green-400 bg-green-400/20"
                            ></div>

                            <!-- Unsecure contexts placeholder or no camera -->
                            <div
                                v-if="!cameraActive"
                                class="absolute inset-0 flex flex-col items-center justify-center bg-neutral-900 p-4 text-center"
                            >
                                <ImageIcon
                                    class="mb-2 h-12 w-12 text-neutral-600"
                                />
                                <span
                                    class="text-xs font-semibold text-neutral-300"
                                    >Kamera tidak aktif</span
                                >
                                <p
                                    class="mt-1 max-w-[200px] text-[10px] text-neutral-500"
                                >
                                    Uji di localhost atau ketuk tombol di bawah
                                    untuk unggah file manual.
                                </p>

                                <label
                                    class="mt-4 flex cursor-pointer items-center gap-1 rounded-lg border border-neutral-700 bg-neutral-800 px-3 py-1.5 text-[10px] font-bold text-white transition-colors hover:bg-neutral-700"
                                >
                                    <ImageIcon class="h-3 w-3" /> Pilih Gambar
                                    Manual
                                    <input
                                        type="file"
                                        accept="image/*"
                                        class="hidden"
                                        @change="handleImageUpload"
                                    />
                                </label>
                            </div>

                            <!-- Scanning Loader -->
                            <div
                                v-if="isAnalyzing"
                                class="absolute inset-0 z-20 flex flex-col items-center justify-center bg-black/75 backdrop-blur-xs"
                            >
                                <div class="flex flex-col items-center gap-2">
                                    <div
                                        class="h-10 w-10 animate-spin rounded-full border-4 border-primary border-t-transparent"
                                    ></div>
                                    <span
                                        class="flex items-center gap-1 text-xs font-bold text-white"
                                    >
                                        <Sparkles
                                            class="h-3.5 w-3.5 animate-pulse text-yellow-400"
                                        />
                                        Menganalisis...
                                    </span>
                                </div>
                            </div>
                        </div>

                        <p
                            class="mt-2 text-center text-[10px] font-medium text-muted-foreground"
                        >
                            Arahkan Kamera ke Produk & Ketuk Shutter
                        </p>

                        <!-- Camera Action Buttons -->
                        <div
                            class="mt-3 flex shrink-0 items-center justify-center gap-6"
                        >
                            <!-- Image file trigger fallback -->
                            <label
                                class="cursor-pointer rounded-full bg-secondary p-3 text-secondary-foreground shadow-xs transition-colors hover:bg-muted"
                                title="Upload Image"
                            >
                                <ImageIcon class="h-5 w-5" />
                                <input
                                    type="file"
                                    accept="image/*"
                                    class="hidden"
                                    @change="handleImageUpload"
                                />
                            </label>

                            <!-- Camera Shutter Trigger -->
                            <button
                                @click="captureAndAnalyze"
                                :disabled="!cameraActive || isAnalyzing"
                                class="flex h-16 w-16 items-center justify-center rounded-full border-4 border-neutral-200 bg-white shadow-md transition-all hover:border-neutral-300 focus:outline-none active:scale-95"
                            >
                                <div
                                    class="flex h-12 w-12 items-center justify-center rounded-full bg-primary text-white"
                                >
                                    <Camera class="h-6 w-6" />
                                </div>
                            </button>

                            <!-- Refresh Camera connection -->
                            <button
                                @click="initCamera()"
                                class="rounded-full bg-secondary p-3 text-secondary-foreground shadow-xs transition-colors hover:bg-muted"
                                title="Reload Camera"
                            >
                                <RefreshCw class="h-5 w-5" />
                            </button>
                        </div>
                    </div>

                    <!-- Gemini Match Output overlay -->
                    <div
                        v-if="scanMatch"
                        class="animate-in px-3 pb-3 duration-200 slide-in-from-bottom-3"
                    >
                        <div
                            :class="[
                                'relative flex flex-col gap-3 rounded-xl border p-3.5 shadow-sm',
                                scanMatch.success
                                    ? 'border-green-500/20 bg-green-500/10 text-green-700 dark:text-green-400'
                                    : 'border-red-500/20 bg-red-500/10 text-red-700',
                            ]"
                        >
                            <button
                                @click="scanMatch = null; capturedImage = null"
                                class="absolute top-2 right-2 text-muted-foreground hover:text-foreground"
                            >
                                <X class="h-4 w-4" />
                            </button>

                            <div v-if="scanMatch.success && scanMatch.matches && scanMatch.matches.length > 0" class="flex flex-col gap-2.5 w-full pr-6 pt-1">
                                <span class="rounded bg-green-500/20 px-1.5 py-0.5 text-[9px] font-bold text-green-700 self-start">
                                    {{ scanMatch.matches.length }} Jenis Produk Terdeteksi
                                </span>
                                
                                <div class="flex flex-col gap-2 max-h-[180px] overflow-y-auto pr-1">
                                    <div 
                                        v-for="(match, idx) in scanMatch.matches" 
                                        :key="idx"
                                        class="flex items-start justify-between gap-2 border-b border-green-500/10 pb-1.5 last:border-0 last:pb-0"
                                    >
                                        <div class="flex-1">
                                            <h4 class="text-xs font-bold text-foreground">
                                                {{ match.qty }}x {{ match.product.nama }}
                                            </h4>
                                            <p class="text-[10px] text-muted-foreground line-clamp-2 mt-0.5 leading-relaxed">
                                                {{ match.reason }}
                                            </p>
                                        </div>
                                        <div class="text-right shrink-0">
                                            <span class="text-xs font-bold text-foreground block">
                                                {{ formatRupiah(match.product.harga_jual * match.qty) }}
                                            </span>
                                            <span class="text-[9px] text-muted-foreground mt-0.5 block">
                                                @{{ formatRupiah(match.product.harga_jual) }}
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <button
                                    @click="addMatchesToCart"
                                    class="w-full mt-1.5 flex items-center justify-center gap-1.5 rounded-lg bg-primary py-2 px-3 text-xs font-bold text-primary-foreground shadow-sm transition-opacity hover:opacity-90 active:scale-98"
                                >
                                    Masukkan Semua ke Keranjang
                                </button>
                            </div>

                            <div v-else class="flex-1 pt-1 pr-6">
                                <span class="rounded bg-red-500/20 px-1.5 py-0.5 text-[9px] font-bold text-red-700">
                                    Gagal
                                </span>
                                <p class="mt-1 text-xs leading-relaxed text-foreground">
                                    {{ scanMatch.reason || 'Tidak ada produk yang berhasil dikenali.' }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- TAB B: MANUAL PRODUCTS GRID -->
                <div
                    v-if="activeTab === 'products'"
                    class="flex flex-1 animate-in flex-col gap-3 p-3 duration-200 fade-in"
                >
                    <div class="relative">
                        <Search
                            class="absolute top-3.5 left-3 h-4.5 w-4.5 text-muted-foreground"
                        />
                        <Input
                            type="text"
                            placeholder="Cari barcode / nama..."
                            v-model="searchQuery"
                            class="h-11 pl-10 text-sm rounded-xl"
                        />
                    </div>

                    <!-- Category Tab bar -->
                    <div
                        class="flex shrink-0 scrollbar-thin gap-1 overflow-x-auto pb-1"
                    >
                        <button
                            @click="selectedCategory = null"
                            :class="[
                                'shrink-0 rounded-full border px-3 py-1 text-[10px] font-bold',
                                selectedCategory === null
                                    ? 'border-primary bg-primary text-primary-foreground'
                                    : 'border-border bg-card text-muted-foreground',
                            ]"
                        >
                            Semua
                        </button>
                        <button
                            v-for="cat in categories"
                            :key="cat.id"
                            @click="selectedCategory = cat.id"
                            :class="[
                                'shrink-0 rounded-full border px-3 py-1 text-[10px] font-bold',
                                selectedCategory === cat.id
                                    ? 'border-primary bg-primary text-primary-foreground'
                                    : 'border-border bg-card text-muted-foreground',
                            ]"
                        >
                            {{ cat.nama }}
                        </button>
                    </div>

                    <!-- Product Items Grid -->
                    <div class="grid grid-cols-2 gap-2 overflow-y-auto pr-1">
                        <div
                            v-for="p in products"
                            :key="p.id"
                            @click="addToCart(p)"
                            class="relative flex cursor-pointer flex-col justify-between rounded-lg border bg-card p-2.5 transition-all hover:border-primary"
                        >
                            <div>
                                <h4 class="line-clamp-2 text-[11px] font-bold">
                                    {{ p.nama }}
                                </h4>
                                <span
                                    class="mt-0.5 block font-mono text-[9px] text-muted-foreground"
                                    >{{ p.kode }}</span
                                >
                            </div>
                            <div
                                class="mt-3 flex items-center justify-between border-t border-border/60 pt-1.5"
                            >
                                <span class="text-xs font-bold text-primary">{{
                                    formatRupiah(p.harga_jual)
                                }}</span>
                                <span class="text-[9px] text-muted-foreground"
                                    >Stok: {{ p.stok }}</span
                                >
                            </div>
                        </div>
                    </div>
                </div>

                <!-- TAB C: TRANSACTION HISTORY -->
                <div
                    v-if="activeTab === 'transactions'"
                    class="flex flex-1 animate-in flex-col gap-3 overflow-y-auto p-3 duration-200 fade-in"
                >
                    <h3 class="text-sm font-bold text-foreground">
                        Riwayat Transaksi
                    </h3>

                    <div
                        v-if="recentTransactions.length === 0"
                        class="flex flex-col items-center justify-center py-12 text-muted-foreground"
                    >
                        <HistoryIcon class="mb-1 h-8 w-8 text-neutral-400" />
                        <span class="text-xs">Belum ada transaksi</span>
                    </div>

                    <div
                        v-else
                        v-for="trx in recentTransactions"
                        :key="trx.id"
                        @click="
                            activeReceipt = trx;
                            showReceiptModal = true;
                        "
                        class="flex cursor-pointer items-center justify-between rounded-lg border bg-card p-3 hover:border-primary"
                    >
                        <div>
                            <span
                                class="block font-mono text-xs font-bold text-foreground"
                                >{{ trx.kode }}</span
                            >
                            <span
                                class="mt-0.5 block text-[10px] text-muted-foreground"
                            >
                                {{
                                    new Date(
                                        trx.tanggaltransaksi,
                                    ).toLocaleDateString('id-ID')
                                }}
                            </span>
                        </div>
                        <div class="text-right">
                            <span
                                class="block text-xs font-bold text-primary"
                                >{{ formatRupiah(trx.total) }}</span
                            >
                            <span
                                class="mt-0.5 block text-[9px] text-muted-foreground"
                                >Lihat Struk</span
                            >
                        </div>
                    </div>
                </div>

                <!-- TAB D: SETTINGS -->
                <div
                    v-if="activeTab === 'settings'"
                    class="flex flex-1 animate-in flex-col gap-4 p-4 duration-200 fade-in"
                >
                    <h3 class="text-sm font-bold text-foreground">
                        Pengaturan
                    </h3>

                    <!-- Account Config -->
                    <div
                        class="flex flex-col gap-3 rounded-xl border bg-card p-3"
                    >
                        <span class="text-xs font-bold text-muted-foreground"
                            >AKUN KASIR</span
                        >
                        <div class="flex items-center gap-3">
                            <div
                                class="flex h-10 w-10 items-center justify-center rounded-full bg-primary/10 font-bold text-primary"
                            >
                                {{
                                    $page.props.auth.user
                                        ? $page.props.auth.user.name.charAt(0)
                                        : 'G'
                                }}
                            </div>
                            <div>
                                <span
                                    class="block text-xs font-bold text-foreground"
                                    >{{
                                        $page.props.auth.user
                                            ? $page.props.auth.user.name
                                            : 'Kasir Tamu (Guest)'
                                    }}</span
                                >
                                <span
                                    class="block text-[10px] text-muted-foreground"
                                    >{{
                                        $page.props.auth.user
                                            ? $page.props.auth.user.email
                                            : 'Belum masuk akun'
                                    }}</span
                                >
                            </div>
                        </div>

                        <div class="flex justify-end border-t pt-2">
                            <Link
                                v-if="!$page.props.auth.user"
                                href="/login"
                                class="rounded-lg bg-primary px-3 py-1.5 text-center text-xs font-bold text-primary-foreground"
                            >
                                Masuk Akun
                            </Link>
                        </div>
                    </div>

                    <!-- System Info Config -->
                    <div
                        class="flex flex-col gap-2.5 rounded-xl border bg-card p-3"
                    >
                        <span class="text-xs font-bold text-muted-foreground"
                            >STATUS SISTEM</span
                        >
                        <div class="flex justify-between text-xs">
                            <span>Koneksi Database</span>
                            <span class="font-bold text-green-500"
                                >SQLite (Aktif)</span
                            >
                        </div>
                        <div class="flex justify-between text-xs">
                            <span>Kredensial Gemini API</span>
                            <span
                                :class="
                                    apiKeyConfigured
                                        ? 'font-bold text-green-500'
                                        : 'font-bold text-yellow-600'
                                "
                            >
                                {{
                                    apiKeyConfigured
                                        ? 'Terkonfigurasi'
                                        : 'Belum Terpasang'
                                }}
                            </span>
                        </div>
                    </div>
                </div>
            </main>

            <!-- 3. Bottom Cart Drawer Pill (Pops up from bottom above nav) -->
            <div
                v-if="!isCartEmpty && activeTab === 'home'"
                class="absolute bottom-[88px] left-4 right-4 z-20 flex animate-in justify-center slide-in-from-bottom-2"
            >
                <button
                    @click="showCartDrawer = true"
                    class="flex w-full items-center justify-between rounded-full bg-orange-600 px-4 py-2.5 text-xs font-bold text-white shadow-lg transition-colors hover:bg-orange-700 active:scale-95"
                >
                    <span>LIHAT KERANJANG</span>
                    <span
                        >{{ cartCount }} Barang |
                        {{ formatRupiah(cartTotal) }}</span
                    >
                </button>
            </div>

            <!-- 4. Bottom Tab Navigation Bar -->
            <nav
                class="absolute bottom-5 left-4 right-4 z-25 grid h-14 grid-cols-4 items-center justify-center rounded-2xl border border-border/85 bg-card/90 backdrop-blur-md shadow-lg"
            >
                <button
                    @click="activeTab = 'home'"
                    :class="[
                        'relative flex h-full flex-col items-center justify-center gap-0.5 transition-colors',
                        activeTab === 'home'
                            ? 'text-orange-600'
                            : 'text-muted-foreground',
                    ]"
                >
                    <span
                        v-if="activeTab === 'home'"
                        class="absolute inset-x-4 top-0 h-0.5 rounded-full bg-orange-600"
                    ></span>
                    <HomeIcon class="h-4.5 w-4.5" />
                    <span class="text-[9px] font-semibold">Home</span>
                </button>

                <button
                    @click="activeTab = 'products'"
                    :class="[
                        'relative flex h-full flex-col items-center justify-center gap-0.5 transition-colors',
                        activeTab === 'products'
                            ? 'text-orange-600'
                            : 'text-muted-foreground',
                    ]"
                >
                    <span
                        v-if="activeTab === 'products'"
                        class="absolute inset-x-4 top-0 h-0.5 rounded-full bg-orange-600"
                    ></span>
                    <PackageIcon class="h-4.5 w-4.5" />
                    <span class="text-[9px] font-semibold">Products</span>
                </button>

                <button
                    @click="activeTab = 'transactions'"
                    :class="[
                        'relative flex h-full flex-col items-center justify-center gap-0.5 transition-colors',
                        activeTab === 'transactions'
                            ? 'text-orange-600'
                            : 'text-muted-foreground',
                    ]"
                >
                    <span
                        v-if="activeTab === 'transactions'"
                        class="absolute inset-x-4 top-0 h-0.5 rounded-full bg-orange-600"
                    ></span>
                    <HistoryIcon class="h-4.5 w-4.5" />
                    <span class="text-[9px] font-semibold">Transaction</span>
                </button>

                <button
                    @click="activeTab = 'settings'"
                    :class="[
                        'relative flex h-full flex-col items-center justify-center gap-0.5 transition-colors',
                        activeTab === 'settings'
                            ? 'text-orange-600'
                            : 'text-muted-foreground',
                    ]"
                >
                    <span
                        v-if="activeTab === 'settings'"
                        class="absolute inset-x-4 top-0 h-0.5 rounded-full bg-orange-600"
                    ></span>
                    <SettingsIcon class="h-4.5 w-4.5" />
                    <span class="text-[9px] font-semibold">Settings</span>
                </button>
            </nav>

            <!-- 5. CART DRAWER BOTTOM SHEET (Inside App Layout) -->
            <div
                v-if="showCartDrawer"
                class="absolute inset-0 z-35 flex animate-in flex-col justify-end bg-black/60 fade-in"
                @click.self="showCartDrawer = false"
            >
                <div
                    class="flex max-h-[75%] w-full animate-in flex-col rounded-t-2xl bg-card duration-200 slide-in-from-bottom-10"
                >
                    <div
                        class="flex shrink-0 items-center justify-between border-b bg-muted/20 p-3"
                    >
                        <span
                            class="flex items-center gap-1.5 text-xs font-bold text-foreground"
                        >
                            <ShoppingCart class="h-4.5 w-4.5" /> Detail Belanja
                        </span>
                        <button
                            @click="showCartDrawer = false"
                            class="rounded-full p-1 text-muted-foreground hover:bg-muted"
                        >
                            <X class="h-4.5 w-4.5" />
                        </button>
                    </div>

                    <div class="flex flex-1 flex-col gap-2 overflow-y-auto p-3">
                        <div
                            v-for="item in cart"
                            :key="item.product.id"
                            class="flex items-center justify-between rounded-lg border bg-muted/10 p-2.5"
                        >
                            <div class="flex-1 pr-2">
                                <h4
                                    class="line-clamp-1 text-xs font-bold text-foreground"
                                >
                                    {{ item.product.nama }}
                                </h4>
                                <span
                                    class="mt-0.5 block font-mono text-[10px] text-muted-foreground"
                                    >{{ item.product.kode }}</span
                                >
                                <span
                                    class="mt-0.5 block text-xs font-bold text-primary"
                                    >{{
                                        formatRupiah(item.product.harga_jual)
                                    }}</span
                                >
                            </div>
                            <div
                                class="flex items-center gap-1 rounded-md border bg-background p-1"
                            >
                                <button
                                    @click="updateQty(item.product.id, -1)"
                                    class="rounded p-1 text-muted-foreground hover:bg-muted"
                                >
                                    <Minus class="h-3 w-3" />
                                </button>
                                <span
                                    class="w-5 text-center text-xs font-bold text-foreground"
                                    >{{ item.qty }}</span
                                >
                                <button
                                    @click="updateQty(item.product.id, 1)"
                                    class="rounded p-1 text-muted-foreground hover:bg-muted"
                                >
                                    <Plus class="h-3 w-3" />
                                </button>
                            </div>
                            <div
                                class="min-w-[70px] pl-2 text-right text-xs font-bold text-foreground"
                            >
                                {{
                                    formatRupiah(
                                        item.product.harga_jual * item.qty,
                                    )
                                }}
                            </div>
                        </div>
                    </div>

                    <!-- Checkout & calculation Area -->
                    <div
                        class="flex shrink-0 flex-col gap-3 border-t bg-muted/20 p-3"
                    >
                        <div
                            class="flex items-center justify-between text-xs font-bold"
                        >
                            <span>TOTAL BELANJA</span>
                            <span class="text-sm font-extrabold text-primary">{{
                                formatRupiah(cartTotal)
                            }}</span>
                        </div>

                        <!-- Cash Payment Input -->
                        <div class="flex flex-col gap-1">
                            <label
                                class="text-[10px] font-bold text-muted-foreground"
                                >NOMINAL BAYAR CASH</label
                            >
                            <div class="relative">
                                <span
                                    class="absolute top-1.5 left-2.5 text-xs font-bold text-muted-foreground"
                                    >Rp</span
                                >
                                <Input
                                    type="number"
                                    v-model.number="payAmount"
                                    placeholder="Jumlah bayar..."
                                    class="h-8 pl-7 text-xs font-bold"
                                />
                            </div>

                            <div class="mt-1 flex flex-col gap-1.5">
                                <button
                                    @click="setQuickPay(cartTotal)"
                                    class="w-full h-10 flex items-center justify-center rounded-lg border border-primary/35 bg-primary/5 hover:bg-primary/10 text-xs font-bold text-primary active:scale-[0.98] transition-all"
                                >
                                    Uang Pas ({{ formatRupiah(cartTotal) }})
                                </button>
                                <div class="grid grid-cols-3 gap-1.5">
                                    <button
                                        v-for="amt in [20000, 50000, 100000]"
                                        :key="amt"
                                        v-show="amt > cartTotal"
                                        @click="setQuickPay(amt)"
                                        class="h-8 rounded-lg border bg-background text-[10px] font-bold hover:border-primary active:scale-[0.98] transition-all"
                                    >
                                        {{ formatRupiah(amt) }}
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Kembalian -->
                        <div
                            v-if="payAmount !== null && payAmount >= cartTotal"
                            class="flex justify-between rounded border border-primary/10 bg-primary/5 p-2 text-xs"
                        >
                            <span class="font-bold text-primary"
                                >KEMBALIAN</span
                            >
                            <span class="font-extrabold text-primary">{{
                                formatRupiah(changeAmount)
                            }}</span>
                        </div>

                        <!-- Submit -->
                        <Button
                            @click="checkoutTransaction"
                            :disabled="
                                payAmount === null ||
                                payAmount < cartTotal ||
                                isAnalyzing
                            "
                            class="h-9 w-full bg-primary text-xs font-bold text-primary-foreground"
                        >
                            {{
                                isAnalyzing
                                    ? 'Memproses...'
                                    : 'PROSES TRANSAKSI'
                            }}
                        </Button>
                    </div>
                </div>
            </div>

            <!-- 6. DIALOG RECEIPT PRINT POPUP (Inside App Layout) -->
            <div
                v-if="showReceiptModal && activeReceipt"
                class="absolute inset-0 z-40 flex animate-in items-center justify-center bg-black/60 p-3 fade-in"
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
                        id="receipt-print-area"
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
                                <div>Jl. Raya Tayu-Jepara Km 7</div>
                                <div>depan Kantor Pos Ngablak</div>
                                <div class="mt-0.5">HP: 085201454015</div>
                            </div>
                            <div
                                class="my-2 border-b border-dashed border-black"
                            ></div>

                            <!-- Meta Info -->
                            <div class="text-left text-[9px] leading-relaxed">
                                <div class="flex">
                                    <span class="inline-block w-[90px]"
                                        >No Transaksi</span
                                    >
                                    <span>: {{ activeReceipt.kode }}</span>
                                </div>
                                <div class="mt-0.5 flex">
                                    <span class="inline-block w-[90px]"
                                        >Tanggal</span
                                    >
                                    <span
                                        >:
                                        {{
                                            formatDate(
                                                activeReceipt.tanggaltransaksi,
                                            )
                                        }}</span
                                    >
                                </div>
                            </div>
                            <div
                                class="my-2 border-b border-dashed border-black"
                            ></div>

                            <!-- Table Header -->
                            <div
                                class="flex justify-between pb-1 text-[9px] font-bold"
                            >
                                <span>Produk</span>
                                <span>Subtotal</span>
                            </div>

                            <!-- Table Items -->
                            <div
                                class="flex flex-col gap-1.5 pt-1 text-left text-[9px]"
                            >
                                <div
                                    v-for="det in activeReceipt.details"
                                    :key="det.id"
                                >
                                    <div>{{ det.produk.nama }}</div>
                                    <div
                                        class="mt-0.5 flex justify-between font-mono text-[9px]"
                                    >
                                        <span
                                            >{{ formatNumber(det.harga) }} x
                                            {{ det.jumlah }}</span
                                        >
                                        <span>{{
                                            formatNumber(det.subtotal)
                                        }}</span>
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
                                <span>{{
                                    formatNumber(activeReceipt.total)
                                }}</span>
                            </div>
                            <div class="mt-1 flex justify-between text-[9px]">
                                <span>BAYAR CASH</span>
                                <span>{{
                                    formatNumber(activeReceipt.bayar)
                                }}</span>
                            </div>
                            <div class="mt-0.5 flex justify-between text-[9px]">
                                <span>KEMBALIAN</span>
                                <span>{{
                                    formatNumber(activeReceipt.kembalian)
                                }}</span>
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
                                    Barang yang sudah dibeli tidak dapat
                                    ditukar/dikembalikan.
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
        </div>
    </div>
</template>

<style>
/* Remove mockup frames completely for a clean look */
@media (max-width: 767px) {
    .bg-neutral-100 {
        background-color: transparent !important;
    }
    .w-full.max-w-md.h-\[100dvh\] {
        max-width: 100% !important;
        height: 100dvh !important;
        box-shadow: none !important;
        border: none !important;
        border-radius: 0 !important;
    }
}
</style>
