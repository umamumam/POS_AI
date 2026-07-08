<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted } from 'vue';
import { toast } from 'vue-sonner';

const page = usePage();
const isAuthenticated = computed(() => !!page.props.auth?.user);

async function fetchNotifications() {
    if (!isAuthenticated.value) return;

    try {
        const response = await fetch('/api/wa-notifications/unread');
        if (!response.ok) return;
        
        const data = await response.json();

        if (data && data.length > 0) {
            // Tampilkan toast untuk setiap pesan baru
            data.forEach((item: any) => {
                toast.info(`WA dari ${item.name || item.sender}`, {
                    description: item.message,
                    duration: 10000,
                });
            });

            // Tandai pesan-pesan tersebut sebagai sudah dibaca
            const ids = data.map((item: any) => item.id);
            const csrfToken = page.props.csrf_token as string;

            await fetch('/api/wa-notifications/mark-read', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({ ids }),
            });
        }
    } catch (error) {
        console.error('Error fetching WA notifications:', error);
    }
}

let intervalId: any = null;

onMounted(() => {
    // Jalankan segera setelah di-mount
    fetchNotifications();

    // Jalankan periodik setiap 5 detik
    intervalId = setInterval(fetchNotifications, 5000);
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
