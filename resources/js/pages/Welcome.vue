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
    ArrowRight
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
    apiKeyConfigured: boolean;
}>();

// Active Tab View: 'home' | 'products' | 'transactions' | 'settings'
const activeTab = ref<'home' | 'products' | 'transactions' | 'settings'>('home');

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
const recentTransactions = ref<Receipt[]>([]);

// Scanning Match Result Overlay
const scanMatch = ref<{
    success: boolean;
    product: Product | null;
    confidence?: number;
    reason?: string;
} | null>(null);

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
        minimumFractionDigits: 0
    }).format(value);
};

const formatNumber = (value: number) => {
    return new Intl.NumberFormat('id-ID').format(value);
};

const formatDate = (dateStr: string) => {
    if (!dateStr) return '';
    const date = new Date(dateStr);
    const months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    const day = String(date.getDate()).padStart(2, '0');
    const month = months[date.getMonth()];
    const year = date.getFullYear();
    return `${day} ${month} ${year}`;
};

// Cart Computeds
const cartTotal = computed(() => {
    return cart.value.reduce((total, item) => total + (item.product.harga_jual * item.qty), 0);
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
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
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
            video: deviceId ? { deviceId: { exact: deviceId } } : { facingMode: 'environment' },
            audio: false
        };

        const mediaStream = await navigator.mediaDevices.getUserMedia(constraints);
        stream.value = mediaStream;
        
        if (videoRef.value) {
            videoRef.value.srcObject = mediaStream;
            videoRef.value.play();
        }
        
        cameraActive.value = true;
        
        const devices = await navigator.mediaDevices.enumerateDevices();
        cameraDevices.value = devices.filter(device => device.kind === 'videoinput');
        
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
        stream.value.getTracks().forEach(track => track.stop());
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

            const token = getCsrfToken();
            const response = await fetch('/api/pos/analyze', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-XSRF-TOKEN': token || ''
                },
                body: JSON.stringify({ image: base64Image })
            });

            const data = await response.json();

            if (response.ok && data.success) {
                const matchedProduct = data.product as Product;
                
                scanMatch.value = {
                    success: true,
                    product: matchedProduct,
                    confidence: data.confidence,
                    reason: data.reason
                };
            } else {
                scanMatch.value = {
                    success: false,
                    product: null,
                    reason: data.message || 'Produk tidak dikenali.'
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
            
            const token = getCsrfToken();
            const response = await fetch('/api/pos/analyze', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-XSRF-TOKEN': token || ''
                },
                body: JSON.stringify({ image: base64Image })
            });

            const data = await response.json();

            if (response.ok && data.success) {
                const matchedProduct = data.product as Product;
                scanMatch.value = {
                    success: true,
                    product: matchedProduct,
                    confidence: data.confidence,
                    reason: data.reason
                };
            } else {
                scanMatch.value = {
                    success: false,
                    product: null,
                    reason: data.message || 'Produk tidak dikenali.'
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
    const existingIndex = cart.value.findIndex(item => item.product.id === product.id);
    
    if (existingIndex > -1) {
        if (cart.value[existingIndex].qty < product.stok) {
            cart.value[existingIndex].qty += 1;
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
    scanMatch.value = null;
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
            items: cart.value.map(item => ({
                id: item.product.id,
                qty: item.qty,
                harga: item.product.harga_jual
            })),
            total: cartTotal.value,
            bayar: payAmount.value
        };

        const token = getCsrfToken();
        const response = await fetch('/api/pos/checkout', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': token || ''
            },
            body: JSON.stringify(payload)
        });

        const data = await response.json();

        if (response.ok && data.success) {
            activeReceipt.value = data.receipt;
            recentTransactions.value.unshift(data.receipt);
            showReceiptModal.value = true;
            showCartDrawer.value = false;
            
            cart.value.forEach(item => {
                const prod = products.value.find(p => p.id === item.product.id);
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
    const index = cart.value.findIndex(item => item.product.id === productId);
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
    <div class="min-h-screen bg-neutral-100 dark:bg-neutral-950 flex items-center justify-center antialiased">
        
        <!-- MOBILE SCREEN CONTAINER -->
        <!-- On Desktop: Centers as a nice app card | On Mobile: Fills 100% of viewport -->
        <div class="w-full max-w-md h-screen md:h-[90vh] bg-background md:rounded-2xl md:shadow-lg md:border border-border overflow-hidden flex flex-col relative">
            
            <!-- 1. Header -->
            <header class="pt-4 pb-3 px-4 border-b border-border bg-card flex items-center justify-between shrink-0">
                <div class="flex items-center gap-2">
                    <div class="bg-primary/10 p-1.5 rounded-lg text-primary">
                        <ShoppingCart class="w-5 h-5" />
                    </div>
                    <span class="font-extrabold text-sm tracking-wide text-foreground">
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
                        class="text-xs font-semibold px-3 py-1.5 rounded-lg bg-secondary text-secondary-foreground hover:bg-muted transition-colors flex items-center gap-1"
                        title="Log Out"
                    >
                        <LogOutIcon class="w-3.5 h-3.5" /> Logout
                    </Link>
                    <Link 
                        v-else 
                        href="/login"
                        class="text-xs font-bold px-3 py-1.5 rounded-lg bg-primary text-primary-foreground hover:opacity-90 transition-opacity flex items-center gap-1"
                        title="Log In"
                    >
                        <LogInIcon class="w-3.5 h-3.5" /> Login
                    </Link>
                </div>
            </header>

            <!-- 2. Screen View Content -->
            <main class="flex-1 overflow-y-auto relative flex flex-col bg-muted/5 pb-20">
                
                <!-- TAB A: HOME (Camera Scanner View) -->
                <div v-if="activeTab === 'home'" class="flex flex-col flex-1">
                    
                    <!-- Search bar link -->
                    <div class="p-3">
                        <div class="relative" @click="activeTab = 'products'">
                            <Search class="absolute left-3 top-2.5 w-4.5 h-4.5 text-muted-foreground" />
                            <Input 
                                type="text" 
                                placeholder="Cari Nama Produk..." 
                                class="pl-9 h-10 text-xs rounded-full border-border bg-card pointer-events-none cursor-pointer"
                            />
                        </div>
                    </div>

                    <!-- WebRTC Camera Stream Frame -->
                    <div class="flex-1 flex flex-col relative px-3 pb-3">
                        <div class="flex-1 min-h-[280px] bg-black rounded-2xl overflow-hidden relative border border-border shadow-inner flex items-center justify-center">
                            <video 
                                ref="videoRef" 
                                autoplay 
                                playsinline 
                                muted 
                                class="w-full h-full object-cover"
                                v-show="cameraActive"
                            ></video>
                            <canvas ref="canvasRef" class="hidden"></canvas>

                            <!-- Glowing Scanner target corners -->
                            <div class="absolute inset-0 z-10 pointer-events-none flex items-center justify-center p-6">
                                <div class="w-48 h-48 border-[3px] border-green-500/80 rounded-2xl relative">
                                    <div class="absolute -top-1 -left-1 w-6 h-6 border-t-4 border-l-4 border-green-500 rounded-tl-md"></div>
                                    <div class="absolute -top-1 -right-1 w-6 h-6 border-t-4 border-r-4 border-green-500 rounded-tr-md"></div>
                                    <div class="absolute -bottom-1 -left-1 w-6 h-6 border-b-4 border-l-4 border-green-500 rounded-bl-md"></div>
                                    <div class="absolute -bottom-1 -right-1 w-6 h-6 border-b-4 border-r-4 border-green-500 rounded-br-md"></div>
                                </div>
                            </div>

                            <div class="absolute pointer-events-none z-10 w-8 h-8 rounded-full border border-green-400 bg-green-400/20 animate-ping"></div>

                            <!-- Unsecure contexts placeholder or no camera -->
                            <div v-if="!cameraActive" class="absolute inset-0 bg-neutral-900 flex flex-col items-center justify-center text-center p-4">
                                <ImageIcon class="w-12 h-12 text-neutral-600 mb-2" />
                                <span class="text-xs font-semibold text-neutral-300">Kamera tidak aktif</span>
                                <p class="text-[10px] text-neutral-500 max-w-[200px] mt-1">Uji di localhost atau ketuk tombol di bawah untuk unggah file manual.</p>
                                
                                <label class="mt-4 px-3 py-1.5 bg-neutral-800 border border-neutral-700 text-[10px] rounded-lg text-white font-bold cursor-pointer hover:bg-neutral-700 transition-colors flex items-center gap-1">
                                    <ImageIcon class="w-3 h-3" /> Pilih Gambar Manual
                                    <input type="file" accept="image/*" class="hidden" @change="handleImageUpload" />
                                </label>
                            </div>

                            <!-- Scanning Loader -->
                            <div v-if="isAnalyzing" class="absolute inset-0 bg-black/75 backdrop-blur-xs flex flex-col items-center justify-center z-20">
                                <div class="flex flex-col items-center gap-2">
                                    <div class="w-10 h-10 rounded-full border-4 border-primary border-t-transparent animate-spin"></div>
                                    <span class="text-white text-xs font-bold flex items-center gap-1">
                                        <Sparkles class="w-3.5 h-3.5 text-yellow-400 animate-pulse" /> Menganalisis...
                                    </span>
                                </div>
                            </div>
                        </div>

                        <p class="text-[10px] text-center text-muted-foreground mt-2 font-medium">
                            Arahkan Kamera ke Produk & Ketuk Shutter
                        </p>

                        <!-- Camera Action Buttons -->
                        <div class="flex items-center justify-center gap-6 mt-3 shrink-0">
                            <!-- Image file trigger fallback -->
                            <label class="p-3 bg-secondary text-secondary-foreground hover:bg-muted rounded-full cursor-pointer transition-colors shadow-xs" title="Upload Image">
                                <ImageIcon class="w-5 h-5" />
                                <input type="file" accept="image/*" class="hidden" @change="handleImageUpload" />
                            </label>

                            <!-- Camera Shutter Trigger -->
                            <button 
                                @click="captureAndAnalyze" 
                                :disabled="!cameraActive || isAnalyzing"
                                class="w-16 h-16 rounded-full bg-white border-4 border-neutral-200 hover:border-neutral-300 shadow-md flex items-center justify-center active:scale-95 transition-all focus:outline-none"
                            >
                                <div class="w-12 h-12 rounded-full bg-primary flex items-center justify-center text-white">
                                    <Camera class="w-6 h-6" />
                                </div>
                            </button>

                            <!-- Refresh Camera connection -->
                            <button 
                                @click="initCamera()" 
                                class="p-3 bg-secondary text-secondary-foreground hover:bg-muted rounded-full transition-colors shadow-xs"
                                title="Reload Camera"
                            >
                                <RefreshCw class="w-5 h-5" />
                            </button>
                        </div>
                    </div>

                    <!-- Gemini Match Output overlay -->
                    <div v-if="scanMatch" class="px-3 pb-3 animate-in slide-in-from-bottom-3 duration-200">
                        <div :class="[
                            'p-3 border rounded-xl flex items-center gap-3 relative shadow-xs',
                            scanMatch.success ? 'bg-green-500/10 border-green-500/20 text-green-700 dark:text-green-400' : 'bg-red-500/10 border-red-500/20 text-red-700'
                        ]">
                            <button @click="scanMatch = null" class="absolute right-2 top-2 text-muted-foreground hover:text-foreground">
                                <X class="w-3.5 h-3.5" />
                            </button>

                            <div v-if="scanMatch.success && scanMatch.product" class="flex items-center justify-between w-full pr-6 pt-1">
                                <div>
                                    <span class="text-[9px] bg-green-500/20 text-green-700 px-1.5 py-0.5 rounded font-bold">Terdeteksi ({{ Math.round((scanMatch.confidence || 0) * 100) }}%)</span>
                                    <h4 class="font-bold text-xs text-foreground mt-1 line-clamp-1">
                                        1x {{ scanMatch.product.nama }}
                                    </h4>
                                    <span class="text-[10px] text-muted-foreground mt-0.5 block">{{ formatRupiah(scanMatch.product.harga_jual) }}</span>
                                </div>
                                <button 
                                    @click="addToCart(scanMatch.product)"
                                    class="text-xs bg-primary text-primary-foreground font-bold px-3 py-1.5 rounded-lg hover:opacity-90 transition-opacity flex items-center gap-1 shadow-xs"
                                >
                                    Tambah <ArrowRight class="w-3 h-3" />
                                </button>
                            </div>

                            <div v-else class="flex-1 pr-6 pt-1">
                                <span class="text-[9px] bg-red-500/20 text-red-700 px-1.5 py-0.5 rounded font-bold">Gagal</span>
                                <p class="text-xs text-foreground mt-1 leading-relaxed">{{ scanMatch.reason }}</p>
                            </div>
                        </div>
                    </div>

                </div>

                <!-- TAB B: MANUAL PRODUCTS GRID -->
                <div v-if="activeTab === 'products'" class="p-3 flex flex-col gap-3 flex-1 animate-in fade-in duration-200">
                    <div class="relative">
                        <Search class="absolute left-3 top-2.5 w-4 h-4 text-muted-foreground" />
                        <Input 
                            type="text" 
                            placeholder="Cari barcode / nama..." 
                            v-model="searchQuery" 
                            class="pl-9 h-9 text-xs"
                        />
                    </div>

                    <!-- Category Tab bar -->
                    <div class="flex gap-1 overflow-x-auto pb-1 shrink-0 scrollbar-thin">
                        <button 
                            @click="selectedCategory = null"
                            :class="[
                                'text-[10px] font-bold px-3 py-1 rounded-full border shrink-0',
                                selectedCategory === null ? 'bg-primary text-primary-foreground border-primary' : 'bg-card text-muted-foreground border-border'
                            ]"
                        >
                            Semua
                        </button>
                        <button 
                            v-for="cat in categories" 
                            :key="cat.id"
                            @click="selectedCategory = cat.id"
                            :class="[
                                'text-[10px] font-bold px-3 py-1 rounded-full border shrink-0',
                                selectedCategory === cat.id ? 'bg-primary text-primary-foreground border-primary' : 'bg-card text-muted-foreground border-border'
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
                            class="bg-card border rounded-lg p-2.5 cursor-pointer hover:border-primary transition-all relative flex flex-col justify-between"
                        >
                            <div>
                                <h4 class="font-bold text-[11px] line-clamp-2">{{ p.nama }}</h4>
                                <span class="text-[9px] text-muted-foreground font-mono mt-0.5 block">{{ p.kode }}</span>
                            </div>
                            <div class="flex justify-between items-center mt-3 pt-1.5 border-t border-border/60">
                                <span class="font-bold text-xs text-primary">{{ formatRupiah(p.harga_jual) }}</span>
                                <span class="text-[9px] text-muted-foreground">Stok: {{ p.stok }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- TAB C: TRANSACTION HISTORY -->
                <div v-if="activeTab === 'transactions'" class="p-3 flex flex-col gap-3 flex-1 overflow-y-auto animate-in fade-in duration-200">
                    <h3 class="font-bold text-sm text-foreground">Riwayat Transaksi</h3>
                    
                    <div v-if="recentTransactions.length === 0" class="flex flex-col items-center justify-center py-12 text-muted-foreground">
                        <HistoryIcon class="w-8 h-8 text-neutral-400 mb-1" />
                        <span class="text-xs">Belum ada transaksi</span>
                    </div>

                    <div 
                        v-else
                        v-for="trx in recentTransactions" 
                        :key="trx.id"
                        @click="activeReceipt = trx; showReceiptModal = true"
                        class="bg-card border rounded-lg p-3 cursor-pointer hover:border-primary flex justify-between items-center"
                    >
                        <div>
                            <span class="font-bold text-xs text-foreground font-mono block">{{ trx.kode }}</span>
                            <span class="text-[10px] text-muted-foreground mt-0.5 block">
                                {{ new Date(trx.tanggaltransaksi).toLocaleDateString('id-ID') }}
                            </span>
                        </div>
                        <div class="text-right">
                            <span class="font-bold text-xs text-primary block">{{ formatRupiah(trx.total) }}</span>
                            <span class="text-[9px] text-muted-foreground mt-0.5 block">Lihat Struk</span>
                        </div>
                    </div>
                </div>

                <!-- TAB D: SETTINGS -->
                <div v-if="activeTab === 'settings'" class="p-4 flex flex-col gap-4 flex-1 animate-in fade-in duration-200">
                    <h3 class="font-bold text-sm text-foreground">Pengaturan</h3>
                    
                    <!-- Account Config -->
                    <div class="bg-card border rounded-xl p-3 flex flex-col gap-3">
                        <span class="text-xs font-bold text-muted-foreground">AKUN KASIR</span>
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-full bg-primary/10 text-primary flex items-center justify-center font-bold">
                                {{ $page.props.auth.user ? $page.props.auth.user.name.charAt(0) : 'G' }}
                            </div>
                            <div>
                                <span class="font-bold text-xs text-foreground block">{{ $page.props.auth.user ? $page.props.auth.user.name : 'Kasir Tamu (Guest)' }}</span>
                                <span class="text-[10px] text-muted-foreground block">{{ $page.props.auth.user ? $page.props.auth.user.email : 'Belum masuk akun' }}</span>
                            </div>
                        </div>

                        <div class="border-t pt-2 flex justify-end">
                            <Link 
                                v-if="!$page.props.auth.user" 
                                href="/login" 
                                class="text-xs bg-primary text-primary-foreground font-bold px-3 py-1.5 rounded-lg text-center"
                            >
                                Masuk Akun
                            </Link>
                        </div>
                    </div>

                    <!-- System Info Config -->
                    <div class="bg-card border rounded-xl p-3 flex flex-col gap-2.5">
                        <span class="text-xs font-bold text-muted-foreground">STATUS SISTEM</span>
                        <div class="flex justify-between text-xs">
                            <span>Koneksi Database</span>
                            <span class="text-green-500 font-bold">SQLite (Aktif)</span>
                        </div>
                        <div class="flex justify-between text-xs">
                            <span>Kredensial Gemini API</span>
                            <span :class="apiKeyConfigured ? 'text-green-500 font-bold' : 'text-yellow-600 font-bold'">
                                {{ apiKeyConfigured ? 'Terkonfigurasi' : 'Belum Terpasang' }}
                            </span>
                        </div>
                    </div>
                </div>

            </main>

            <!-- 3. Bottom Cart Drawer Pill (Pops up from bottom above nav) -->
            <div 
                v-if="!isCartEmpty && activeTab === 'home'" 
                class="absolute bottom-16 left-0 right-0 p-3 z-20 flex justify-center animate-in slide-in-from-bottom-2"
            >
                <button 
                    @click="showCartDrawer = true" 
                    class="w-full bg-orange-600 hover:bg-orange-700 text-white font-bold py-2.5 px-4 rounded-full shadow-lg flex items-center justify-between transition-colors text-xs active:scale-95"
                >
                    <span>LIHAT KERANJANG</span>
                    <span>{{ cartCount }} Barang | {{ formatRupiah(cartTotal) }}</span>
                </button>
            </div>

            <!-- 4. Bottom Tab Navigation Bar -->
            <nav class="absolute bottom-0 left-0 right-0 h-14 bg-card border-t border-border grid grid-cols-4 items-center justify-center z-25">
                <button 
                    @click="activeTab = 'home'"
                    :class="[
                        'flex flex-col items-center justify-center gap-0.5 h-full relative transition-colors',
                        activeTab === 'home' ? 'text-orange-600' : 'text-muted-foreground'
                    ]"
                >
                    <span v-if="activeTab === 'home'" class="absolute top-0 inset-x-4 h-0.5 bg-orange-600 rounded-full"></span>
                    <HomeIcon class="w-4.5 h-4.5" />
                    <span class="text-[9px] font-semibold">Home</span>
                </button>

                <button 
                    @click="activeTab = 'products'"
                    :class="[
                        'flex flex-col items-center justify-center gap-0.5 h-full relative transition-colors',
                        activeTab === 'products' ? 'text-orange-600' : 'text-muted-foreground'
                    ]"
                >
                    <span v-if="activeTab === 'products'" class="absolute top-0 inset-x-4 h-0.5 bg-orange-600 rounded-full"></span>
                    <PackageIcon class="w-4.5 h-4.5" />
                    <span class="text-[9px] font-semibold">Products</span>
                </button>

                <button 
                    @click="activeTab = 'transactions'"
                    :class="[
                        'flex flex-col items-center justify-center gap-0.5 h-full relative transition-colors',
                        activeTab === 'transactions' ? 'text-orange-600' : 'text-muted-foreground'
                    ]"
                >
                    <span v-if="activeTab === 'transactions'" class="absolute top-0 inset-x-4 h-0.5 bg-orange-600 rounded-full"></span>
                    <HistoryIcon class="w-4.5 h-4.5" />
                    <span class="text-[9px] font-semibold">Transaction</span>
                </button>

                <button 
                    @click="activeTab = 'settings'"
                    :class="[
                        'flex flex-col items-center justify-center gap-0.5 h-full relative transition-colors',
                        activeTab === 'settings' ? 'text-orange-600' : 'text-muted-foreground'
                    ]"
                >
                    <span v-if="activeTab === 'settings'" class="absolute top-0 inset-x-4 h-0.5 bg-orange-600 rounded-full"></span>
                    <SettingsIcon class="w-4.5 h-4.5" />
                    <span class="text-[9px] font-semibold">Settings</span>
                </button>
            </nav>

            <!-- 5. CART DRAWER BOTTOM SHEET (Inside App Layout) -->
            <div 
                v-if="showCartDrawer" 
                class="absolute inset-0 bg-black/60 z-35 flex flex-col justify-end animate-in fade-in"
                @click.self="showCartDrawer = false"
            >
                <div class="bg-card w-full max-h-[75%] rounded-t-2xl flex flex-col animate-in slide-in-from-bottom-10 duration-200">
                    
                    <div class="p-3 border-b flex items-center justify-between bg-muted/20 shrink-0">
                        <span class="font-bold text-xs text-foreground flex items-center gap-1.5">
                            <ShoppingCart class="w-4.5 h-4.5" /> Detail Belanja
                        </span>
                        <button @click="showCartDrawer = false" class="p-1 rounded-full hover:bg-muted text-muted-foreground">
                            <X class="w-4.5 h-4.5" />
                        </button>
                    </div>

                    <div class="flex-1 overflow-y-auto p-3 flex flex-col gap-2">
                        <div 
                            v-for="item in cart" 
                            :key="item.product.id"
                            class="flex items-center justify-between border rounded-lg p-2.5 bg-muted/10"
                        >
                            <div class="flex-1 pr-2">
                                <h4 class="font-bold text-xs text-foreground line-clamp-1">{{ item.product.nama }}</h4>
                                <span class="text-[10px] text-muted-foreground font-mono mt-0.5 block">{{ item.product.kode }}</span>
                                <span class="text-xs font-bold text-primary mt-0.5 block">{{ formatRupiah(item.product.harga_jual) }}</span>
                            </div>
                            <div class="flex items-center gap-1 bg-background border p-1 rounded-md">
                                <button @click="updateQty(item.product.id, -1)" class="p-1 hover:bg-muted text-muted-foreground rounded">
                                    <Minus class="w-3 h-3" />
                                </button>
                                <span class="text-xs font-bold w-5 text-center text-foreground">{{ item.qty }}</span>
                                <button @click="updateQty(item.product.id, 1)" class="p-1 hover:bg-muted text-muted-foreground rounded">
                                    <Plus class="w-3 h-3" />
                                </button>
                            </div>
                            <div class="pl-2 font-bold text-xs text-foreground min-w-[70px] text-right">
                                {{ formatRupiah(item.product.harga_jual * item.qty) }}
                            </div>
                        </div>
                    </div>

                    <!-- Checkout & calculation Area -->
                    <div class="border-t p-3 bg-muted/20 flex flex-col gap-3 shrink-0">
                        <div class="flex justify-between items-center font-bold text-xs">
                            <span>TOTAL BELANJA</span>
                            <span class="text-sm text-primary font-extrabold">{{ formatRupiah(cartTotal) }}</span>
                        </div>

                        <!-- Cash Payment Input -->
                        <div class="flex flex-col gap-1">
                            <label class="text-[10px] font-bold text-muted-foreground">NOMINAL BAYAR CASH</label>
                            <div class="relative">
                                <span class="absolute left-2.5 top-1.5 text-xs text-muted-foreground font-bold">Rp</span>
                                <Input 
                                    type="number" 
                                    v-model.number="payAmount" 
                                    placeholder="Jumlah bayar..." 
                                    class="pl-7 h-8 text-xs font-bold"
                                />
                            </div>
                            
                            <div class="grid grid-cols-4 gap-1 mt-1">
                                <button @click="setQuickPay(cartTotal)" class="text-[9px] font-bold py-1 bg-background border rounded hover:border-primary">Uang Pas</button>
                                <button v-for="amt in [20000, 50000, 100000]" :key="amt" v-show="amt > cartTotal" @click="setQuickPay(amt)" class="text-[9px] font-bold py-1 bg-background border rounded hover:border-primary">
                                    {{ formatRupiah(amt) }}
                                </button>
                            </div>
                        </div>

                        <!-- Kembalian -->
                        <div v-if="payAmount !== null && payAmount >= cartTotal" class="flex justify-between text-xs bg-primary/5 p-2 rounded border border-primary/10">
                            <span class="font-bold text-primary">KEMBALIAN</span>
                            <span class="font-extrabold text-primary">{{ formatRupiah(changeAmount) }}</span>
                        </div>

                        <!-- Submit -->
                        <Button 
                            @click="checkoutTransaction"
                            :disabled="payAmount === null || payAmount < cartTotal || isAnalyzing"
                            class="w-full bg-primary text-primary-foreground font-bold h-9 text-xs"
                        >
                            {{ isAnalyzing ? 'Memproses...' : 'PROSES TRANSAKSI' }}
                        </Button>
                    </div>

                </div>
            </div>

            <!-- 6. DIALOG RECEIPT PRINT POPUP (Inside App Layout) -->
            <div 
                v-if="showReceiptModal && activeReceipt" 
                class="absolute inset-0 bg-black/60 z-40 flex items-center justify-center p-3 animate-in fade-in"
            >
                <div class="bg-card w-full max-w-[320px] rounded-xl shadow-lg flex flex-col max-h-[85%] animate-in zoom-in-95 duration-150">
                    <div class="p-3 border-b flex items-center justify-between bg-muted/20 shrink-0">
                        <span class="font-bold text-xs">Transaksi Sukses</span>
                        <button @click="showReceiptModal = false" class="p-1 rounded-full hover:bg-muted text-muted-foreground">
                            <X class="w-4.5 h-4.5" />
                        </button>
                    </div>

                    <!-- Print Container -->
                    <div class="p-4 bg-white overflow-y-auto text-black" id="receipt-print-area">
                        <div class="font-mono text-[10px]">
                            <!-- Header Info -->
                            <div class="text-center font-bold text-xs uppercase tracking-wide">
                                <div>Agen Sosis</div>
                                <div class="mt-0.5">Lancar Manunggal</div>
                            </div>
                            <div class="text-center text-[9px] mt-1.5 leading-tight">
                                <div>Jl. Raya Tayu-Jepara Km 7</div>
                                <div>depan Kantor Pos Ngablak</div>
                                <div class="mt-0.5">HP: 085201454015</div>
                            </div>
                            <div class="border-b border-dashed border-black my-2"></div>

                            <!-- Meta Info -->
                            <div class="text-left text-[9px] leading-relaxed">
                                <div class="flex">
                                    <span class="w-[90px] inline-block">No Transaksi</span>
                                    <span>: {{ activeReceipt.kode }}</span>
                                </div>
                                <div class="flex mt-0.5">
                                    <span class="w-[90px] inline-block">Tanggal</span>
                                    <span>: {{ formatDate(activeReceipt.tanggaltransaksi) }}</span>
                                </div>
                            </div>
                            <div class="border-b border-dashed border-black my-2"></div>

                            <!-- Table Header -->
                            <div class="flex justify-between font-bold text-[9px] pb-1">
                                <span>Produk</span>
                                <span>Subtotal</span>
                            </div>

                            <!-- Table Items -->
                            <div class="flex flex-col gap-1.5 text-left text-[9px] pt-1">
                                <div v-for="det in activeReceipt.details" :key="det.id">
                                    <div>{{ det.produk.nama }}</div>
                                    <div class="flex justify-between font-mono text-[9px] mt-0.5">
                                        <span>{{ formatNumber(det.harga) }} x {{ det.jumlah }}</span>
                                        <span>{{ formatNumber(det.subtotal) }}</span>
                                    </div>
                                </div>
                            </div>

                            <div class="border-b border-dashed border-black my-2"></div>

                            <!-- Calculations / Footer Info -->
                            <div class="flex justify-between font-bold text-[9px]">
                                <span>TOTAL</span>
                                <span>{{ formatNumber(activeReceipt.total) }}</span>
                            </div>
                            <div class="flex justify-between mt-1 text-[9px]">
                                <span>BAYAR CASH</span>
                                <span>{{ formatNumber(activeReceipt.bayar) }}</span>
                            </div>
                            <div class="flex justify-between mt-0.5 text-[9px]">
                                <span>KEMBALIAN</span>
                                <span>{{ formatNumber(activeReceipt.kembalian) }}</span>
                            </div>

                            <div class="border-b border-dashed border-black my-2"></div>
                            
                            <div class="text-center mt-3 leading-tight text-[8px] text-neutral-600">
                                <p class="font-bold text-[9px] text-black">--- TERIMA KASIH ---</p>
                                <p class="mt-0.5">Barang yang sudah dibeli tidak dapat ditukar/dikembalikan.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Print Actions -->
                    <div class="p-3 border-t bg-muted/20 flex gap-2 shrink-0">
                        <Button @click="showReceiptModal = false" variant="outline" class="flex-1 text-xs h-8">
                            Tutup
                        </Button>
                        <Button @click="printReceipt" class="flex-1 text-xs h-8 bg-primary text-primary-foreground flex items-center justify-center gap-1">
                            <Printer class="w-3.5 h-3.5" /> Cetak
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
    .w-full.max-w-md.h-screen {
        max-width: 100% !important;
        height: 100vh !important;
        box-shadow: none !important;
        border: none !important;
        border-radius: 0 !important;
    }
}
</style>
