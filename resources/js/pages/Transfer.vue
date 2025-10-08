<script setup lang="ts">
import CreateTransactionsController from '@/actions/App/Http/Controllers/Transactions/CreateTransactionsController';
import GetTransactionsController from '@/actions/App/Http/Controllers/Transactions/GetTransactionsController';
import api from '@/bootstrap';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { APIResponse, GetTransactionsControllerResponse, Transaction, type BreadcrumbItem } from '@/types';
import { Head, usePage } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';
import { onMounted, reactive, ref } from 'vue';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
];

const page = usePage();
const user = page.props.auth.user;
const currentUserId = user.id;

const form = reactive({
  receiver_id: '',
  amount: '',
});

const balance = ref('0.00');
const transactions = ref<Array<Transaction>>([]);
const loading = ref(false);
const errorMessage = ref<string | null>(null);

async function loadTransactions() {
  try {
    const res = await api.get<APIResponse<GetTransactionsControllerResponse>>(GetTransactionsController.get().url, { params: { per_page: 20 } });
    const payload = res.data?.data;

    console.log('[Transfer] GET /api/v1/transactions', payload);
    balance.value = payload?.balance ?? balance.value;
    transactions.value = payload?.transactions ?? [];
  } catch (e) {
    console.error(e);
  }
}

async function submit() {
  errorMessage.value = null;
  loading.value = true;

  try {
    await api.post(CreateTransactionsController.post().url, {
      receiver_id: form.receiver_id,
      amount: form.amount,
    });

    // success: clear form and reload
    form.receiver_id = '';
    form.amount = '';
    await loadTransactions();
  } catch (err: any) {
    const resp = err?.response;
    if (resp?.status === 422 && resp.data?.errors) {
      errorMessage.value = Object.values(resp.data.errors).flat().join(' ');
    } else if (resp?.data?.message) {
      errorMessage.value = resp.data.message;
    } else {
      errorMessage.value = 'Request failed';
    }
    // console.error('[Transfer] submit error', resp ?? err);
  } finally {
    loading.value = false;
  }
}

/**
 * Listen for server broadcasts (Laravel Echo) on the private user channel
 * and update local balance/transactions in real time.
 */
function setupRealtime() {
  const Echo = (window as any).Echo;

  if (!Echo || !currentUserId) {
    return
};

  Echo.private(`user.${currentUserId}`).listen('TransactionCreated', (payload: any) => {
    try {
      const transaction = payload.transaction;
      const balances = payload.balances ?? {};

      // Update balance if payload contains it
      if (transaction.sender_id === currentUserId && balances.sender) {
        balance.value = balances.sender;
      } else if (transaction.receiver_id === currentUserId && balances.receiver) {
        balance.value = balances.receiver;
      }

      // Prepend transaction if relevant to this user
      if (transaction.sender_id === currentUserId || transaction.receiver_id === currentUserId) {
        transactions.value = [transaction, ...transactions.value].slice(0, 50); // keep recent 50
      }
    } catch (e) {
      console.error('Error processing TransactionCreated event', e);
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
        <div class="rounded-lg border p-4 col-span-1 md:col-span-2" @submit.prevent="submit" method="post">
        <h3 class="text-sm text-muted-foreground mb-4">Transfer</h3>

        <form @submit.prevent="submit" class="grid gap-4 sm:grid-cols-2">
             <div class="grid gap-2">
              <Label for="receiver">Recipient user ID</Label>
              <Input
                id="receiver"
                v-model="form.receiver_id"
                type="number"
                placeholder="Recipient user id"
                autocomplete="off"
              />

            </div>

             <div class="grid gap-2">
              <Label for="amount">Amount</Label>
              <Input
                id="amount"
                v-model="form.amount"
                type="text"
                placeholder="0.00"
                inputmode="decimal"
              />
            </div>

            <div class="flex items-center flex-row gap-4 sm:col-span-2">
                <Button
                    type="submit"
                    :tabindex="4"
                    :disabled="loading"
                    data-test="login-button"
                >
                    <LoaderCircle
                        v-if="loading"
                        class="h-4 w-4 animate-spin"
                    />
                    Send
                </Button>

                <div v-if="errorMessage" class="text-sm text-red-600">
                {{ errorMessage }}
                </div>
            </div>
        </form>


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
          <li v-for="transaction in transactions" :key="transaction.id" class="py-2 flex justify-between">
            <div>
              <div class="text-sm">
                <strong v-if="transaction.sender_id === currentUserId">Sent</strong>
                <strong v-else>Received</strong>
                <span class="ml-2 text-muted-foreground text-xs">#{{ transaction.id }}</span>
              </div>
              <div class="text-xs text-muted-foreground">
                {{ new Date(transaction.created_at).toLocaleString() }}
              </div>
            </div>
            <div class="text-right">
              <div :class="transaction.sender_id === currentUserId ? 'text-red-600' : 'text-green-600'">
                {{ transaction.sender_id === currentUserId ? '-' : '+' }}${{ parseFloat(transaction.amount).toFixed(2) }}
              </div>
              <div class="text-xs text-muted-foreground">Fee: ${{ parseFloat(transaction.commission_fee).toFixed(2) }}</div>
            </div>
          </li>
        </ul>
      </div>
    </div>
  </AppLayout>
</template>
