<script setup lang="ts">
import GetTransactionsController from '@/actions/App/Http/Controllers/Transactions/GetTransactionsController';
import api from '@/bootstrap';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { Transaction, type BreadcrumbItem } from '@/types';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
];

const page = usePage();
const user = page.props.auth.user;
const currentUserId = user.id;

const form = useForm({
  receiver_id: '',
  amount: '',
});

const balance = ref('0.00');
const transactions = ref<Array<Transaction>>([]);
const loading = ref(false);
const errorMessage = ref<string | null>(null);

async function loadTransactions() {
  try {
    // uses axios configured baseURL '/api/v1' so this calls GET /api/v1/transactions
    const res = await api.get('/transactions', { params: { per_page: 20 } });
    const payload = res.data?.data;
    // helpful debug
    // console.log('[Transfer] GET /api/v1/transactions', payload);
    balance.value = payload?.balance ?? balance.value;
    transactions.value = payload?.transactions?.data ?? [];
  } catch (e) {
    // silent
  }
}

function submit() {
  errorMessage.value = null;
  loading.value = true;

  form.post('/api/transactions', {
    preserveState: false,
    onSuccess: (page) => {
      form.reset('receiver_id', 'amount');
    },
    onError: (errors) => {
      errorMessage.value = Object.values(errors).flat().join(' ');
    },
    onFinish: () => {
      loading.value = false;
      // reload balance + transactions after POST (also real-time events will update)
      loadTransactions();
    },
  });
}

/**
 * Listen for server broadcasts (Laravel Echo) on the private user channel
 * and update local balance/transactions in real time.
 */
function setupRealtime() {
  // window.Echo is expected to be configured in your app bootstrap
  const Echo = (window as any).Echo;
  if (!Echo || !currentUserId) return;

  Echo.private(`user.${currentUserId}`).listen('TransactionCreated', (payload: any) => {
    try {
      const tx = payload.transaction;
      const balances = payload.balances ?? {};

      // Update balance if payload contains it
      if (tx.sender_id === currentUserId && balances.sender) {
        balance.value = balances.sender;
      } else if (tx.receiver_id === currentUserId && balances.receiver) {
        balance.value = balances.receiver;
      }

      // Prepend transaction if relevant to this user
      if (tx.sender_id === currentUserId || tx.receiver_id === currentUserId) {
        transactions.value = [tx, ...transactions.value].slice(0, 50); // keep recent 50
      }
    } catch (e) {
      // ignore malformed payloads
    }
  });
}

onMounted(async () => {
  await loadTransactions();
  setupRealtime();
});
</script>

<template>
    <Head title="Transfer" />

   <AppLayout :breadcrumbs="breadcrumbs">
    <div class="flex h-full flex-1 flex-col gap-6 p-6">
      <div class="grid md:grid-cols-3 gap-6">
        <!-- Balance card -->
        <div class="rounded-lg border p-4">
          <h3 class="text-sm text-muted-foreground mb-2">Current balance</h3>
          <div class="text-2xl font-medium">${{ balance }}</div>
        </div>

        <!-- Transfer form -->
        <div class="rounded-lg border p-4 col-span-1 md:col-span-2">
          <h3 class="text-sm text-muted-foreground mb-4">Send funds</h3>

          <div class="grid gap-4 sm:grid-cols-2">
            <div>
              <Label for="receiver">Recipient user ID</Label>
              <Input
                id="receiver"
                v-model="form.receiver_id"
                type="number"
                placeholder="Recipient user id"
                autocomplete="off"
              />
            </div>

            <div>
              <Label for="amount">Amount</Label>
              <Input
                id="amount"
                v-model="form.amount"
                type="text"
                placeholder="0.00"
                inputmode="decimal"
              />
            </div>
          </div>

          <div class="mt-4 flex items-center gap-3">
            <Button type="button" :disabled="loading" @click="submit">
              <span v-if="!loading">Send</span>
              <span v-else>Sending…</span>
            </Button>

            <div v-if="errorMessage" class="text-sm text-red-600">
              {{ errorMessage }}
            </div>
          </div>

          <p class="mt-3 text-xs text-muted-foreground">
            Commission: 1.5% (debited from sender). Amounts shown with 2 decimal places.
          </p>
        </div>
      </div>

      <!-- Recent transactions -->
      <div class="rounded-lg border p-4">
        <h4 class="mb-3">Recent transactions</h4>

        <div v-if="transactions.length === 0" class="text-sm text-muted-foreground">
          No recent transactions.
        </div>

        <ul class="divide-y">
          <li v-for="tx in transactions" :key="tx.id" class="py-2 flex justify-between">
            <div>
              <div class="text-sm">
                <strong v-if="tx.sender_id === currentUserId">Sent</strong>
                <strong v-else>Received</strong>
                <span class="ml-2 text-muted-foreground text-xs">#{{ tx.id }}</span>
              </div>
              <div class="text-xs text-muted-foreground">
                {{ new Date(tx.created_at).toLocaleString() }}
              </div>
            </div>
            <div class="text-right">
              <div :class="tx.sender_id === currentUserId ? 'text-red-600' : 'text-green-600'">
                {{ tx.sender_id === currentUserId ? '-' : '+' }}${{ parseFloat(tx.amount).toFixed(2) }}
              </div>
              <div class="text-xs text-muted-foreground">Fee: ${{ parseFloat(tx.commission_fee).toFixed(2) }}</div>
            </div>
          </li>
        </ul>
      </div>
    </div>
  </AppLayout>
</template>
