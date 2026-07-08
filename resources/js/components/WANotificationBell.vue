<script setup lang="ts">
import { useWANotifications } from '@/composables/useWANotifications';
import { Bell, MessageSquare, Trash2, CheckCheck } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

const { notifications, unreadCount, markAsRead, markAllRead } = useWANotifications();

function formatTime(dateTimeStr: string): string {
    try {
        const date = new Date(dateTimeStr);
        return date.toLocaleTimeString('id-ID', {
            hour: '2-digit',
            minute: '2-digit',
        });
    } catch (e) {
        return '';
    }
}
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger :as-child="true">
            <Button
                variant="ghost"
                size="icon"
                class="relative h-9 w-9 cursor-pointer group"
                aria-label="Notifikasi WhatsApp"
            >
                <Bell class="size-5 opacity-80 transition-opacity group-hover:opacity-100" />
                
                <!-- Badge merah penunjuk unread count -->
                <span
                    v-if="unreadCount > 0"
                    class="absolute -top-0.5 -right-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-red-600 px-1 text-[9px] font-bold text-white shadow-xs"
                >
                    {{ unreadCount }}
                </span>
            </Button>
        </DropdownMenuTrigger>
        
        <DropdownMenuContent align="end" class="w-80 p-0 shadow-md border border-neutral-200/80 dark:border-neutral-800">
            <!-- Header Dropdown -->
            <div class="flex items-center justify-between border-b border-muted p-3">
                <span class="text-xs font-semibold text-foreground flex items-center gap-1.5">
                    <MessageSquare class="h-3.5 w-3.5 text-orange-500" />
                    Pesan WA Masuk ({{ unreadCount }})
                </span>
                <Button
                    v-if="unreadCount > 0"
                    variant="ghost"
                    size="sm"
                    class="h-7 px-2 text-[10px] text-muted-foreground hover:text-foreground cursor-pointer flex items-center gap-1"
                    @click="markAllRead"
                >
                    <CheckCheck class="h-3.5 w-3.5" />
                    Tandai Semua Dibaca
                </Button>
            </div>
            
            <!-- List Notifikasi -->
            <div class="max-h-72 overflow-y-auto">
                <div v-if="unreadCount === 0" class="flex flex-col items-center justify-center py-8 px-4 text-center">
                    <Bell class="h-8 w-8 text-muted-foreground/40 stroke-[1.5] mb-2" />
                    <p class="text-xs text-muted-foreground font-medium">Tidak ada pesan WhatsApp baru</p>
                    <p class="text-[10px] text-muted-foreground/75 mt-0.5">Pesan terfilter akan muncul di sini</p>
                </div>
                
                <div v-else class="divide-y divide-muted">
                    <div
                        v-for="item in notifications"
                        :key="item.id"
                        class="p-3 transition-colors hover:bg-muted/30 group flex gap-3 relative"
                    >
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-semibold text-foreground truncate block">
                                    {{ item.name || item.sender }}
                                </span>
                                <span class="text-[9px] text-muted-foreground font-mono shrink-0">
                                    {{ formatTime(item.created_at) }}
                                </span>
                            </div>
                            <p class="text-[11px] text-muted-foreground/90 mt-1 line-clamp-2 break-words">
                                {{ item.message }}
                            </p>
                        </div>
                        
                        <!-- Tombol tandai dibaca per item (ikon tong sampah/check) -->
                        <div class="flex items-center shrink-0">
                            <Button
                                variant="ghost"
                                size="icon"
                                class="h-6 w-6 opacity-0 group-hover:opacity-100 hover:bg-muted transition-opacity cursor-pointer text-muted-foreground/80 hover:text-red-500"
                                title="Tandai dibaca"
                                @click="markAsRead([item.id])"
                            >
                                <Trash2 class="h-3.5 w-3.5" />
                            </Button>
                        </div>
                    </div>
                </div>
            </div>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
