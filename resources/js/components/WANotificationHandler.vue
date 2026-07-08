<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted } from 'vue';
import { useWANotifications } from '@/composables/useWANotifications';

const page = usePage();
const isAuthenticated = computed(() => !!page.props.auth?.user);
const { fetchUnread, requestDesktopPermission } = useWANotifications();

async function poll() {
    if (!isAuthenticated.value) return;
    await fetchUnread();
}

let intervalId: any = null;

onMounted(() => {
    // Minta izin notifikasi desktop saat di-mount
    requestDesktopPermission();

    // Jalankan segera setelah di-mount
    poll();

    // Jalankan periodik setiap 5 detik
    intervalId = setInterval(poll, 5000);
});

onUnmounted(() => {
    if (intervalId) {
        clearInterval(intervalId);
    }
});
</script>

<template>
    <!-- Komponen ini tidak me-render apapun secara visual -->
    <div class="hidden" />
</template>
