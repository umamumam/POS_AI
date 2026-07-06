<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { 
    Search, 
    Plus, 
    Edit, 
    Trash2, 
    X, 
    AlertCircle, 
    Package, 
    ArrowUpDown,
    CheckCircle
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

interface PaginatedProducts {
    current_page: number;
    data: Product[];
    first_page_url: string;
    from: number;
    last_page: number;
    last_page_url: string;
    links: Array<{ url: string | null; label: string; active: boolean }>;
    next_page_url: string | null;
    path: string;
    per_page: number;
    prev_page_url: string | null;
    to: number;
    total: number;
}

const props = defineProps<{
    products: PaginatedProducts;
    categories: Category[];
    filters: {
        q: string | null;
        category_id: string | null;
        low_stock: string | null;
        per_page: number | null;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Master Data',
                href: '#',
            },
            {
                title: 'Produk',
                href: '/products-crud',
            },
        ],
    },
});

// Formatting helper
const formatRupiah = (value: number) => {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
    }).format(value);
};

// Search & Filter States matching DataTables layout
const searchQuery = ref(props.filters.q || '');
const selectedCategory = ref(props.filters.category_id || '');
const isLowStockOnly = ref(props.filters.low_stock === 'true');
const entriesPerPage = ref(Number(props.filters.per_page) || 10);

const handleFilter = () => {
    router.get('/products-crud', {
        q: searchQuery.value,
        category_id: selectedCategory.value,
        low_stock: isLowStockOnly.value ? 'true' : 'false',
        per_page: entriesPerPage.value
    }, {
        preserveState: true,
        replace: true
    });
};

// watch selectors to trigger immediately
watch([selectedCategory, entriesPerPage, isLowStockOnly], () => {
    handleFilter();
});

// debounce search input
let searchTimeout: any = null;
const triggerDebouncedFilter = () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        handleFilter();
    }, 400);
};

// Modal toggles
const showCreateModal = ref(false);
const showEditModal = ref(false);
const selectedProduct = ref<Product | null>(null);

// Forms
const createForm = useForm({
    nama: '',
    kode: '',
    harga_beli: 0,
    harga_jual: 0,
    stok: 0,
    kategori_id: '',
});

const editForm = useForm({
    nama: '',
    kode: '',
    harga_beli: 0,
    harga_jual: 0,
    stok: 0,
    kategori_id: '',
});

// Handlers with SweetAlert2 integration
const openCreateModal = () => {
    createForm.reset();
    createForm.clearErrors();
    showCreateModal.value = true;
};

const submitCreate = () => {
    createForm.post('/products-crud', {
        onSuccess: () => {
            showCreateModal.value = false;
            createForm.reset();
            Swal.fire({
                icon: 'success',
                title: 'Berhasil!',
                text: 'Produk baru telah berhasil disimpan.',
                confirmButtonColor: '#ea580c',
                timer: 2000
            });
        }
    });
};

const openEditModal = (product: Product) => {
    selectedProduct.value = product;
    editForm.nama = product.nama;
    editForm.kode = product.kode;
    editForm.harga_beli = product.harga_beli;
    editForm.harga_jual = product.harga_jual;
    editForm.stok = product.stok;
    editForm.kategori_id = String(product.kategori_id);
    editForm.clearErrors();
    showEditModal.value = true;
};

const submitUpdate = () => {
    if (!selectedProduct.value) return;
    editForm.put(`/products-crud/${selectedProduct.value.id}`, {
        onSuccess: () => {
            showEditModal.value = false;
            editForm.reset();
            Swal.fire({
                icon: 'success',
                title: 'Berhasil!',
                text: 'Detail produk telah diperbarui.',
                confirmButtonColor: '#ea580c',
                timer: 2000
            });
        }
    });
};

const confirmDelete = (product: Product) => {
    Swal.fire({
        title: 'Hapus Produk?',
        text: `Apakah Anda yakin ingin menghapus produk "${product.nama}"?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Ya, Hapus',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(`/products-crud/${product.id}`, {
                onSuccess: () => {
                    Swal.fire({
                        icon: 'success',
                        title: 'Dihapus!',
                        text: 'Produk berhasil dihapus.',
                        confirmButtonColor: '#ea580c',
                        timer: 2000
                    });
                }
            });
        }
    });
};
</script>

<template>
    <Head title="Manajemen Produk" />

    <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
        <!-- Header Panel with Actions -->
        <div class="relative flex flex-col gap-4 rounded-xl border border-sidebar-border bg-card p-6 shadow-sm">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div class="flex items-center gap-2.5">
                    <div class="rounded-lg bg-orange-500/15 p-2 text-orange-600">
                        <Package class="h-5 w-5" />
                    </div>
                    <div>
                        <h3 class="text-sm font-extrabold text-foreground">Manajemen Produk (Master Data)</h3>
                        <p class="text-xs text-muted-foreground">Kelola persediaan barang dagangan, harga beli, harga jual, dan kategori produk toko Anda.</p>
                    </div>
                </div>

                <button
                    @click="openCreateModal"
                    class="flex items-center justify-center gap-1.5 rounded-lg bg-orange-600 px-4 py-2 text-xs font-bold text-white shadow-lg shadow-orange-600/15 hover:bg-orange-700 active:scale-95 transition-all self-start sm:self-center"
                >
                    <Plus class="h-4 w-4" />
                    <span>Tambah Produk Baru</span>
                </button>
            </div>

            <!-- Tab Buttons (Semua Produk vs Stok Rendah) -->
            <div class="flex gap-1.5 border-b pb-3 mt-2">
                <button
                    @click="isLowStockOnly = false"
                    :class="[
                        'px-4 py-2 text-xs font-bold rounded-lg transition-all',
                        !isLowStockOnly
                            ? 'bg-orange-500/10 text-orange-600 border border-orange-500/20'
                            : 'text-muted-foreground hover:bg-muted'
                    ]"
                >
                    Semua Produk
                </button>
                <button
                    @click="isLowStockOnly = true"
                    :class="[
                        'px-4 py-2 text-xs font-bold rounded-lg transition-all flex items-center gap-1.5',
                        isLowStockOnly
                            ? 'bg-red-500/10 text-red-600 border border-red-500/20 animate-pulse'
                            : 'text-muted-foreground hover:bg-muted'
                    ]"
                >
                    Stok Rendah (≤ 20)
                </button>
            </div>

            <!-- DataTables Header Controls: Entries Selection & Align Search on the Right -->
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mt-2 bg-muted/10 p-3 border rounded-xl">
                <!-- Left: Show entries dropdown -->
                <div class="flex items-center gap-2 text-xs font-medium text-muted-foreground">
                    <span>Tampilkan</span>
                    <select
                        v-model="entriesPerPage"
                        class="h-8 w-18 px-2 rounded-md border border-input bg-background text-xs text-foreground focus:ring-1 focus:ring-orange-500 focus:outline-none"
                    >
                        <option :value="10">10</option>
                        <option :value="25">25</option>
                        <option :value="50">50</option>
                        <option :value="100">100</option>
                    </select>
                    <span>entri</span>
                </div>

                <!-- Right: Search query aligned on the right -->
                <div class="flex items-center gap-2 w-full md:w-auto">
                    <!-- Category Dropdown Filter -->
                    <select
                        v-model="selectedCategory"
                        class="h-9 px-3 rounded-md border border-input bg-background text-xs text-foreground focus:ring-1 focus:ring-orange-500 focus:outline-none min-w-[140px]"
                    >
                        <option value="">Semua Kategori</option>
                        <option v-for="cat in categories" :key="cat.id" :value="cat.id">
                            {{ cat.nama }}
                        </option>
                    </select>

                    <div class="relative w-full md:w-64">
                        <Search class="absolute top-2.5 left-3 h-4 w-4 text-muted-foreground" />
                        <Input
                            type="text"
                            placeholder="Cari nama atau barcode..."
                            v-model="searchQuery"
                            @input="triggerDebouncedFilter"
                            class="h-9 pl-9 text-xs focus-visible:ring-orange-500 w-full"
                        />
                    </div>
                </div>
            </div>

            <!-- Products List Table (DataTables styled) -->
            <div class="overflow-x-auto border rounded-xl bg-background mt-2">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="bg-muted/30 border-b text-muted-foreground font-bold uppercase tracking-wider text-[10px]">
                            <th class="p-3">BARCODE</th>
                            <th class="p-3">NAMA PRODUK</th>
                            <th class="p-3">KATEGORI</th>
                            <th class="p-3">HARGA BELI</th>
                            <th class="p-3">HARGA JUAL</th>
                            <th class="p-3 text-center">STOK</th>
                            <th class="p-3 text-center">AKSI</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="products.data.length === 0" class="border-b last:border-0">
                            <td colspan="7" class="p-8 text-center text-muted-foreground font-semibold">
                                Tidak ada data produk yang cocok.
                            </td>
                        </tr>
                        <tr 
                            v-else
                            v-for="prod in products.data" 
                            :key="prod.id"
                            class="border-b last:border-0 hover:bg-muted/5 transition-colors font-medium text-foreground"
                        >
                            <td class="p-3 font-mono font-bold text-muted-foreground">{{ prod.kode }}</td>
                            <td class="p-3 font-bold text-foreground">{{ prod.nama }}</td>
                            <td class="p-3">
                                <span class="rounded bg-orange-500/10 px-2 py-0.5 text-[9px] font-bold text-orange-600">
                                    {{ prod.kategori?.nama || 'Lainnya' }}
                                </span>
                            </td>
                            <td class="p-3 text-muted-foreground">{{ formatRupiah(prod.harga_beli) }}</td>
                            <td class="p-3 font-bold text-orange-600">{{ formatRupiah(prod.harga_jual) }}</td>
                            <td class="p-3 text-center">
                                <span :class="[
                                    'rounded px-2 py-0.5 text-[10px] font-bold',
                                    prod.stok <= 20 ? 'bg-red-500/10 text-red-600 animate-pulse' : 'bg-green-500/10 text-green-600'
                                ]">
                                    {{ prod.stok }}
                                </span>
                            </td>
                            <td class="p-3">
                                <div class="flex items-center justify-center gap-1.5">
                                    <button
                                        @click="openEditModal(prod)"
                                        class="flex h-7 w-7 items-center justify-center rounded-lg bg-orange-500 text-white hover:bg-orange-600 transition-colors shadow-sm"
                                        title="Ubah Produk"
                                    >
                                        <Edit class="h-3.5 w-3.5" />
                                    </button>
                                    <button
                                        @click="confirmDelete(prod)"
                                        class="flex h-7 w-7 items-center justify-center rounded-lg bg-red-500 text-white hover:bg-red-600 transition-colors shadow-sm"
                                        title="Hapus Produk"
                                    >
                                        <Trash2 class="h-3.5 w-3.5" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- DataTables Footer Controls: Info text on left, Page numbers link on right -->
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-t pt-4 mt-2">
                <!-- Info Text -->
                <span class="text-xs font-semibold text-muted-foreground">
                    Menampilkan {{ products.from || 0 }} sampai {{ products.to || 0 }} dari {{ products.total }} entri
                    <span v-if="isLowStockOnly" class="text-red-500 font-bold ml-1">(Stok Rendah)</span>
                </span>
                
                <!-- Pagination buttons links aligned right -->
                <div v-if="products.last_page > 1" class="flex items-center gap-1.5">
                    <Link
                        v-for="(link, lIdx) in products.links"
                        :key="lIdx"
                        v-show="link.url && link.label.includes('Previous') === false && link.label.includes('Next') === false"
                        :href="link.url || ''"
                        :class="[
                            'px-3 py-1 text-xs font-bold rounded border transition-all',
                            link.active 
                                ? 'bg-orange-600 border-orange-600 text-white shadow-md shadow-orange-600/10' 
                                : 'bg-background hover:bg-muted text-muted-foreground'
                        ]"
                        v-html="link.label"
                    />
                </div>
            </div>
        </div>
    </div>

    <!-- DIALOG: TAMBAH PRODUK MODAL -->
    <div
        v-if="showCreateModal"
        class="fixed inset-0 z-50 flex animate-in items-center justify-center bg-black/60 p-4 fade-in"
    >
        <div
            class="flex max-h-[90%] w-full max-w-[480px] animate-in flex-col rounded-xl bg-card shadow-xl duration-150 zoom-in-95"
        >
            <div class="flex shrink-0 items-center justify-between border-b bg-muted/20 px-4 py-3">
                <span class="text-xs font-bold text-foreground">Tambah Produk Baru</span>
                <button @click="showCreateModal = false" class="rounded-full p-1 text-muted-foreground hover:bg-muted">
                    <X class="h-4.5 w-4.5" />
                </button>
            </div>

            <form @submit.prevent="submitCreate" class="flex-1 overflow-y-auto p-4 flex flex-col gap-3">
                <!-- Barcode -->
                <div class="flex flex-col gap-1">
                    <label class="text-[10px] font-bold text-muted-foreground uppercase">Barcode / Kode Produk</label>
                    <Input type="text" v-model="createForm.kode" placeholder="Masukkan kode barcode..." class="h-9 text-xs" />
                    <span v-if="createForm.errors.kode" class="text-[10px] text-red-500 font-medium">{{ createForm.errors.kode }}</span>
                </div>

                <!-- Nama -->
                <div class="flex flex-col gap-1">
                    <label class="text-[10px] font-bold text-muted-foreground uppercase">Nama Produk</label>
                    <Input type="text" v-model="createForm.nama" placeholder="Masukkan nama produk..." class="h-9 text-xs" />
                    <span v-if="createForm.errors.nama" class="text-[10px] text-red-500 font-medium">{{ createForm.errors.nama }}</span>
                </div>

                <!-- Kategori -->
                <div class="flex flex-col gap-1">
                    <label class="text-[10px] font-bold text-muted-foreground uppercase">Kategori</label>
                    <select
                        v-model="createForm.kategori_id"
                        class="w-full h-9 px-3 rounded-md border border-input bg-background text-xs text-foreground focus:ring-1 focus:ring-orange-500 focus:outline-none"
                    >
                        <option value="">Pilih Kategori</option>
                        <option v-for="cat in categories" :key="cat.id" :value="cat.id">
                            {{ cat.nama }}
                        </option>
                    </select>
                    <span v-if="createForm.errors.kategori_id" class="text-[10px] text-red-500 font-medium">{{ createForm.errors.kategori_id }}</span>
                </div>

                <!-- Harga Beli -->
                <div class="flex flex-col gap-1">
                    <label class="text-[10px] font-bold text-muted-foreground uppercase">Harga Beli</label>
                    <Input type="number" v-model.number="createForm.harga_beli" placeholder="Masukkan harga beli..." class="h-9 text-xs" />
                    <span v-if="createForm.errors.harga_beli" class="text-[10px] text-red-500 font-medium">{{ createForm.errors.harga_beli }}</span>
                </div>

                <!-- Harga Jual -->
                <div class="flex flex-col gap-1">
                    <label class="text-[10px] font-bold text-muted-foreground uppercase">Harga Jual</label>
                    <Input type="number" v-model.number="createForm.harga_jual" placeholder="Masukkan harga jual..." class="h-9 text-xs" />
                    <span v-if="createForm.errors.harga_jual" class="text-[10px] text-red-500 font-medium">{{ createForm.errors.harga_jual }}</span>
                </div>

                <!-- Stok -->
                <div class="flex flex-col gap-1">
                    <label class="text-[10px] font-bold text-muted-foreground uppercase">Stok Awal</label>
                    <Input type="number" v-model.number="createForm.stok" placeholder="Masukkan stok..." class="h-9 text-xs" />
                    <span v-if="createForm.errors.stok" class="text-[10px] text-red-500 font-medium">{{ createForm.errors.stok }}</span>
                </div>

                <!-- Footer buttons -->
                <div class="flex justify-end gap-2 border-t pt-3 mt-2">
                    <Button type="button" variant="outline" @click="showCreateModal = false" class="h-8 text-xs">Batal</Button>
                    <Button type="submit" class="h-8 text-xs bg-orange-600 text-white hover:bg-orange-700" :disabled="createForm.processing">Simpan</Button>
                </div>
            </form>
        </div>
    </div>

    <!-- DIALOG: UBAH PRODUK MODAL -->
    <div
        v-if="showEditModal"
        class="fixed inset-0 z-50 flex animate-in items-center justify-center bg-black/60 p-4 fade-in"
    >
        <div
            class="flex max-h-[90%] w-full max-w-[480px] animate-in flex-col rounded-xl bg-card shadow-xl duration-150 zoom-in-95"
        >
            <div class="flex shrink-0 items-center justify-between border-b bg-muted/20 px-4 py-3">
                <span class="text-xs font-bold text-foreground">Ubah Detail Produk</span>
                <button @click="showEditModal = false" class="rounded-full p-1 text-muted-foreground hover:bg-muted">
                    <X class="h-4.5 w-4.5" />
                </button>
            </div>

            <form @submit.prevent="submitUpdate" class="flex-1 overflow-y-auto p-4 flex flex-col gap-3">
                <!-- Barcode -->
                <div class="flex flex-col gap-1">
                    <label class="text-[10px] font-bold text-muted-foreground uppercase">Barcode / Kode Produk</label>
                    <Input type="text" v-model="editForm.kode" placeholder="Masukkan kode barcode..." class="h-9 text-xs" />
                    <span v-if="editForm.errors.kode" class="text-[10px] text-red-500 font-medium">{{ editForm.errors.kode }}</span>
                </div>

                <!-- Nama -->
                <div class="flex flex-col gap-1">
                    <label class="text-[10px] font-bold text-muted-foreground uppercase">Nama Produk</label>
                    <Input type="text" v-model="editForm.nama" placeholder="Masukkan nama produk..." class="h-9 text-xs" />
                    <span v-if="editForm.errors.nama" class="text-[10px] text-red-500 font-medium">{{ editForm.errors.nama }}</span>
                </div>

                <!-- Kategori -->
                <div class="flex flex-col gap-1">
                    <label class="text-[10px] font-bold text-muted-foreground uppercase">Kategori</label>
                    <select
                        v-model="editForm.kategori_id"
                        class="w-full h-9 px-3 rounded-md border border-input bg-background text-xs text-foreground focus:ring-1 focus:ring-orange-500 focus:outline-none"
                    >
                        <option value="">Pilih Kategori</option>
                        <option v-for="cat in categories" :key="cat.id" :value="cat.id">
                            {{ cat.nama }}
                        </option>
                    </select>
                    <span v-if="editForm.errors.kategori_id" class="text-[10px] text-red-500 font-medium">{{ editForm.errors.kategori_id }}</span>
                </div>

                <!-- Harga Beli -->
                <div class="flex flex-col gap-1">
                    <label class="text-[10px] font-bold text-muted-foreground uppercase">Harga Beli</label>
                    <Input type="number" v-model.number="editForm.harga_beli" placeholder="Masukkan harga beli..." class="h-9 text-xs" />
                    <span v-if="editForm.errors.harga_beli" class="text-[10px] text-red-500 font-medium">{{ editForm.errors.harga_beli }}</span>
                </div>

                <!-- Harga Jual -->
                <div class="flex flex-col gap-1">
                    <label class="text-[10px] font-bold text-muted-foreground uppercase">Harga Jual</label>
                    <Input type="number" v-model.number="editForm.harga_jual" placeholder="Masukkan harga jual..." class="h-9 text-xs" />
                    <span v-if="editForm.errors.harga_jual" class="text-[10px] text-red-500 font-medium">{{ editForm.errors.harga_jual }}</span>
                </div>

                <!-- Stok -->
                <div class="flex flex-col gap-1">
                    <label class="text-[10px] font-bold text-muted-foreground uppercase">Jumlah Stok</label>
                    <Input type="number" v-model.number="editForm.stok" placeholder="Masukkan stok..." class="h-9 text-xs" />
                    <span v-if="editForm.errors.stok" class="text-[10px] text-red-500 font-medium">{{ editForm.errors.stok }}</span>
                </div>

                <!-- Footer buttons -->
                <div class="flex justify-end gap-2 border-t pt-3 mt-2">
                    <Button type="button" variant="outline" @click="showEditModal = false" class="h-8 text-xs">Batal</Button>
                    <Button type="submit" class="h-8 text-xs bg-orange-600 text-white hover:bg-orange-700" :disabled="editForm.processing">Simpan Perubahan</Button>
                </div>
            </form>
        </div>
    </div>
</template>
