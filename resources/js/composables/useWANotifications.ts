import { ref, computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';

export interface WaNotification {
    id: number;
    device: string;
    sender: string;
    name: string | null;
    message: string | null;
    is_read: boolean;
    created_at: string;
}

const notifications = ref<WaNotification[]>([]);
const unreadCount = computed(() => notifications.value.length);
const toastedIds = new Set<number>();

// Web Audio API chime synthesis for robust sound notifications without external dependencies
function playChime() {
    try {
        const AudioContextClass = window.AudioContext || (window as any).webkitAudioContext;
        if (!AudioContextClass) return;
        
        const ctx = new AudioContextClass();
        const now = ctx.currentTime;
        
        // Note 1: Clear sweet bell-like chime (D5)
        const osc1 = ctx.createOscillator();
        const gain1 = ctx.createGain();
        osc1.type = 'sine';
        osc1.frequency.setValueAtTime(587.33, now); // D5
        osc1.frequency.exponentialRampToValueAtTime(880, now + 0.15); // A5
        
        gain1.gain.setValueAtTime(0.25, now);
        gain1.gain.exponentialRampToValueAtTime(0.001, now + 0.5);
        
        osc1.connect(gain1);
        gain1.connect(ctx.destination);
        osc1.start(now);
        osc1.stop(now + 0.5);
        
        // Note 2: Harmonized response chime (A5 -> D6)
        const osc2 = ctx.createOscillator();
        const gain2 = ctx.createGain();
        osc2.type = 'sine';
        osc2.frequency.setValueAtTime(880, now + 0.08); // A5
        osc2.frequency.exponentialRampToValueAtTime(1174.66, now + 0.23); // D6
        
        gain2.gain.setValueAtTime(0.18, now + 0.08);
        gain2.gain.exponentialRampToValueAtTime(0.001, now + 0.6);
        
        osc2.connect(gain2);
        gain2.connect(ctx.destination);
        osc2.start(now + 0.08);
        osc2.stop(now + 0.6);
    } catch (e) {
        console.error('AudioContext chime error:', e);
    }
}

// Request permission for native desktop notifications
function requestDesktopPermission() {
    if ('Notification' in window && Notification.permission === 'default') {
        Notification.requestPermission();
    }
}

// Trigger native browser notification
function showDesktopNotification(title: string, body: string) {
    if ('Notification' in window && Notification.permission === 'granted') {
        try {
            new Notification(title, {
                body: body,
                tag: 'wa-pos-notification',
                renotify: true,
            });
        } catch (e) {
            console.error('Browser Notification error:', e);
        }
    }
}

// Fetch unread notifications from backend
async function fetchUnread() {
    try {
        const response = await fetch('/api/wa-notifications/unread');
        if (!response.ok) return;
        
        const data: WaNotification[] = await response.json();
        
        let hasNew = false;
        const newNotifications: WaNotification[] = [];
        
        data.forEach(item => {
            if (!toastedIds.has(item.id)) {
                toastedIds.add(item.id);
                hasNew = true;
                newNotifications.push(item);
                
                // Show standard UI toast
                toast.info(`WA dari ${item.name || item.sender}`, {
                    description: item.message || '',
                    duration: 10000,
                });
                
                // Show native desktop notification
                showDesktopNotification(
                    `Pesan WA: ${item.name || item.sender}`,
                    item.message || 'Mengirim pesan baru'
                );
            }
        });
        
        if (hasNew) {
            playChime();
        }
        
        // Update local reactive notifications state
        notifications.value = data;
    } catch (e) {
        console.error('Error fetching WA notifications:', e);
    }
}

// Mark specified notification IDs as read in database
async function markAsRead(ids: number[]) {
    if (ids.length === 0) return;
    
    // Optimistically filter them out from local state
    notifications.value = notifications.value.filter(n => !ids.includes(n.id));
    
    try {
        const page = usePage();
        const csrfToken = page.props.csrf_token as string;
        
        await fetch('/api/wa-notifications/mark-read', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            },
            body: JSON.stringify({ ids }),
        });
    } catch (e) {
        console.error('Error marking notifications as read:', e);
    }
}

// Mark all current unread notifications as read
async function markAllRead() {
    const ids = notifications.value.map(n => n.id);
    await markAsRead(ids);
}

export function useWANotifications() {
    return {
        notifications,
        unreadCount,
        fetchUnread,
        markAsRead,
        markAllRead,
        requestDesktopPermission,
        playChime,
    };
}
