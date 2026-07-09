<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, nextTick, watch } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import { MessageCircle, Search, Send, Check, CheckCheck, Loader2 } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Card } from '@/components/ui/card';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { getInitials } from '@/composables/useInitials';

interface Message {
    id: number;
    message: string;
    is_outgoing: boolean;
    time: string;
}

interface Conversation {
    phone: string;
    name: string;
    last_message: string;
    last_message_time: string;
    unread_count: number;
    messages: Message[];
}

const page = usePage();
const csrfToken = computed(() => page.props.csrf_token as string);

const conversations = ref<Conversation[]>([]);
const activePhone = ref<string | null>(null);
const searchQuery = ref('');
const replyText = ref('');
const isSending = ref(false);
const messageContainer = ref<HTMLDivElement | null>(null);
let pollIntervalId: any = null;

const activeConversation = computed(() => {
    return conversations.value.find(c => c.phone === activePhone.value) || null;
});

const filteredConversations = computed(() => {
    if (!searchQuery.value.trim()) return conversations.value;
    const query = searchQuery.value.toLowerCase();
    return conversations.value.filter(
        c => c.name.toLowerCase().includes(query) || c.phone.includes(query)
    );
});

// Scroll message container to bottom
function scrollToBottom() {
    nextTick(() => {
        if (messageContainer.value) {
            messageContainer.value.scrollTop = messageContainer.value.scrollHeight;
        }
    });
}

// Fetch conversations from backend
async function fetchConversations() {
    try {
        const response = await fetch('/api/wa-notifications/today');
        if (response.ok) {
            const data = await response.json();
            conversations.value = data;
            
            // Auto mark read if we are looking at an active conversation
            if (activePhone.value) {
                const active = data.find((c: Conversation) => c.phone === activePhone.value);
                if (active && active.unread_count > 0) {
                    markConversationAsRead(active);
                }
            }
        }
    } catch (e) {
        console.error('Failed to fetch WA conversations:', e);
    }
}

// Mark all messages from a conversation as read
async function markConversationAsRead(conv: Conversation) {
    const unreadIds = conv.messages
        .filter((m: any) => !m.is_outgoing) // Only mark incoming messages
        .map(m => m.id);
        
    if (unreadIds.length === 0) return;
    
    // Optimistically update count
    conv.unread_count = 0;
    
    try {
        await fetch('/api/wa-notifications/mark-read', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken.value,
            },
            body: JSON.stringify({ ids: unreadIds }),
        });
    } catch (e) {
        console.error('Failed to mark messages as read:', e);
    }
}

// Handle clicking a conversation
function selectConversation(conv: Conversation) {
    activePhone.value = conv.phone;
    markConversationAsRead(conv);
    scrollToBottom();
}

// Send reply message
async function sendReply() {
    if (!activePhone.value || !replyText.value.trim() || isSending.value) return;
    
    isSending.value = true;
    const phone = activePhone.value;
    const text = replyText.value;
    replyText.value = ''; // Clear input immediately
    
    try {
        const response = await fetch('/api/wa-notifications/reply', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken.value,
            },
            body: JSON.stringify({
                phone,
                message: text,
            }),
        });
        
        const resData = await response.json();
        if (resData.success) {
            // Append message locally for immediate UI update
            const active = conversations.value.find(c => c.phone === phone);
            if (active) {
                active.messages.push(resData.data);
                active.last_message = text;
                active.last_message_time = resData.data.time;
            }
            scrollToBottom();
        } else {
            alert('Gagal mengirim pesan: ' + resData.message);
            replyText.value = text; // Restore text
        }
    } catch (e) {
        console.error('Error sending reply:', e);
        replyText.value = text; // Restore text
    } finally {
        isSending.value = false;
    }
}

// Format relative/friendly time
function formatMsgTime(timeStr: string): string {
    try {
        const date = new Date(timeStr);
        return date.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
    } catch (e) {
        return '';
    }
}

// Watch active chat to scroll to bottom
watch(activePhone, () => {
    scrollToBottom();
});

onMounted(() => {
    fetchConversations();
    // Poll updates every 4 seconds
    pollIntervalId = setInterval(fetchConversations, 4000);
});

onUnmounted(() => {
    if (pollIntervalId) {
        clearInterval(pollIntervalId);
    }
});
</script>

<template>
    <Head title="WhatsApp Pesan Hari Ini" />
    
    <div class="flex h-[calc(100vh-4.5rem)] overflow-hidden rounded-xl border border-neutral-200/80 dark:border-neutral-800 bg-white dark:bg-neutral-950 shadow-xs">
            
            <!-- Left Sidebar: Senders List -->
            <div class="w-80 flex flex-col border-r border-neutral-200/80 dark:border-neutral-800 shrink-0 bg-neutral-50/50 dark:bg-neutral-900/10">
                <!-- Sidebar Header & Search -->
                <div class="p-4 border-b border-neutral-200/80 dark:border-neutral-800 space-y-3">
                    <h2 class="text-sm font-semibold text-foreground flex items-center gap-2">
                        <MessageCircle class="h-4.5 w-4.5 text-orange-500 fill-orange-500/20" />
                        Pesan WA Hari Ini
                    </h2>
                    <div class="relative">
                        <Search class="absolute left-3 top-2.5 h-4 w-4 text-muted-foreground" />
                        <Input
                            v-model="searchQuery"
                            placeholder="Cari nama atau nomor..."
                            class="pl-9 h-9"
                        />
                    </div>
                </div>
                
                <!-- Senders List -->
                <div class="flex-1 overflow-y-auto divide-y divide-neutral-100 dark:divide-neutral-900">
                    <div v-if="filteredConversations.length === 0" class="flex flex-col items-center justify-center py-12 px-4 text-center">
                        <MessageCircle class="h-10 w-10 text-muted-foreground/35 stroke-[1.5] mb-2" />
                        <p class="text-xs text-muted-foreground font-medium">Tidak ada chat hari ini</p>
                        <p class="text-[10px] text-muted-foreground/75 mt-0.5">Pesan WA masuk hari ini akan tampil di sini</p>
                    </div>
                    
                    <button
                        v-for="conv in filteredConversations"
                        :key="conv.phone"
                        class="w-full text-left p-3.5 flex items-start gap-3 transition-colors hover:bg-neutral-100/50 dark:hover:bg-neutral-900/50 cursor-pointer"
                        :class="{ 'bg-neutral-100 dark:bg-neutral-900': activePhone === conv.phone }"
                        @click="selectConversation(conv)"
                    >
                        <Avatar class="size-9 border border-neutral-200/60 dark:border-neutral-800 shrink-0">
                            <AvatarFallback class="bg-orange-100 text-orange-700 dark:bg-orange-950/40 dark:text-orange-400 font-semibold text-xs">
                                {{ getInitials(conv.name) }}
                            </AvatarFallback>
                        </Avatar>
                        
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-semibold text-foreground truncate block">
                                    {{ conv.name }}
                                </span>
                                <span class="text-[9px] text-muted-foreground font-mono">
                                    {{ formatMsgTime(conv.last_message_time) }}
                                </span>
                            </div>
                            <div class="flex items-center justify-between mt-1">
                                <p class="text-[11px] text-muted-foreground truncate flex-1 pr-2">
                                    <span v-if="conv.messages[conv.messages.length - 1]?.is_outgoing" class="text-neutral-500 font-medium mr-0.5">Anda:</span>
                                    {{ conv.last_message }}
                                </p>
                                <span
                                    v-if="conv.unread_count > 0"
                                    class="bg-red-500 text-white font-bold text-[9px] px-1.5 py-0.5 rounded-full shrink-0 flex items-center justify-center min-w-4 h-4"
                                >
                                    {{ conv.unread_count }}
                                </span>
                            </div>
                        </div>
                    </button>
                </div>
            </div>
            
            <!-- Right Pane: Active Chat Conversation -->
            <div class="flex-1 flex flex-col bg-neutral-50/20 dark:bg-neutral-950">
                <template v-if="activeConversation">
                    <!-- Chat Header -->
                    <div class="h-16 px-6 border-b border-neutral-200/80 dark:border-neutral-800 flex items-center justify-between bg-white dark:bg-neutral-900/10">
                        <div class="flex items-center gap-3">
                            <Avatar class="size-9 border border-neutral-200/60 dark:border-neutral-800">
                                <AvatarFallback class="bg-orange-100 text-orange-700 dark:bg-orange-950/40 dark:text-orange-400 font-semibold text-xs">
                                    {{ getInitials(activeConversation.name) }}
                                </AvatarFallback>
                            </Avatar>
                            <div>
                                <h3 class="text-xs font-semibold text-foreground">
                                    {{ activeConversation.name }}
                                </h3>
                                <p class="text-[10px] text-muted-foreground font-mono mt-0.5">
                                    {{ activeConversation.phone }}
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Chat Messages Area -->
                    <div
                        ref="messageContainer"
                        class="flex-1 overflow-y-auto p-6 space-y-4 bg-[radial-gradient(#e5e7eb_1px,transparent_1px)] dark:bg-[radial-gradient(#262626_1px,transparent_1px)] [background-size:16px_16px]"
                    >
                        <div
                            v-for="msg in activeConversation.messages"
                            :key="msg.id"
                            class="flex w-full"
                            :class="msg.is_outgoing ? 'justify-end' : 'justify-start'"
                        >
                            <div
                                class="max-w-[70%] rounded-2xl px-4 py-2.5 text-xs shadow-xs relative group"
                                :class="msg.is_outgoing
                                    ? 'bg-neutral-900 text-white rounded-tr-none dark:bg-white dark:text-black'
                                    : 'bg-white text-foreground border border-neutral-200/60 rounded-tl-none dark:bg-neutral-900 dark:border-neutral-800'"
                            >
                                <p class="break-words leading-relaxed select-text">{{ msg.message }}</p>
                                <div class="flex items-center justify-end gap-1 mt-1 text-[8px]" :class="msg.is_outgoing ? 'text-neutral-300 dark:text-neutral-500' : 'text-neutral-400'">
                                    <span>{{ formatMsgTime(msg.time) }}</span>
                                    <span v-if="msg.is_outgoing">
                                        <CheckCheck class="h-3 w-3 text-emerald-400 dark:text-emerald-600" />
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Chat Input Reply Area -->
                    <form @submit.prevent="sendReply" class="p-4 border-t border-neutral-200/80 dark:border-neutral-800 bg-white dark:bg-neutral-900/10 flex items-center gap-3">
                        <Input
                            v-model="replyText"
                            placeholder="Ketik balasan WhatsApp..."
                            class="flex-1 h-10 px-4 focus-visible:ring-1"
                            :disabled="isSending"
                            autocomplete="off"
                        />
                        <Button
                            type="submit"
                            size="icon"
                            class="h-10 w-10 shrink-0 cursor-pointer"
                            :disabled="!replyText.trim() || isSending"
                        >
                            <Loader2 v-if="isSending" class="h-4 w-4 animate-spin" />
                            <Send v-else class="h-4 w-4" />
                        </Button>
                    </form>
                </template>
                
                <!-- Chat Placeholder (No chat selected) -->
                <div v-else class="flex-1 flex flex-col items-center justify-center p-8 text-center bg-white dark:bg-neutral-950">
                    <div class="h-16 w-16 bg-neutral-100 dark:bg-neutral-900 rounded-full flex items-center justify-center mb-4">
                        <MessageCircle class="h-8 w-8 text-muted-foreground stroke-[1.5]" />
                    </div>
                    <h3 class="text-sm font-semibold text-foreground">WhatsApp Chat Online</h3>
                    <p class="text-xs text-muted-foreground/80 max-w-sm mt-1">
                        Pilih kontak di sebelah kiri untuk melihat percakapan hari ini dan membalas langsung dari sini.
                    </p>
                </div>
            </div>
            
        </div>
</template>
