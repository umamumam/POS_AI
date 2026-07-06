<script setup lang="ts">
import { ref, onMounted, onBeforeUnmount, computed, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
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
    Maximize2
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

// App Layout Config
defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'POS Kasir',
                href: '/pos',
            },
        ],
    },
});

// POS State
const products = ref<Product[]>(props.initialProducts);
const categories = ref<Category[]>(props.categories);
const selectedCategory = ref<number | null>(null);
const searchQuery = ref('');
const cart = ref<CartItem[]>([]);
const payAmount = ref<number | null>(null);

// UI States
const isAnalyzing = ref(false);
const analysisResult = ref<{
    success: boolean;
    confidence?: number;
    reason?: string;
    productName?: string;
} | null>(null);

const showReceiptModal = ref(false);
const activeReceipt = ref<Receipt | null>(null);
const errorMessage = ref('');

// Camera WebRTC State
const videoRef = ref<HTMLVideoElement | null>(null);
const canvasRef = ref<HTMLCanvasElement | null>(null);
const stream = ref<MediaStream | null>(null);
const cameraActive = ref(false);
const cameraDevices = ref<MediaDeviceInfo[]>([]);
const selectedDeviceId = ref<string>('');
const showCameraError = ref(false);

// Format Rupiah Helper
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
    const months = [
        'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun',
        'Jul', 'Agt', 'Sep', 'Okt', 'Nov', 'Des'
    ];
    const day = String(date.getDate()).padStart(2, '0');
    const month = months[date.getMonth()];
    const year = date.getFullYear();
    return `${day} ${month} ${year}`;
};

// Cart Computeds
const cartTotal = computed(() => {
    return cart.value.reduce((total, item) => total + (item.product.harga_jual * item.qty), 0);
});

const changeAmount = computed(() => {
    if (payAmount.value === null || payAmount.value < cartTotal.value) return 0;
    return payAmount.value - cartTotal.value;
});

const isCartEmpty = computed(() => cart.value.length === 0);

// API Helpers
function getCsrfToken() {
    const match = document.cookie.match(new RegExp('(^| )XSRF-TOKEN=([^;]+)'));
    if (match) return decodeURIComponent(match[2]);
    return null;
}

// Fetch products when category or search changes
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
            // The endpoint returns paginated data
            products.value = data.data || data;
        }
    } catch (err) {
        console.error('Gagal mengambil produk:', err);
    }
};

// Debounce search
let searchTimeout: NodeJS.Timeout;
watch([searchQuery, selectedCategory], () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        fetchProducts();
    }, 300);
});

// Camera Controls
const initCamera = async (deviceId?: string) => {
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
        
        // Fetch devices if not already done
        const devices = await navigator.mediaDevices.enumerateDevices();
        cameraDevices.value = devices.filter(device => device.kind === 'videoinput');
        
        // Select active device ID
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

const handleDeviceChange = () => {
    if (selectedDeviceId.value) {
        initCamera(selectedDeviceId.value);
    }
};

// Capture Photo and Analyze using Gemini
const captureAndAnalyze = async () => {
    if (!videoRef.value || !canvasRef.value || isAnalyzing.value) return;

    isAnalyzing.value = true;
    analysisResult.value = null;
    errorMessage.value = '';

    try {
        const video = videoRef.value;
        const canvas = canvasRef.value;
        const context = canvas.getContext('2d');

        if (context) {
            // Match canvas size to video size
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            
            // Draw image on canvas
            context.drawImage(video, 0, 0, canvas.width, canvas.height);
            
            // Convert to Base64 (JPEG format, 0.8 quality to optimize payload size)
            const base64Image = canvas.toDataURL('image/jpeg', 0.8);

            // Send base64 to backend
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
                addToCart(matchedProduct);
                
                analysisResult.value = {
                    success: true,
                    confidence: data.confidence,
                    reason: data.reason,
                    productName: matchedProduct.nama
                };
            } else {
                analysisResult.value = {
                    success: false,
                    reason: data.message || 'Produk tidak dikenali oleh sistem.'
                };
            }
        }
    } catch (err: any) {
        console.error('Analisis gambar gagal:', err);
        errorMessage.value = err.message || 'Terjadi kesalahan saat memproses gambar.';
    } finally {
        isAnalyzing.value = false;
    }
};

// Fallback image upload analysis
const handleImageUpload = async (event: Event) => {
    const target = event.target as HTMLInputElement;
    if (!target.files || target.files.length === 0) return;

    const file = target.files[0];
    const reader = new FileReader();

    isAnalyzing.value = true;
    analysisResult.value = null;
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
                addToCart(matchedProduct);
                
                analysisResult.value = {
                    success: true,
                    confidence: data.confidence,
                    reason: data.reason,
                    productName: matchedProduct.nama
                };
            } else {
                analysisResult.value = {
                    success: false,
                    reason: data.message || 'Produk tidak dikenali oleh sistem.'
                };
            }
        } catch (err: any) {
            console.error('Gagal menganalisis file:', err);
            errorMessage.value = err.message || 'Gagal memproses file.';
        } finally {
            isAnalyzing.value = false;
            // reset file input
            target.value = '';
        }
    };

    reader.readAsDataURL(file);
};

// Cart Operations
const addToCart = (product: Product) => {
    const existingIndex = cart.value.findIndex(item => item.product.id === product.id);
    
    if (existingIndex > -1) {
        if (cart.value[existingIndex].qty < product.stok) {
            cart.value[existingIndex].qty += 1;
        } else {
            alert(`Stok untuk ${product.nama} sudah mencapai batas maksimum (${product.stok} unit).`);
        }
    } else {
        if (product.stok > 0) {
            cart.value.push({ product, qty: 1 });
        } else {
            alert(`Produk ${product.nama} sedang habis (Stok: 0).`);
        }
    }
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
            alert(`Stok untuk ${item.product.nama} tidak mencukupi.`);
        }
    }
};

const removeFromCart = (productId: number) => {
    const index = cart.value.findIndex(item => item.product.id === productId);
    if (index > -1) {
        cart.value.splice(index, 1);
    }
};

const clearCart = () => {
    cart.value = [];
    payAmount.value = null;
    analysisResult.value = null;
};

// Checkout transaction
const checkoutTransaction = async () => {
    if (isCartEmpty.value || isAnalyzing.value) return;

    if (payAmount.value === null || payAmount.value < cartTotal.value) {
        alert('Jumlah bayar kurang dari total belanja.');
        return;
    }

    isAnalyzing.value = true;
    errorMessage.value = '';

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
            showReceiptModal.value = true;
            
            // Deduct stock locally
            cart.value.forEach(item => {
                const prod = products.value.find(p => p.id === item.product.id);
                if (prod) prod.stok -= item.qty;
            });

            // Clear Cart
            clearCart();
        } else {
            errorMessage.value = data.message || 'Transaksi gagal diproses.';
            alert(errorMessage.value);
        }
    } catch (err: any) {
        console.error('Checkout gagal:', err);
        errorMessage.value = err.message || 'Terjadi kesalahan koneksi.';
    } finally {
        isAnalyzing.value = false;
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
                    <title>Nota Transaksi #${activeReceipt.value?.kode || ''}</title>
                    <style>
                        @page {
                            margin: 0;
                        }
                        body {
                            font-family: 'Plus Jakarta Sans', DejaVu Sans, sans-serif;
                            font-size: 15px;
                            line-height: 1.4;
                            padding: 20px;
                            width: 220px;
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
                            padding: 3px 0;
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

// Quick Payment Selectors
const setQuickPay = (amount: number) => {
    payAmount.value = amount;
};

// Lifecycle Hooks
onMounted(() => {
    initCamera();
});

onBeforeUnmount(() => {
    stopCamera();
});
</script>

<template>
    <Head title="POS Kasir Foto" />

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-12 p-1 min-h-[calc(100vh-140px)]">
        
        <!-- LEFT COLUMN: Camera Scanner & Product Catalog (7 columns) -->
        <div class="lg:col-span-7 flex flex-col gap-6">
            
            <!-- CAMERA FEED & SCANNING CARD -->
            <div class="bg-card border border-border rounded-xl shadow-sm overflow-hidden relative">
                <div class="p-4 border-b border-border flex items-center justify-between bg-muted/30">
                    <div class="flex items-center gap-2">
                        <Camera class="w-5 h-5 text-primary" />
                        <h2 class="font-semibold text-sm">Foto Produk Kasir</h2>
                        <span v-if="cameraActive" class="w-2.5 h-2.5 rounded-full bg-green-500 animate-pulse"></span>
                    </div>

                    <div class="flex items-center gap-2">
                        <!-- Camera Selector -->
                        <select 
                            v-if="cameraDevices.length > 1" 
                            v-model="selectedDeviceId" 
                            @change="handleDeviceChange"
                            class="text-xs bg-background border border-input rounded px-2 py-1 focus:outline-none focus:ring-1 focus:ring-ring"
                        >
                            <option v-for="device in cameraDevices" :key="device.deviceId" :value="device.deviceId">
                                {{ device.label || 'Kamera ' + (cameraDevices.indexOf(device) + 1) }}
                            </option>
                        </select>

                        <button 
                            v-if="!cameraActive" 
                            @click="initCamera()" 
                            class="text-xs flex items-center gap-1 bg-primary text-primary-foreground px-2 py-1 rounded hover:opacity-90"
                        >
                            <RefreshCw class="w-3 h-3" /> Aktifkan Kamera
                        </button>
                    </div>
                </div>

                <!-- Video View -->
                <div class="relative bg-black aspect-video flex items-center justify-center overflow-hidden">
                    <video 
                        ref="videoRef" 
                        autoplay 
                        playsinline 
                        muted 
                        class="w-full h-full object-cover"
                        v-show="cameraActive"
                    ></video>
                    
                    <!-- Hidden Canvas for frame capturing -->
                    <canvas ref="canvasRef" class="hidden"></canvas>

                    <!-- Camera Error or Inactive State -->
                    <div v-if="!cameraActive || showCameraError" class="absolute inset-0 flex flex-col items-center justify-center p-6 text-center text-muted-foreground bg-neutral-900">
                        <ImageIcon class="w-12 h-12 mb-3 text-neutral-600" />
                        <p class="text-sm font-medium text-neutral-300">Kamera tidak aktif</p>
                        <p class="text-xs text-neutral-500 max-w-sm mt-1">Izinkan akses kamera di browser Anda atau gunakan alternatif upload gambar produk.</p>
                        
                        <label class="mt-4 px-4 py-2 bg-neutral-800 border border-neutral-700 rounded-lg text-xs font-semibold text-white hover:bg-neutral-700 cursor-pointer flex items-center gap-2 transition-all">
                            <ImageIcon class="w-3.5 h-3.5" /> Unggah Foto Manual
                            <input type="file" accept="image/*" class="hidden" @change="handleImageUpload" />
                        </label>
                    </div>

                    <!-- Scanning laser line overlay when analyzing -->
                    <div v-if="isAnalyzing" class="absolute inset-x-0 h-1 bg-red-500/80 shadow-[0_0_8px_rgba(239,68,68,0.8)] animate-[bounce_2s_infinite]"></div>

                    <!-- Analyzing Overlay Loader -->
                    <div v-if="isAnalyzing" class="absolute inset-0 bg-black/60 backdrop-blur-sm flex flex-col items-center justify-center p-4">
                        <div class="flex flex-col items-center gap-3">
                            <div class="w-12 h-12 rounded-full border-4 border-primary border-t-transparent animate-spin"></div>
                            <span class="text-white font-medium text-sm flex items-center gap-1.5">
                                <Sparkles class="w-4 h-4 text-yellow-400 animate-pulse" /> Gemini menganalisa foto...
                            </span>
                            <span class="text-xs text-gray-400">Mencocokkan produk dengan database...</span>
                        </div>
                    </div>
                </div>

                <!-- Camera Actions / Info -->
                <div class="p-4 bg-muted/10 border-t border-border flex flex-col gap-3">
                    <div class="flex items-center justify-between gap-4">
                        <div class="flex items-center gap-2">
                            <label class="px-3 py-1.5 bg-secondary text-secondary-foreground rounded-lg text-xs font-semibold hover:opacity-90 cursor-pointer flex items-center gap-1.5 transition-all">
                                <ImageIcon class="w-3.5 h-3.5" /> Pilih File Gambar
                                <input type="file" accept="image/*" class="hidden" @change="handleImageUpload" />
                            </label>
                        </div>
                        
                        <Button 
                            v-if="cameraActive" 
                            @click="captureAndAnalyze" 
                            :disabled="isAnalyzing"
                            class="flex items-center gap-2 bg-primary text-primary-foreground font-semibold py-2 px-6 shadow-md"
                        >
                            <Camera class="w-4 h-4" /> Ambil Foto & Analisa
                        </Button>
                    </div>

                    <!-- Notification setup API Warning -->
                    <div v-if="!apiKeyConfigured" class="flex gap-2 p-3 bg-yellow-500/10 text-yellow-600 rounded-lg text-xs border border-yellow-500/20">
                        <AlertCircle class="w-4 h-4 shrink-0 mt-0.5" />
                        <div>
                            <strong>Peringatan:</strong> GEMINI_API_KEY tidak dikonfigurasi di file <code>.env</code>.
                            Pastikan Anda menambahkan <code>GEMINI_API_KEY=your_key</code> agar fitur analisa foto dapat berjalan.
                        </div>
                    </div>

                    <!-- Gemini Match Result Alert -->
                    <div v-if="analysisResult" :class="[
                        'p-3 rounded-lg border text-xs flex gap-2.5 items-start animate-in fade-in slide-in-from-bottom-2',
                        analysisResult.success ? 'bg-green-500/10 border-green-500/20 text-green-700 dark:text-green-400' : 'bg-red-500/10 border-red-500/20 text-red-700 dark:text-red-400'
                    ]">
                        <Sparkles v-if="analysisResult.success" class="w-4.5 h-4.5 text-green-500 shrink-0 mt-0.5" />
                        <AlertCircle v-else class="w-4.5 h-4.5 text-red-500 shrink-0 mt-0.5" />
                        <div class="flex-1">
                            <p class="font-semibold text-sm" v-if="analysisResult.success">
                                Terdeteksi: {{ analysisResult.productName }}
                            </p>
                            <p class="font-semibold text-sm" v-else>
                                Gagal Mengenali Produk
                            </p>
                            <p class="mt-0.5 leading-relaxed text-muted-foreground">{{ analysisResult.reason }}</p>
                            <p class="mt-1 font-mono text-[10px] text-muted-foreground/80" v-if="analysisResult.success && analysisResult.confidence">
                                Kredibilitas: {{ Math.round(analysisResult.confidence * 100) }}%
                            </p>
                        </div>
                        <button @click="analysisResult = null" class="text-muted-foreground hover:text-foreground">
                            <X class="w-3.5 h-3.5" />
                        </button>
                    </div>
                </div>
            </div>

            <!-- PRODUCT CATALOG & GRID -->
            <div class="bg-card border border-border rounded-xl shadow-sm p-4 flex flex-col gap-4 flex-1">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <h3 class="font-bold text-base flex items-center gap-2">
                        <span>Katalog Produk</span>
                        <span class="text-xs bg-muted text-muted-foreground py-0.5 px-2 rounded-full font-normal">
                            {{ products.length }} item
                        </span>
                    </h3>

                    <!-- Search Input -->
                    <div class="relative w-full sm:max-w-[240px]">
                        <Search class="absolute left-3 top-2.5 w-4 h-4 text-muted-foreground" />
                        <Input 
                            type="text" 
                            placeholder="Cari nama atau barcode..." 
                            v-model="searchQuery" 
                            class="pl-9 h-9 text-xs"
                        />
                    </div>
                </div>

                <!-- Category Selector -->
                <div class="flex gap-1.5 overflow-x-auto pb-1.5 scrollbar-thin">
                    <button 
                        @click="selectedCategory = null"
                        :class="[
                            'text-xs font-semibold px-3 py-1.5 rounded-full transition-all shrink-0 border',
                            selectedCategory === null 
                                ? 'bg-primary text-primary-foreground border-primary' 
                                : 'bg-background text-muted-foreground border-border hover:bg-muted/50'
                        ]"
                    >
                        Semua
                    </button>
                    <button 
                        v-for="cat in categories" 
                        :key="cat.id"
                        @click="selectedCategory = cat.id"
                        :class="[
                            'text-xs font-semibold px-3 py-1.5 rounded-full transition-all shrink-0 border',
                            selectedCategory === cat.id 
                                ? 'bg-primary text-primary-foreground border-primary' 
                                : 'bg-background text-muted-foreground border-border hover:bg-muted/50'
                        ]"
                    >
                        {{ cat.nama }}
                    </button>
                </div>

                <!-- Product Grid (Compact Cards) -->
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 overflow-y-auto max-h-[360px] pr-1 scrollbar-thin">
                    <div 
                        v-for="prod in products" 
                        :key="prod.id"
                        @click="addToCart(prod)"
                        :class="[
                            'bg-background border rounded-lg p-3 cursor-pointer flex flex-col justify-between hover:border-primary transition-all duration-200 shadow-sm relative group',
                            prod.stok === 0 ? 'opacity-60 cursor-not-allowed bg-muted/10' : ''
                        ]"
                    >
                        <!-- Add Button Indicator (Group Hover) -->
                        <div class="absolute right-2 top-2 bg-primary text-primary-foreground rounded-full p-1 opacity-0 group-hover:opacity-100 transition-opacity duration-150 shadow-sm">
                            <Plus class="w-3.5 h-3.5" />
                        </div>

                        <div>
                            <span class="text-[10px] bg-secondary text-secondary-foreground px-1.5 py-0.5 rounded font-medium mb-1.5 inline-block">
                                {{ prod.kategori?.nama || 'Lainnya' }}
                            </span>
                            <h4 class="font-bold text-xs line-clamp-2 text-foreground group-hover:text-primary transition-colors">
                                {{ prod.nama }}
                            </h4>
                            <span class="text-[10px] text-muted-foreground font-mono mt-0.5 block">{{ prod.kode }}</span>
                        </div>

                        <div class="mt-4 flex items-center justify-between border-t border-border pt-2 bg-muted/5 -mx-3 -mb-3 px-3 rounded-b-lg">
                            <span class="font-bold text-xs text-primary">
                                {{ formatRupiah(prod.harga_jual) }}
                            </span>
                            <span :class="[
                                'text-[10px] font-semibold',
                                prod.stok <= 10 ? 'text-red-500' : 'text-muted-foreground'
                            ]">
                                Stok: {{ prod.stok }}
                            </span>
                        </div>
                    </div>
                </div>

            </div>

        </div>

        <!-- RIGHT COLUMN: Shopping Cart & Checkout (5 columns) -->
        <div class="lg:col-span-5 bg-card border border-border rounded-xl shadow-sm flex flex-col h-full overflow-hidden">
            
            <!-- Cart Header -->
            <div class="p-4 border-b border-border flex items-center justify-between bg-muted/30">
                <div class="flex items-center gap-2">
                    <ShoppingCart class="w-5 h-5 text-primary" />
                    <h3 class="font-bold text-sm">Keranjang Transaksi</h3>
                </div>
                <button 
                    v-if="!isCartEmpty" 
                    @click="clearCart"
                    class="text-xs text-destructive hover:underline flex items-center gap-1 font-semibold"
                >
                    <Trash2 class="w-3.5 h-3.5" /> Kosongkan
                </button>
            </div>

            <!-- Cart Items (Scrollable) -->
            <div class="flex-1 overflow-y-auto p-4 flex flex-col gap-3 min-h-[220px] max-h-[360px] scrollbar-thin">
                <div v-if="isCartEmpty" class="flex flex-col items-center justify-center py-12 text-muted-foreground flex-1">
                    <ShoppingCart class="w-10 h-10 mb-2 text-neutral-300" />
                    <p class="text-xs font-semibold text-neutral-400">Belum ada barang di keranjang</p>
                    <p class="text-[10px] text-neutral-500 text-center max-w-[200px] mt-0.5">
                        Foto produk menggunakan kamera atau pilih produk di katalog manual.
                    </p>
                </div>

                <div 
                    v-else
                    v-for="item in cart" 
                    :key="item.product.id"
                    class="flex items-center justify-between border border-border rounded-lg p-3 bg-muted/10 hover:bg-muted/20 transition-all duration-150 animate-in fade-in slide-in-from-right-2"
                >
                    <div class="flex-1 pr-3">
                        <h4 class="font-bold text-xs text-foreground line-clamp-1">
                            {{ item.product.nama }}
                        </h4>
                        <span class="text-[10px] text-muted-foreground block font-mono">{{ item.product.kode }}</span>
                        <span class="text-xs font-bold text-primary mt-1 block">
                            {{ formatRupiah(item.product.harga_jual) }}
                        </span>
                    </div>

                    <!-- Quantity Control -->
                    <div class="flex items-center gap-2 bg-background border border-input rounded-lg p-1.5">
                        <button 
                            @click="updateQty(item.product.id, -1)" 
                            class="p-1 hover:bg-muted rounded text-muted-foreground transition-all"
                        >
                            <Minus class="w-3.5 h-3.5" />
                        </button>
                        <span class="text-xs font-bold w-6 text-center text-foreground">{{ item.qty }}</span>
                        <button 
                            @click="updateQty(item.product.id, 1)" 
                            class="p-1 hover:bg-muted rounded text-muted-foreground transition-all"
                        >
                            <Plus class="w-3.5 h-3.5" />
                        </button>
                    </div>

                    <!-- Item Total & Delete -->
                    <div class="flex flex-col items-end pl-3 min-w-[90px]">
                        <span class="font-bold text-xs text-foreground">
                            {{ formatRupiah(item.product.harga_jual * item.qty) }}
                        </span>
                        <button 
                            @click="removeFromCart(item.product.id)" 
                            class="text-[10px] text-destructive hover:underline mt-1 font-semibold flex items-center gap-0.5"
                        >
                            <Trash2 class="w-3 h-3" /> Hapus
                        </button>
                    </div>
                </div>
            </div>

            <!-- Cart Calculation Area -->
            <div class="border-t border-border p-4 bg-muted/25 flex flex-col gap-4 mt-auto">
                <div class="flex flex-col gap-2">
                    <div class="flex justify-between items-center text-xs text-muted-foreground">
                        <span>Total Qty</span>
                        <span class="font-bold">{{ cart.reduce((tot, item) => tot + item.qty, 0) }} item</span>
                    </div>
                    <div class="flex justify-between items-center border-t border-border/60 pt-2">
                        <span class="font-bold text-sm text-foreground">TOTAL BELANJA</span>
                        <span class="font-extrabold text-base text-primary">
                            {{ formatRupiah(cartTotal) }}
                        </span>
                    </div>
                </div>

                <!-- Payment input -->
                <div class="flex flex-col gap-1.5" v-if="!isCartEmpty">
                    <label class="text-xs font-bold text-muted-foreground">JUMLAH BAYAR (CASH)</label>
                    <div class="relative">
                        <span class="absolute left-3 top-2.5 font-bold text-xs text-muted-foreground">Rp</span>
                        <Input 
                            type="number" 
                            v-model.number="payAmount" 
                            placeholder="Masukkan jumlah pembayaran..." 
                            class="pl-8 h-9 text-xs font-bold"
                        />
                    </div>

                    <!-- Quick Pay Suggetions -->
                    <div class="grid grid-cols-4 gap-1.5 mt-1.5">
                        <button 
                            @click="setQuickPay(cartTotal)" 
                            class="text-[10px] font-bold py-1 bg-background border rounded hover:border-primary hover:bg-primary/5 transition-all"
                        >
                            Uang Pas
                        </button>
                        <button 
                            v-for="amt in [20000, 50000, 100000]" 
                            :key="amt"
                            v-show="amt > cartTotal"
                            @click="setQuickPay(amt)"
                            class="text-[10px] font-bold py-1 bg-background border rounded hover:border-primary hover:bg-primary/5 transition-all"
                        >
                            {{ formatRupiah(amt) }}
                        </button>
                    </div>
                </div>

                <!-- Kembalian/Change Output -->
                <div v-if="payAmount !== null && payAmount >= cartTotal && !isCartEmpty" class="flex justify-between items-center p-3 bg-primary/5 rounded-lg border border-primary/10 animate-in fade-in duration-200">
                    <span class="font-bold text-xs text-primary">KEMBALIAN</span>
                    <span class="font-extrabold text-sm text-primary">
                        {{ formatRupiah(changeAmount) }}
                    </span>
                </div>

                <!-- Process Button -->
                <Button 
                    @click="checkoutTransaction" 
                    :disabled="isCartEmpty || payAmount === null || payAmount < cartTotal || isAnalyzing"
                    class="w-full bg-primary text-primary-foreground font-bold py-3 rounded-lg shadow flex items-center justify-center gap-2 hover:opacity-90 transition-all text-xs"
                >
                    <Check class="w-4 h-4" /> 
                    {{ isAnalyzing ? 'Memproses Transaksi...' : 'PROSES TRANSAKSI' }}
                </Button>
            </div>

        </div>

    </div>

    <!-- DIALOG: RECEIPT PRINTER POPUP -->
    <div 
        v-if="showReceiptModal && activeReceipt" 
        class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-4 animate-in fade-in"
    >
        <div class="bg-card border rounded-xl shadow-lg w-full max-w-sm overflow-hidden flex flex-col animate-in zoom-in-95 duration-200">
            <!-- Modal Header -->
            <div class="p-4 border-b border-border flex items-center justify-between bg-muted/40">
                <span class="font-bold text-sm text-foreground">Struk Transaksi Sukses</span>
                <button 
                    @click="showReceiptModal = false" 
                    class="p-1 rounded-full hover:bg-muted text-muted-foreground hover:text-foreground"
                >
                    <X class="w-4 h-4" />
                </button>
            </div>

            <!-- Receipt Print Area -->
            <div class="p-6 bg-white overflow-y-auto max-h-[420px] text-black flex justify-center" id="receipt-print-area">
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

            <!-- Modal Actions -->
            <div class="p-4 border-t border-border flex items-center justify-end gap-2 bg-muted/30">
                <Button 
                    @click="showReceiptModal = false" 
                    variant="outline"
                    class="text-xs h-9"
                >
                    Tutup
                </Button>
                <Button 
                    @click="printReceipt" 
                    class="text-xs h-9 bg-primary text-primary-foreground flex items-center gap-1.5"
                >
                    <Printer class="w-3.5 h-3.5" /> Cetak Struk
                </Button>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Hidden scrollbars for smooth overflow design */
.scrollbar-thin::-webkit-scrollbar {
    width: 4px;
    height: 4px;
}
.scrollbar-thin::-webkit-scrollbar-track {
    background: transparent;
}
.scrollbar-thin::-webkit-scrollbar-thumb {
    background: hsl(var(--border));
    border-radius: 4px;
}
.scrollbar-thin::-webkit-scrollbar-thumb:hover {
    background: hsl(var(--muted-foreground));
}
</style>
