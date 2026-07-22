<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { 
    Plus, 
    Trash2, 
    Download, 
    Copy, 
    Save, 
    Sparkles, 
    PackagePlus, 
    FileText, 
    Search,
    History,
    Calendar,
    Package,
    ArrowLeftRight,
    Share2,
    RefreshCw,
    Clipboard,
    Maximize2,
    X
} from '@lucide/vue';
import Swal from 'sweetalert2';
import * as htmlToImage from 'html-to-image';

interface Category {
    id: number;
    nama: string;
}

interface Product {
    id: number;
    nama: string;
    kode: string;
    kategori_id: number;
    kategori?: Category;
}

interface OrderItemInput {
    id: string | number;
    produk_id: number | null;
    nama_item: string;
    jumlah: number;
    satuan: string;
    kolom: 'kiri' | 'kanan';
    keterangan: string;
    save_to_master: boolean;
    kategori_id: number | null;
}

interface SavedOrderItem {
    id: number;
    order_id: number;
    produk_id: number | null;
    nama_item: string;
    jumlah: number;
    satuan: string;
    kolom: 'kiri' | 'kanan';
    keterangan?: string | null;
}

interface SavedOrder {
    id: number;
    nomor_order: string;
    judul: string;
    tanggal: string;
    catatan?: string | null;
    status: string;
    created_at: string;
    items: SavedOrderItem[];
}

interface PaginatedOrders {
    current_page: number;
    data: SavedOrder[];
    last_page: number;
    total: number;
}

const props = defineProps<{
    orders: PaginatedOrders;
    products: Product[];
    categories: Category[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: '/dashboard' },
            { title: 'Order Kiriman', href: '/orders' },
        ],
    },
});

// Active Tab: 'builder' or 'history'
const activeTab = ref<'builder' | 'history'>('builder');

// Document Metadata
const documentTitle = ref('NYUWUN KIRIMAN');
const orderDate = ref(new Date().toISOString().substring(0, 10));
const orderNotes = ref('');

// Master items list
const items = ref<OrderItemInput[]>([]);

// Input fields for Adding Item
const isExistingProductMode = ref(true);
const selectedProductId = ref<number | null>(null);
const inputNamaItem = ref('');
const inputJumlah = ref<number>(1);
const inputSatuan = ref('Karton');
const inputKolom = ref<'kiri' | 'kanan'>('kiri');
const inputKeterangan = ref('');
const inputSaveToMaster = ref(false);
const inputKategoriId = ref<number | null>(props.categories[0]?.id || null);

// Search query in DB product dropdown
const productSearchQuery = ref('');
const showProductDropdown = ref(false);

const filteredProducts = computed(() => {
    if (!productSearchQuery.value) return props.products;
    const q = productSearchQuery.value.toLowerCase();
    return props.products.filter(p => p.nama.toLowerCase().includes(q) || p.kode.toLowerCase().includes(q));
});

// Auto detect unit & column based on product name or unit select
const handleSatuanChange = () => {
    if (inputSatuan.value.toLowerCase().includes('karton')) {
        inputKolom.value = 'kiri';
    } else {
        inputKolom.value = 'kanan';
    }
};

const handleProductNameInput = () => {
    const name = inputNamaItem.value.toLowerCase();
    if (name.includes('karton')) {
        inputSatuan.value = 'Karton';
        inputKolom.value = 'kiri';
    } else if (name.includes('ball')) {
        inputSatuan.value = 'Ball';
        inputKolom.value = 'kanan';
    }
};

const selectProductFromDropdown = (product: Product) => {
    selectedProductId.value = product.id;
    inputNamaItem.value = product.nama;
    productSearchQuery.value = product.nama;
    showProductDropdown.value = false;
    handleProductNameInput();
};

// Add Single Item
const addItem = () => {
    const nameStr = (inputNamaItem.value || productSearchQuery.value).trim();
    if (!nameStr) {
        Swal.fire({
            icon: 'warning',
            title: 'Nama barang kosong',
            text: 'Silakan isi nama barang terlebih dahulu.',
            timer: 1800,
            showConfirmButton: false,
        });
        return;
    }

    items.value.push({
        id: Date.now() + Math.random(),
        produk_id: selectedProductId.value,
        nama_item: nameStr,
        jumlah: inputJumlah.value > 0 ? inputJumlah.value : 1,
        satuan: inputSatuan.value || 'Karton',
        kolom: inputKolom.value,
        keterangan: inputKeterangan.value.trim(),
        save_to_master: !selectedProductId.value && inputSaveToMaster.value,
        kategori_id: inputKategoriId.value,
    });

    // Reset input fields
    selectedProductId.value = null;
    inputNamaItem.value = '';
    productSearchQuery.value = '';
    inputJumlah.value = 1;
    inputKeterangan.value = '';
    inputSaveToMaster.value = false;
    showProductDropdown.value = false;
};

// Bulk Paste Modal State
const showBulkModal = ref(false);
const bulkTextRaw = ref('');

// Parse raw text lines into structured items
const processBulkText = () => {
    if (!bulkTextRaw.value.trim()) return;

    const lines = bulkTextRaw.value.split('\n');
    let addedCount = 0;

    lines.forEach(line => {
        const trimmed = line.trim();
        if (!trimmed) return;

        let jumlah = 1;
        let satuan = 'Satuan';
        let kolom: 'kiri' | 'kanan' = 'kanan';
        let nama = trimmed;
        let keterangan = '';

        const numMatch = trimmed.match(/^(\d+)\s+(.*)/);
        if (numMatch) {
            jumlah = parseInt(numMatch[1], 10);
            nama = numMatch[2].trim();
        }

        if (/\bkarton\b/i.test(nama)) {
            satuan = 'Karton';
            kolom = 'kiri';
            nama = nama.replace(/\bkarton\b/gi, '').trim();
        } else if (/\bball\b/i.test(nama)) {
            satuan = 'Ball';
            kolom = 'kanan';
            nama = nama.replace(/\bball\b/gi, '').trim();
        }

        nama = nama.replace(/\s+/g, ' ');

        items.value.push({
            id: Date.now() + Math.random(),
            produk_id: null,
            nama_item: nama,
            jumlah,
            satuan,
            kolom,
            keterangan,
            save_to_master: false,
            kategori_id: null,
        });
        addedCount++;
    });

    bulkTextRaw.value = '';
    showBulkModal.value = false;

    Swal.fire({
        icon: 'success',
        title: `${addedCount} Barang Ditambahkan!`,
        timer: 1500,
        showConfirmButton: false,
    });
};

// Item editing / row actions
const removeItem = (id: string | number) => {
    items.value = items.value.filter(i => i.id !== id);
};

const toggleItemColumn = (item: OrderItemInput) => {
    item.kolom = item.kolom === 'kiri' ? 'kanan' : 'kiri';
};

const updateItemQty = (item: OrderItemInput, delta: number) => {
    const next = item.jumlah + delta;
    if (next >= 1) {
        item.jumlah = next;
    }
};

// Computed lists by Column
const itemsKiri = computed(() => items.value.filter(i => i.kolom === 'kiri'));
const itemsKanan = computed(() => items.value.filter(i => i.kolom === 'kanan'));

// Maximum number of rows between left and right column
const maxRows = computed(() => Math.max(itemsKiri.value.length, itemsKanan.value.length));

// Load Preset matching user's reference image
const loadSamplePreset = () => {
    items.value = [
        // Left Column (Kartonan)
        { id: 1, produk_id: null, nama_item: 'Sosis Okey ½ kg', jumlah: 1, satuan: 'Karton', kolom: 'kiri', keterangan: '', save_to_master: false, kategori_id: null },
        { id: 2, produk_id: null, nama_item: 'Sosis Okey 1 kg', jumlah: 1, satuan: 'Karton', kolom: 'kiri', keterangan: '', save_to_master: false, kategori_id: null },
        { id: 3, produk_id: null, nama_item: 'Hemato Pendek isi 40', jumlah: 1, satuan: 'Karton', kolom: 'kiri', keterangan: '', save_to_master: false, kategori_id: null },
        { id: 4, produk_id: null, nama_item: 'Hemato Panjang isi 30', jumlah: 1, satuan: 'Karton', kolom: 'kiri', keterangan: '', save_to_master: false, kategori_id: null },
        { id: 5, produk_id: null, nama_item: 'Hemato Panjang isi 15', jumlah: 1, satuan: 'Karton', kolom: 'kiri', keterangan: '', save_to_master: false, kategori_id: null },
        { id: 6, produk_id: null, nama_item: 'Salam Bakar Mini isi 13', jumlah: 2, satuan: 'Karton', kolom: 'kiri', keterangan: '', save_to_master: false, kategori_id: null },
        { id: 7, produk_id: null, nama_item: 'Yona Ayam Beff', jumlah: 1, satuan: 'Karton', kolom: 'kiri', keterangan: '', save_to_master: false, kategori_id: null },
        { id: 8, produk_id: null, nama_item: 'Yona Petties Beff', jumlah: 1, satuan: 'Karton', kolom: 'kiri', keterangan: '', save_to_master: false, kategori_id: null },
        { id: 9, produk_id: null, nama_item: 'Joss Bakar Mini', jumlah: 1, satuan: 'Karton', kolom: 'kiri', keterangan: '', save_to_master: false, kategori_id: null },
        { id: 10, produk_id: null, nama_item: 'Stik Okey', jumlah: 1, satuan: 'Karton', kolom: 'kiri', keterangan: '', save_to_master: false, kategori_id: null },
        { id: 11, produk_id: null, nama_item: 'Naget Okey', jumlah: 1, satuan: 'Karton', kolom: 'kiri', keterangan: '', save_to_master: false, kategori_id: null },
        { id: 12, produk_id: null, nama_item: 'Naget Salam', jumlah: 2, satuan: 'Karton', kolom: 'kiri', keterangan: '', save_to_master: false, kategori_id: null },
        { id: 13, produk_id: null, nama_item: 'Stik Salam', jumlah: 2, satuan: 'Karton', kolom: 'kiri', keterangan: '', save_to_master: false, kategori_id: null },
        { id: 14, produk_id: null, nama_item: 'Bakso Udang Indomina', jumlah: 1, satuan: 'Karton', kolom: 'kiri', keterangan: '', save_to_master: false, kategori_id: null },
        { id: 15, produk_id: null, nama_item: 'Bakso Mix Indomina', jumlah: 1, satuan: 'Karton', kolom: 'kiri', keterangan: '', save_to_master: false, kategori_id: null },
        { id: 16, produk_id: null, nama_item: 'Siomay Ikan Indomina', jumlah: 1, satuan: 'Karton', kolom: 'kiri', keterangan: '', save_to_master: false, kategori_id: null },
        { id: 17, produk_id: null, nama_item: 'Dumpling Ayam Indomina', jumlah: 1, satuan: 'Karton', kolom: 'kiri', keterangan: '', save_to_master: false, kategori_id: null },
        { id: 18, produk_id: null, nama_item: 'Dumpling Keju Indomina', jumlah: 1, satuan: 'Karton', kolom: 'kiri', keterangan: '', save_to_master: false, kategori_id: null },
        { id: 19, produk_id: null, nama_item: 'Cikuwa Indomina', jumlah: 1, satuan: 'Karton', kolom: 'kiri', keterangan: '', save_to_master: false, kategori_id: null },
        { id: 20, produk_id: null, nama_item: 'Row Roll Indomina', jumlah: 1, satuan: 'Karton', kolom: 'kiri', keterangan: '', save_to_master: false, kategori_id: null },
        { id: 21, produk_id: null, nama_item: 'Fish Tofu Indomina', jumlah: 1, satuan: 'Karton', kolom: 'kiri', keterangan: '', save_to_master: false, kategori_id: null },
        { id: 22, produk_id: null, nama_item: 'Kentang Afiko 2½ kg', jumlah: 1, satuan: 'Karton', kolom: 'kiri', keterangan: '', save_to_master: false, kategori_id: null },
        { id: 23, produk_id: null, nama_item: 'Kentang Fiesta ½ kg', jumlah: 1, satuan: 'Karton', kolom: 'kiri', keterangan: '', save_to_master: false, kategori_id: null },
        { id: 24, produk_id: null, nama_item: 'Naget 1688', jumlah: 1, satuan: 'Karton', kolom: 'kiri', keterangan: '', save_to_master: false, kategori_id: null },
        { id: 25, produk_id: null, nama_item: 'Sosis Salju Indomina', jumlah: 1, satuan: 'Karton', kolom: 'kiri', keterangan: '', save_to_master: false, kategori_id: null },
        { id: 26, produk_id: null, nama_item: 'Otak-Otak Indomina', jumlah: 1, satuan: 'Karton', kolom: 'kiri', keterangan: '', save_to_master: false, kategori_id: null },

        // Right Column (Ball & Satuan)
        { id: 27, produk_id: null, nama_item: 'Basreng', jumlah: 20, satuan: 'Satuan', kolom: 'kanan', keterangan: '', save_to_master: false, kategori_id: null },
        { id: 28, produk_id: null, nama_item: 'Otak-Otak Kecil', jumlah: 1, satuan: 'Ball', kolom: 'kanan', keterangan: '', save_to_master: false, kategori_id: null },
        { id: 29, produk_id: null, nama_item: 'Bakso Abadi', jumlah: 25, satuan: 'Satuan', kolom: 'kanan', keterangan: '', save_to_master: false, kategori_id: null },
        { id: 30, produk_id: null, nama_item: 'Kaki Naga SAS', jumlah: 20, satuan: 'Satuan', kolom: 'kanan', keterangan: '', save_to_master: false, kategori_id: null },
        { id: 31, produk_id: null, nama_item: 'Cireng Salju', jumlah: 15, satuan: 'Satuan', kolom: 'kanan', keterangan: '', save_to_master: false, kategori_id: null },
        { id: 32, produk_id: null, nama_item: 'Sukoi', jumlah: 20, satuan: 'Satuan', kolom: 'kanan', keterangan: '', save_to_master: false, kategori_id: null },
        { id: 33, produk_id: null, nama_item: 'Star', jumlah: 10, satuan: 'Satuan', kolom: 'kanan', keterangan: '', save_to_master: false, kategori_id: null },
    ];

    Swal.fire({
        icon: 'success',
        title: 'Preset Berhasil Dimuat!',
        text: '33 Barang dimuat ke daftar order.',
        timer: 1500,
        showConfirmButton: false,
    });
};

const clearOrder = () => {
    if (items.value.length === 0) return;
    Swal.fire({
        title: 'Kosongkan List Order?',
        text: 'Semua barang dalam daftar akan dihapus.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Ya, Kosongkan',
        cancelButtonText: 'Batal',
    }).then((res) => {
        if (res.isConfirmed) {
            items.value = [];
        }
    });
};

// Formatted line for canvas rendering
const formatItemLine = (item: OrderItemInput | SavedOrderItem) => {
    let line = `${item.jumlah} `;
    if (item.satuan && item.satuan.toLowerCase() !== 'satuan' && item.satuan.toLowerCase() !== 'pcs') {
        line += `${item.satuan} `;
    }
    line += item.nama_item;
    if (item.keterangan && item.keterangan.trim()) {
        line += ` ${item.keterangan.trim()}`;
    }
    return line;
};

// High-Resolution Image Exporting
const exportCardRef = ref<HTMLElement | null>(null);
const isExporting = ref(false);
const showPreviewModal = ref(false);
const previewImageDataUrl = ref('');

// Download PNG Image
const downloadImage = async () => {
    if (!exportCardRef.value || items.value.length === 0) {
        Swal.fire('Daftar Kosong', 'Tambahkan barang ke daftar terlebih dahulu.', 'warning');
        return;
    }

    isExporting.value = true;
    try {
        const dataUrl = await htmlToImage.toPng(exportCardRef.value, {
            quality: 1.0,
            pixelRatio: 3,
            backgroundColor: '#ffffff',
        });

        const link = document.createElement('a');
        link.download = `${documentTitle.value.replace(/\s+/g, '_')}_${orderDate.value}.png`;
        link.href = dataUrl;
        link.click();

        Swal.fire({
            icon: 'success',
            title: 'Gambar Berhasil Diunduh!',
            text: 'File PNG telah tersimpan.',
            timer: 2000,
            showConfirmButton: false,
        });
    } catch (err) {
        console.error(err);
        Swal.fire('Gagal Export', 'Gagal memproses gambar.', 'error');
    } finally {
        isExporting.value = false;
    }
};

// Copy PNG Image to Clipboard
const copyImageToClipboard = async () => {
    if (!exportCardRef.value || items.value.length === 0) {
        Swal.fire('Daftar Kosong', 'Tambahkan barang ke daftar terlebih dahulu.', 'warning');
        return;
    }

    isExporting.value = true;
    try {
        const blob = await htmlToImage.toBlob(exportCardRef.value, {
            quality: 1.0,
            pixelRatio: 3,
            backgroundColor: '#ffffff',
        });

        if (blob && navigator.clipboard && window.ClipboardItem) {
            await navigator.clipboard.write([
                new ClipboardItem({ 'image/png': blob })
            ]);
            Swal.fire({
                icon: 'success',
                title: 'Gambar Disalin ke Clipboard!',
                text: 'Buka WhatsApp Web dan tekan Ctrl+V untuk mengirim gambar ini.',
                timer: 2500,
                showConfirmButton: false,
            });
        } else {
            throw new Error('Browser tidak mendukung ClipboardItem API');
        }
    } catch (err) {
        console.error(err);
        copyTextSummary();
    } finally {
        isExporting.value = false;
    }
};

// Open Fullscreen Image Preview
const openFullImagePreview = async () => {
    if (!exportCardRef.value || items.value.length === 0) return;
    try {
        const url = await htmlToImage.toPng(exportCardRef.value, {
            quality: 1.0,
            pixelRatio: 2,
            backgroundColor: '#ffffff',
        });
        previewImageDataUrl.value = url;
        showPreviewModal.value = true;
    } catch (e) {
        console.error(e);
    }
};

// Copy WhatsApp Plaintext Summary
const copyTextSummary = () => {
    let text = `*${documentTitle.value}*\nTanggal: ${orderDate.value}\n\n`;
    text += `*--- KARTONAN ---*\n`;
    itemsKiri.value.forEach(i => {
        text += `• ${formatItemLine(i)}\n`;
    });
    text += `\n*--- BALL / SATUAN ---*\n`;
    itemsKanan.value.forEach(i => {
        text += `• ${formatItemLine(i)}\n`;
    });

    navigator.clipboard.writeText(text);
    Swal.fire({
        icon: 'info',
        title: 'Teks Pesanan Disalin!',
        text: 'Format teks disalin ke clipboard.',
        timer: 2000,
        showConfirmButton: false,
    });
};

// Save Order to DB
const saveOrderForm = useForm({
    judul: '',
    tanggal: '',
    catatan: '',
    items: [] as OrderItemInput[],
});

const saveOrderToDatabase = () => {
    if (items.value.length === 0) {
        Swal.fire('Daftar Kosong', 'Tambahkan setidaknya 1 barang.', 'warning');
        return;
    }

    saveOrderForm.judul = documentTitle.value;
    saveOrderForm.tanggal = orderDate.value;
    saveOrderForm.catatan = orderNotes.value;
    saveOrderForm.items = items.value;

    saveOrderForm.post('/orders', {
        onSuccess: () => {
            Swal.fire({
                icon: 'success',
                title: 'Order Disimpan!',
                text: 'Order kiriman dan barang baru berhasil disimpan ke database.',
                timer: 2000,
                showConfirmButton: false,
            });
        },
        onError: (err) => {
            console.error(err);
            Swal.fire('Gagal Menyimpan', 'Periksa kembali data order.', 'error');
        }
    });
};

// Delete Order from History
const deleteOrder = (orderId: number) => {
    Swal.fire({
        title: 'Hapus Order Ini?',
        text: 'Data order akan dihapus permanen.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Ya, Hapus',
        cancelButtonText: 'Batal'
    }).then((res) => {
        if (res.isConfirmed) {
            router.delete(`/orders/${orderId}`, {
                onSuccess: () => {
                    Swal.fire('Terhapus', 'Order telah dihapus.', 'success');
                }
            });
        }
    });
};

// Load Saved Order from History to Editor
const loadSavedOrderToBuilder = (savedOrder: SavedOrder) => {
    documentTitle.value = savedOrder.judul || 'NYUWUN KIRIMAN';
    orderDate.value = savedOrder.tanggal ? savedOrder.tanggal.substring(0, 10) : new Date().toISOString().substring(0, 10);
    orderNotes.value = savedOrder.catatan || '';

    items.value = savedOrder.items.map(item => ({
        id: item.id,
        produk_id: item.produk_id,
        nama_item: item.nama_item,
        jumlah: item.jumlah,
        satuan: item.satuan,
        kolom: item.kolom,
        keterangan: item.keterangan || '',
        save_to_master: false,
        kategori_id: null,
    }));

    activeTab.value = 'builder';

    Swal.fire({
        icon: 'success',
        title: 'Order Dimuat!',
        text: `Order ${savedOrder.nomor_order} dimuat ke editor.`,
        timer: 1500,
        showConfirmButton: false,
    });
};

onMounted(() => {
    if (items.value.length === 0) {
        loadSamplePreset();
    }
});
</script>

<template>
    <Head title="Order Kiriman Supplier - NYUWUN KIRIMAN" />

    <div class="flex-1 w-full p-4 md:p-6 space-y-4 bg-slate-50/50">
            <!-- Sleek Compact Workspace Control Bar -->
            <div class="flex flex-wrap items-center justify-between gap-3 bg-white p-3 px-4 rounded-2xl border border-slate-200/80 shadow-sm">
                <!-- Mode Tabs -->
                <div class="flex items-center gap-2">
                    <button
                        @click="activeTab = 'builder'"
                        :class="[
                            'px-4 py-2 text-xs font-bold rounded-xl transition-all flex items-center gap-1.5',
                            activeTab === 'builder'
                                ? 'bg-amber-600 text-white shadow-md shadow-amber-600/20'
                                : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
                        ]"
                    >
                        <Plus class="w-4 h-4" />
                        Editor Order
                    </button>
                    <button
                        @click="activeTab = 'history'"
                        :class="[
                            'px-4 py-2 text-xs font-bold rounded-xl transition-all flex items-center gap-1.5',
                            activeTab === 'history'
                                ? 'bg-amber-600 text-white shadow-md shadow-amber-600/20'
                                : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
                        ]"
                    >
                        <History class="w-4 h-4" />
                        Riwayat Order ({{ orders.total }})
                    </button>
                </div>

                <!-- Presets & Bulk Import Actions -->
                <div class="flex items-center gap-2">
                    <button
                        @click="loadSamplePreset"
                        class="px-3 py-1.5 bg-amber-50 hover:bg-amber-100 text-amber-700 border border-amber-200/80 text-xs font-bold rounded-xl flex items-center gap-1.5 transition-colors"
                    >
                        <Sparkles class="w-3.5 h-3.5 text-amber-600" />
                        Muat Preset Contoh
                    </button>
                    <button
                        @click="showBulkModal = true"
                        class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-200 text-xs font-bold rounded-xl flex items-center gap-1.5 transition-colors"
                    >
                        <Clipboard class="w-3.5 h-3.5 text-slate-500" />
                        Paste Multi-Baris
                    </button>
                    <button
                        v-if="items.length > 0"
                        @click="clearOrder"
                        class="px-2.5 py-1.5 text-red-600 hover:bg-red-50 text-xs font-semibold rounded-xl flex items-center gap-1 transition-colors"
                    >
                        <Trash2 class="w-3.5 h-3.5" />
                        Reset List
                    </button>
                </div>
            </div>

            <!-- TAB 1: EDITOR ORDER -->
            <div v-if="activeTab === 'builder'" class="grid grid-cols-1 lg:grid-cols-12 gap-5 items-start">
                
                <!-- LEFT PANEL: Quick Add + Items Management Tables (7 COLS) -->
                <div class="lg:col-span-7 space-y-4">
                    
                    <!-- Input Item Card -->
                    <div class="bg-white p-4 sm:p-5 rounded-2xl border border-slate-200 shadow-sm space-y-3.5">
                        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                            <h2 class="text-xs font-bold text-slate-800 uppercase tracking-wider flex items-center gap-1.5">
                                <PackagePlus class="w-4 h-4 text-amber-600" />
                                Input Barang Order
                            </h2>

                            <div class="flex items-center bg-slate-100 p-0.5 rounded-lg text-[11px] font-semibold">
                                <button
                                    @click="isExistingProductMode = true"
                                    :class="['px-2.5 py-1 rounded-md transition-all', isExistingProductMode ? 'bg-white text-slate-900 shadow-sm font-bold' : 'text-slate-500']"
                                >
                                    Pilih Master DB
                                </button>
                                <button
                                    @click="isExistingProductMode = false; selectedProductId = null;"
                                    :class="['px-2.5 py-1 rounded-md transition-all', !isExistingProductMode ? 'bg-white text-slate-900 shadow-sm font-bold' : 'text-slate-500']"
                                >
                                    Input Manual
                                </button>
                            </div>
                        </div>

                        <!-- Dropdown Search for DB Product -->
                        <div v-if="isExistingProductMode" class="relative space-y-1">
                            <label class="text-[11px] font-bold uppercase text-slate-400">Pilih Dari Produk Database:</label>
                            <div class="relative">
                                <Search class="w-4 h-4 text-slate-400 absolute left-3 top-2.5" />
                                <input
                                    type="text"
                                    v-model="productSearchQuery"
                                    @focus="showProductDropdown = true"
                                    placeholder="Cari nama produk / ketik nama barang..."
                                    class="w-full pl-9 pr-4 py-2 text-xs bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all font-medium"
                                />
                            </div>

                            <div
                                v-if="showProductDropdown && filteredProducts.length > 0"
                                class="absolute left-0 right-0 top-full mt-1 max-h-48 overflow-y-auto border border-slate-200 rounded-xl bg-white shadow-xl divide-y divide-slate-100 z-30"
                            >
                                <div
                                    v-for="prod in filteredProducts.slice(0, 10)"
                                    :key="prod.id"
                                    @click="selectProductFromDropdown(prod)"
                                    class="p-2.5 text-xs hover:bg-amber-50 cursor-pointer flex items-center justify-between transition-colors"
                                >
                                    <span class="font-bold text-slate-800">{{ prod.nama }}</span>
                                    <span class="text-[10px] bg-slate-100 text-slate-500 px-2 py-0.5 rounded font-mono">{{ prod.kategori?.nama || 'Umum' }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Input fields grid -->
                        <div class="grid grid-cols-1 sm:grid-cols-12 gap-2.5">
                            <!-- Nama Barang -->
                            <div class="sm:col-span-5 space-y-1">
                                <label class="text-[11px] font-bold uppercase text-slate-400">Nama Barang:</label>
                                <input
                                    type="text"
                                    v-model="inputNamaItem"
                                    @input="handleProductNameInput"
                                    @keyup.enter="addItem"
                                    placeholder="Contoh: Sosis Okey / Basreng"
                                    class="w-full px-3 py-2 text-xs bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 font-medium"
                                />
                            </div>

                            <!-- Qty -->
                            <div class="sm:col-span-3 space-y-1">
                                <label class="text-[11px] font-bold uppercase text-slate-400">Jumlah Qty:</label>
                                <input
                                    type="number"
                                    min="1"
                                    v-model.number="inputJumlah"
                                    @keyup.enter="addItem"
                                    class="w-full px-3 py-2 text-xs bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 font-bold text-slate-900"
                                />
                            </div>

                            <!-- Satuan Kemasan -->
                            <div class="sm:col-span-4 space-y-1">
                                <label class="text-[11px] font-bold uppercase text-slate-400">Satuan Kemasan:</label>
                                <select
                                    v-model="inputSatuan"
                                    @change="handleSatuanChange"
                                    class="w-full px-3 py-2 text-xs bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 font-semibold"
                                >
                                    <option value="Karton">Karton (Kiri)</option>
                                    <option value="Ball">Ball (Kanan)</option>
                                    <option value="Satuan">Satuan / Pcs (Kanan)</option>
                                    <option value="Bungkus">Bungkus (Kanan)</option>
                                    <option value="Ikat">Ikat (Kanan)</option>
                                </select>
                            </div>
                        </div>

                        <!-- Column Target & Auto DB Checkbox -->
                        <div class="grid grid-cols-1 sm:grid-cols-12 gap-2.5 items-center">
                            <!-- Kolom Target Switch -->
                            <div class="sm:col-span-6 space-y-1">
                                <label class="text-[11px] font-bold uppercase text-slate-400">Posisi Kolom Output:</label>
                                <div class="grid grid-cols-2 gap-1.5">
                                    <button
                                        type="button"
                                        @click="inputKolom = 'kiri'"
                                        :class="[
                                            'py-1.5 px-2 text-xs font-bold rounded-lg border transition-all flex items-center justify-center gap-1',
                                            inputKolom === 'kiri' ? 'bg-amber-50 border-amber-400 text-amber-800' : 'bg-slate-50 border-slate-200 text-slate-500'
                                        ]"
                                    >
                                        Kiri (Kartonan)
                                    </button>
                                    <button
                                        type="button"
                                        @click="inputKolom = 'kanan'"
                                        :class="[
                                            'py-1.5 px-2 text-xs font-bold rounded-lg border transition-all flex items-center justify-center gap-1',
                                            inputKolom === 'kanan' ? 'bg-blue-50 border-blue-400 text-blue-800' : 'bg-slate-50 border-slate-200 text-slate-500'
                                        ]"
                                    >
                                        Kanan (Ball/Satuan)
                                    </button>
                                </div>
                            </div>

                            <div class="sm:col-span-6 flex items-end">
                                <button
                                    @click="addItem"
                                    class="w-full py-2 bg-slate-900 hover:bg-slate-800 text-white text-xs font-bold rounded-xl transition-all shadow-md shadow-slate-900/10 flex items-center justify-center gap-1.5"
                                >
                                    <Plus class="w-4 h-4 text-amber-400" />
                                    Tambah Barang (+ Enter)
                                </button>
                            </div>
                        </div>

                        <!-- Auto Save DB Option -->
                        <div v-if="!selectedProductId" class="p-2.5 bg-amber-50/70 border border-amber-200 rounded-xl space-y-1.5">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input
                                    type="checkbox"
                                    v-model="inputSaveToMaster"
                                    class="rounded border-amber-300 text-amber-600 focus:ring-amber-500 h-4 w-4"
                                />
                                <span class="text-xs font-bold text-amber-900">
                                    Simpan barang baru ini ke Master Produk database secara otomatis saat simpan order
                                </span>
                            </label>

                            <div v-if="inputSaveToMaster" class="flex items-center gap-2 pl-6 pt-1">
                                <span class="text-xs text-amber-800 font-semibold">Kategori DB:</span>
                                <select
                                    v-model="inputKategoriId"
                                    class="px-2 py-1 text-xs bg-white border border-amber-300 rounded-lg focus:ring-amber-500 font-medium"
                                >
                                    <option v-for="cat in categories" :key="cat.id" :value="cat.id">
                                        {{ cat.nama }}
                                    </option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Side-by-Side Live Editable Tables (Kiri vs Kanan) -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <!-- Left Table: Kartonan -->
                        <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm space-y-2.5">
                            <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                                <span class="text-xs font-extrabold text-slate-800 uppercase tracking-wider flex items-center gap-1.5">
                                    <span class="w-2.5 h-2.5 rounded-full bg-amber-500"></span>
                                    Kolom Kiri: Kartonan ({{ itemsKiri.length }})
                                </span>
                            </div>

                            <div v-if="itemsKiri.length === 0" class="py-8 text-center text-xs text-slate-400 italic">
                                Belum ada barang kartonan.
                            </div>

                            <div v-else class="space-y-1.5 max-h-[420px] overflow-y-auto pr-1">
                                <div
                                    v-for="item in itemsKiri"
                                    :key="item.id"
                                    class="p-2 bg-slate-50 hover:bg-amber-50/70 rounded-xl border border-slate-200/80 flex items-center justify-between text-xs transition-colors group"
                                >
                                    <div class="flex items-center gap-2 font-medium text-slate-900 truncate">
                                        <!-- Qty adjust -->
                                        <div class="flex items-center border border-slate-200 rounded-lg bg-white overflow-hidden">
                                            <button @click="updateItemQty(item, -1)" class="px-1.5 py-0.5 hover:bg-slate-100 text-slate-600 font-bold">-</button>
                                            <span class="px-2 font-extrabold text-amber-700 min-w-[18px] text-center">{{ item.jumlah }}</span>
                                            <button @click="updateItemQty(item, 1)" class="px-1.5 py-0.5 hover:bg-slate-100 text-slate-600 font-bold">+</button>
                                        </div>

                                        <span class="font-bold truncate">{{ item.nama_item }}</span>
                                    </div>

                                    <div class="flex items-center gap-1">
                                        <button
                                            @click="toggleItemColumn(item)"
                                            class="p-1 text-slate-400 hover:text-blue-600 hover:bg-blue-50 rounded-md transition-colors"
                                            title="Pindahkan ke Kolom Kanan"
                                        >
                                            <ArrowLeftRight class="w-3.5 h-3.5" />
                                        </button>
                                        <button
                                            @click="removeItem(item.id)"
                                            class="p-1 text-slate-400 hover:text-red-600 hover:bg-red-50 rounded-md transition-colors"
                                            title="Hapus item"
                                        >
                                            <Trash2 class="w-3.5 h-3.5" />
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Right Table: Ball & Satuan -->
                        <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm space-y-2.5">
                            <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                                <span class="text-xs font-extrabold text-slate-800 uppercase tracking-wider flex items-center gap-1.5">
                                    <span class="w-2.5 h-2.5 rounded-full bg-blue-500"></span>
                                    Kolom Kanan: Ball & Satuan ({{ itemsKanan.length }})
                                </span>
                            </div>

                            <div v-if="itemsKanan.length === 0" class="py-8 text-center text-xs text-slate-400 italic">
                                Belum ada barang ball/satuan.
                            </div>

                            <div v-else class="space-y-1.5 max-h-[420px] overflow-y-auto pr-1">
                                <div
                                    v-for="item in itemsKanan"
                                    :key="item.id"
                                    class="p-2 bg-slate-50 hover:bg-blue-50/70 rounded-xl border border-slate-200/80 flex items-center justify-between text-xs transition-colors group"
                                >
                                    <div class="flex items-center gap-2 font-medium text-slate-900 truncate">
                                        <!-- Qty adjust -->
                                        <div class="flex items-center border border-slate-200 rounded-lg bg-white overflow-hidden">
                                            <button @click="updateItemQty(item, -1)" class="px-1.5 py-0.5 hover:bg-slate-100 text-slate-600 font-bold">-</button>
                                            <span class="px-2 font-extrabold text-blue-700 min-w-[18px] text-center">{{ item.jumlah }}</span>
                                            <button @click="updateItemQty(item, 1)" class="px-1.5 py-0.5 hover:bg-slate-100 text-slate-600 font-bold">+</button>
                                        </div>

                                        <span class="font-bold truncate">{{ item.nama_item }}</span>
                                    </div>

                                    <div class="flex items-center gap-1">
                                        <button
                                            @click="toggleItemColumn(item)"
                                            class="p-1 text-slate-400 hover:text-amber-600 hover:bg-amber-50 rounded-md transition-colors"
                                            title="Pindahkan ke Kolom Kiri"
                                        >
                                            <ArrowLeftRight class="w-3.5 h-3.5" />
                                        </button>
                                        <button
                                            @click="removeItem(item.id)"
                                            class="p-1 text-slate-400 hover:text-red-600 hover:bg-red-50 rounded-md transition-colors"
                                            title="Hapus item"
                                        >
                                            <Trash2 class="w-3.5 h-3.5" />
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- RIGHT PANEL: Live Document Canvas Output (5 COLS) -->
                <div class="lg:col-span-5 space-y-4">
                    
                    <!-- Action Toolbar Card -->
                    <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-extrabold text-slate-700 uppercase tracking-wider flex items-center gap-1.5">
                                <Share2 class="w-4 h-4 text-amber-600" />
                                Ekspor Output Gambar
                            </span>
                            <span class="text-xs font-bold bg-amber-100 text-amber-800 px-2.5 py-0.5 rounded-full">
                                {{ items.length }} Barang Total
                            </span>
                        </div>

                        <!-- Export Buttons -->
                        <div class="grid grid-cols-2 gap-2">
                            <button
                                @click="downloadImage"
                                :disabled="isExporting || items.length === 0"
                                class="py-2.5 px-3 bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 text-white text-xs font-bold rounded-xl transition-all shadow-md shadow-emerald-600/10 flex items-center justify-center gap-2"
                            >
                                <Download class="w-4 h-4" />
                                Download PNG
                            </button>

                            <button
                                @click="copyImageToClipboard"
                                :disabled="isExporting || items.length === 0"
                                class="py-2.5 px-3 bg-blue-600 hover:bg-blue-700 disabled:opacity-50 text-white text-xs font-bold rounded-xl transition-all shadow-md shadow-blue-600/10 flex items-center justify-center gap-2"
                            >
                                <Copy class="w-4 h-4" />
                                Copy ke WA
                            </button>
                        </div>

                        <div class="grid grid-cols-2 gap-2">
                            <button
                                @click="openFullImagePreview"
                                :disabled="items.length === 0"
                                class="py-2 px-3 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold rounded-xl transition-colors flex items-center justify-center gap-1.5"
                            >
                                <Maximize2 class="w-3.5 h-3.5" />
                                Zoom Full
                            </button>
                            <button
                                @click="copyTextSummary"
                                class="py-2 px-3 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold rounded-xl transition-colors flex items-center justify-center gap-1.5"
                            >
                                <Clipboard class="w-3.5 h-3.5" />
                                Copy Teks WA
                            </button>
                        </div>

                        <button
                            @click="saveOrderToDatabase"
                            :disabled="saveOrderForm.processing || items.length === 0"
                            class="w-full py-2.5 px-3 bg-amber-600 hover:bg-amber-700 disabled:opacity-50 text-white text-xs font-bold rounded-xl transition-all shadow-md shadow-amber-600/20 flex items-center justify-center gap-2"
                        >
                            <Save class="w-4 h-4" />
                            Simpan Order ke Database
                        </button>
                    </div>

                    <!-- REALISTIC CANVAS PREVIEW BOX (Strict 2-Column Table matching sample image with ZERO text collision) -->
                    <div class="bg-slate-200/80 p-3 sm:p-4 rounded-2xl border border-slate-300 overflow-x-auto shadow-inner">
                        <div
                            ref="exportCardRef"
                            class="bg-white text-black p-6 sm:p-8 w-full max-w-[620px] mx-auto min-h-[580px] shadow-sm leading-relaxed text-left border border-slate-200"
                            style="font-family: 'Times New Roman', Times, serif;"
                        >
                            <!-- Title -->
                            <div class="text-center mb-8">
                                <h1 class="text-base sm:text-lg font-bold uppercase tracking-widest text-black">
                                    {{ documentTitle || 'NYUWUN KIRIMAN' }}
                                </h1>
                            </div>

                            <!-- 2-Column Table Layout to ensure left and right columns NEVER overlap -->
                            <table v-if="maxRows > 0" class="w-full border-collapse font-serif text-[12px] sm:text-[13px] text-black" style="font-family: 'Times New Roman', Times, serif;">
                                <tbody>
                                    <tr v-for="rowIndex in maxRows" :key="rowIndex">
                                        <!-- Left Column Cell (Kartonan) -->
                                        <td class="w-[58%] align-top pr-4 pb-1.5 leading-snug text-left break-words">
                                            <span v-if="itemsKiri[rowIndex - 1]">
                                                {{ formatItemLine(itemsKiri[rowIndex - 1]) }}
                                            </span>
                                        </td>

                                        <!-- Right Column Cell (Ball & Satuan) -->
                                        <td class="w-[42%] align-top pl-4 pb-1.5 leading-snug text-left break-words">
                                            <span v-if="itemsKanan[rowIndex - 1]">
                                                {{ formatItemLine(itemsKanan[rowIndex - 1]) }}
                                            </span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>

                            <div v-else class="text-center py-12 text-slate-400 text-xs italic">
                                (Daftar Order Kosong)
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB 2: RIWAYAT ORDER -->
            <div v-if="activeTab === 'history'" class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="p-5 border-b border-slate-100">
                    <h2 class="text-sm font-bold text-slate-900">Daftar Order Kiriman Tersimpan</h2>
                    <p class="text-xs text-slate-500">Pilih order tersimpan untuk dimuat ulang ke editor atau di-export kembali.</p>
                </div>

                <div v-if="orders.data.length === 0" class="text-center py-12 text-slate-400 text-xs italic">
                    Belum ada order kiriman yang disimpan.
                </div>

                <div v-else class="divide-y divide-slate-100">
                    <div
                        v-for="order in orders.data"
                        :key="order.id"
                        class="p-4 sm:p-5 hover:bg-slate-50 transition-colors flex flex-col sm:flex-row sm:items-center justify-between gap-4"
                    >
                        <div class="space-y-1">
                            <div class="flex items-center gap-2">
                                <span class="font-bold text-slate-900 text-sm">{{ order.judul }}</span>
                                <span class="text-[11px] font-mono bg-amber-50 text-amber-800 px-2 py-0.5 rounded border border-amber-200 font-bold">
                                    {{ order.nomor_order }}
                                </span>
                            </div>
                            <div class="flex items-center gap-4 text-xs text-slate-500">
                                <span class="flex items-center gap-1">
                                    <Calendar class="w-3.5 h-3.5 text-slate-400" />
                                    {{ order.tanggal ? new Date(order.tanggal).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' }) : '-' }}
                                </span>
                                <span class="flex items-center gap-1">
                                    <Package class="w-3.5 h-3.5 text-slate-400" />
                                    {{ order.items.length }} Item Barang
                                </span>
                            </div>
                        </div>

                        <div class="flex items-center gap-2">
                            <button
                                @click="loadSavedOrderToBuilder(order)"
                                class="px-3.5 py-1.5 bg-amber-600 hover:bg-amber-700 text-white text-xs font-bold rounded-xl transition-colors flex items-center gap-1.5"
                            >
                                <RefreshCw class="w-3.5 h-3.5" />
                                Muat ke Editor & Export
                            </button>
                            <button
                                @click="deleteOrder(order.id)"
                                class="p-1.5 text-slate-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors"
                            >
                                <Trash2 class="w-4 h-4" />
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- MODAL: BULK PASTE TEXT -->
            <div v-if="showBulkModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
                <div class="bg-white rounded-2xl border border-slate-200 shadow-2xl w-full max-w-lg overflow-hidden space-y-4 p-5">
                    <div class="flex items-center justify-between border-b pb-3">
                        <h3 class="text-sm font-bold text-slate-900 flex items-center gap-2">
                            <Clipboard class="w-4 h-4 text-amber-600" />
                            Paste Banyak Baris Sekaligus
                        </h3>
                        <button @click="showBulkModal = false" class="text-slate-400 hover:text-slate-600">
                            <X class="w-4 h-4" />
                        </button>
                    </div>

                    <p class="text-xs text-slate-500">
                        Paste teks daftar barang dari WA / Catatan Anda (1 baris 1 barang). Sistem akan otomatis membaca jumlah, satuan, dan mengelompokkan ke kolom Karton vs Satuan.
                    </p>

                    <textarea
                        v-model="bulkTextRaw"
                        rows="8"
                        placeholder="Contoh:&#10;1 Karton Sosis Okey 1 kg&#10;20 Basreng&#10;1 Ball Otak-Otak Kecil&#10;25 Bakso Abadi"
                        class="w-full p-3 text-xs bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-amber-500 font-mono"
                    ></textarea>

                    <div class="flex items-center justify-end gap-2 pt-2 border-t">
                        <button @click="showBulkModal = false" class="px-3 py-1.5 text-xs font-semibold text-slate-600 hover:bg-slate-100 rounded-lg">
                            Batal
                        </button>
                        <button @click="processBulkText" class="px-4 py-1.5 bg-amber-600 text-white text-xs font-bold rounded-xl hover:bg-amber-700 transition-colors">
                            Proses & Tambahkan Ke Daftar
                        </button>
                    </div>
                </div>
            </div>

            <!-- MODAL: FULLSCREEN IMAGE PREVIEW -->
            <div v-if="showPreviewModal" class="fixed inset-0 bg-slate-900/80 backdrop-blur-sm z-50 flex items-center justify-center p-4">
                <div class="bg-white rounded-2xl shadow-2xl max-w-2xl w-full overflow-hidden p-5 space-y-4">
                    <div class="flex items-center justify-between border-b pb-3">
                        <h3 class="text-sm font-bold text-slate-900">Pratinjau Output Gambar (100% Scale)</h3>
                        <button @click="showPreviewModal = false" class="text-slate-400 hover:text-slate-600">
                            <X class="w-4 h-4" />
                        </button>
                    </div>

                    <div class="max-h-[70vh] overflow-y-auto bg-slate-100 p-4 rounded-xl flex justify-center">
                        <img :src="previewImageDataUrl" alt="Pratinjau Order" class="max-w-full h-auto shadow-md border" />
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-2">
                        <button @click="downloadImage" class="px-4 py-2 bg-emerald-600 text-white text-xs font-bold rounded-xl hover:bg-emerald-700 flex items-center gap-1.5">
                            <Download class="w-4 h-4" />
                            Download Gambar PNG
                        </button>
                    </div>
                </div>
            </div>

        </div>
</template>
