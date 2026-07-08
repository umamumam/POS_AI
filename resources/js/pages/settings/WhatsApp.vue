<script setup lang="ts">
import { ref } from 'vue';
import { Form, Head } from '@inertiajs/vue3';
import WaConfigController from '@/actions/App/Http/Controllers/Settings/WaConfigController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { toast } from 'vue-sonner';
import { Copy, Check, ExternalLink } from '@lucide/vue';

type Props = {
    config: {
        monitored_devices: string;
        allowed_senders: string;
        is_active: boolean;
        api_token: string;
    };
};

const props = defineProps<Props>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'WhatsApp settings',
                href: '/settings/whatsapp',
            },
        ],
    },
});

const webhookUrl = window.location.origin + '/webhooks/fonnte';
const copied = ref(false);

function copyWebhook() {
    navigator.clipboard.writeText(webhookUrl);
    copied.value = true;
    toast.success('URL Webhook berhasil disalin!');
    setTimeout(() => {
        copied.value = false;
    }, 2000);
}
</script>

<template>
    <Head title="WhatsApp Settings" />

    <h1 class="sr-only">WhatsApp settings</h1>

    <div class="flex flex-col space-y-6">
        <Heading
            variant="small"
            title="WhatsApp Notification"
            description="Konfigurasi webhook Fonnte untuk menampilkan notifikasi pesan WA masuk di web POS secara real-time"
        />

        <!-- Webhook Info Box -->
        <div class="rounded-lg border border-orange-200/50 bg-orange-50/50 p-4 dark:border-orange-900/30 dark:bg-orange-950/20">
            <h3 class="text-sm font-semibold text-orange-800 dark:text-orange-300">Petunjuk Setup Webhook Fonnte</h3>
            <p class="mt-1 text-xs text-orange-700/90 dark:text-orange-400/90">
                Salin URL di bawah ini lalu tempelkan (paste) pada bagian <strong>Webhook URL</strong> di dashboard Fonnte (menu Device -> Edit) agar sistem Fonnte dapat mengirimkan pesan WA ke aplikasi POS ini.
            </p>
            
            <div class="mt-3 flex items-center gap-2">
                <div class="flex-1 rounded border border-muted bg-background px-3 py-1.5 font-mono text-xs select-all overflow-x-auto whitespace-nowrap text-muted-foreground">
                    {{ webhookUrl }}
                </div>
                <Button 
                    type="button" 
                    variant="outline" 
                    size="icon" 
                    class="h-8 w-8 shrink-0" 
                    @click="copyWebhook"
                >
                    <Check v-if="copied" class="h-4 w-4 text-green-600" />
                    <Copy v-else class="h-4 w-4" />
                </Button>
            </div>
            
            <div class="mt-3">
                <a 
                    href="https://md.fonnte.com" 
                    target="_blank" 
                    rel="noopener noreferrer" 
                    class="inline-flex items-center text-xs font-medium text-orange-700 hover:text-orange-900 dark:text-orange-400 dark:hover:text-orange-300 gap-1"
                >
                    Buka Dashboard Fonnte
                    <ExternalLink class="h-3.5 w-3.5" />
                </a>
            </div>
        </div>

        <Form
            v-bind="WaConfigController.update.form()"
            class="space-y-6"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-2">
                <Label for="is_active">Status Notifikasi WA</Label>
                <select
                    id="is_active"
                    name="is_active"
                    class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs transition-colors file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-hidden focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                >
                    <option value="1" :selected="props.config.is_active">Aktif</option>
                    <option value="0" :selected="!props.config.is_active">Nonaktif (Matikan Notifikasi)</option>
                </select>
                <p class="text-[11px] text-muted-foreground">Aktifkan atau matikan seluruh notifikasi WhatsApp di web.</p>
                <InputError class="mt-1" :message="errors.is_active" />
            </div>

            <div class="grid gap-2">
                <Label for="monitored_devices">Nomor WA Anda (Monitored Devices)</Label>
                <Input
                    id="monitored_devices"
                    class="mt-1 block w-full"
                    name="monitored_devices"
                    :default-value="props.config.monitored_devices"
                    placeholder="Contoh: 085799352991, 6285201454015"
                />
                <p class="text-[11px] text-muted-foreground">Masukkan nomor WhatsApp Anda yang terdaftar di Fonnte. Pisahkan dengan tanda koma ( , ) jika memantau lebih dari satu nomor.</p>
                <InputError class="mt-1" :message="errors.monitored_devices" />
            </div>

            <div class="grid gap-2">
                <Label for="allowed_senders">Nomor Pengirim yang Dipantau (Allowed Senders)</Label>
                <Input
                    id="allowed_senders"
                    class="mt-1 block w-full"
                    name="allowed_senders"
                    :default-value="props.config.allowed_senders"
                    placeholder="Contoh: 628123456789, 628987654321"
                />
                <p class="text-[11px] text-muted-foreground">Hanya pesan masuk dari nomor-nomor ini yang akan memicu notifikasi toast di web POS. Pisahkan dengan tanda koma ( , ).</p>
                <InputError class="mt-1" :message="errors.allowed_senders" />
            </div>

            <div class="grid gap-2">
                <Label for="api_token">Fonnte API Token</Label>
                <Input
                    id="api_token"
                    class="mt-1 block w-full font-mono"
                    name="api_token"
                    :default-value="props.config.api_token"
                    placeholder="Masukkan token API Fonnte Anda"
                />
                <p class="text-[11px] text-muted-foreground">Diperlukan jika aplikasi POS ini memerlukan otentikasi API dengan Fonnte (opsional).</p>
                <InputError class="mt-1" :message="errors.api_token" />
            </div>

            <div class="flex items-center gap-4">
                <Button :disabled="processing">Simpan Konfigurasi</Button>
            </div>
        </Form>
    </div>
</template>
