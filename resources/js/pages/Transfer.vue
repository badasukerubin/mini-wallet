<script setup lang="ts">
import CreateTransactionsController from '@/actions/App/Http/Controllers/Transactions/CreateTransactionsController';
import GetTransactionsController from '@/actions/App/Http/Controllers/Transactions/GetTransactionsController';
import api from '@/bootstrap';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import {
    APIResponse,
    GetTransactionsControllerResponse,
    Transaction,
    TransactionCreatedEventPayload,
    type BreadcrumbItem,
} from '@/types';
import { Head, usePage } from '@inertiajs/vue3';
import { useEcho } from "@laravel/echo-vue";
import { useForm } from 'laravel-precognition-vue';
import { LoaderCircle } from 'lucide-vue-next';
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

const form = useForm('post', CreateTransactionsController.post().url, {
    receiver_id: '',
    amount: '',
});

const balance = ref('0.00');
const transactions = ref<Array<Transaction>>([]);
const loading = ref(false);
const errorMessage = ref<string | null>(null);

async function loadTransactions() {
    try {
        const res = await api.get<
            APIResponse<GetTransactionsControllerResponse>
        >(GetTransactionsController.get().url, { params: { per_page: 20 } });
        const payload = res.data?.data;

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
        await form.submit();

        form.reset();
    } catch (err: any) {
        const resp = err?.response;
        if (resp?.data?.message) {
            errorMessage.value = resp.data.message;
        }
    } finally {
        loading.value = false;
    }
}

useEcho(`user.${currentUserId}`, '.transaction.created',
    (payload: TransactionCreatedEventPayload) => {
        try {
            const transaction = payload.transaction;
            const balances = payload.balances ?? {};

            // Update balance if payload contains it
            if (
                transaction.sender_id === currentUserId &&
                balances.sender
            ) {
                balance.value = balances.sender;
            } else if (
                transaction.receiver_id === currentUserId &&
                balances.receiver
            ) {
                balance.value = balances.receiver;
            }

            // Prepend transaction if relevant to this user
            if (
                transaction.sender_id === currentUserId ||
                transaction.receiver_id === currentUserId
            ) {
                transactions.value = [
                    transaction,
                    ...transactions.value,
                ].slice(0, 50); // keep recent 50
            }
        } catch (e) {
            console.error('Error processing TransactionCreated event', e);
        }
    },
);






onMounted(async () => {
    await loadTransactions();
});
</script>

<template>

    <Head title="Transfer" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <div class="grid gap-6 md:grid-cols-3">
                <!-- Balance card -->
                <div class="rounded-lg border p-4">
                    <h3 class="mb-2 text-sm text-muted-foreground">
                        Current balance
                    </h3>
                    <div class="text-2xl font-medium">${{ balance }}</div>
                </div>

                <!-- Transfer form -->
                <div class="col-span-1 rounded-lg border p-4 md:col-span-2">
                    <h3 class="mb-4 text-sm text-muted-foreground">Transfer</h3>

                    <form @submit.prevent="submit" class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="receiver">Recipient user ID</Label>
                            <Input id="receiver" v-model="form.receiver_id" type="number"
                                placeholder="Recipient user id" autocomplete="off"
                                @change="form.validate('receiver_id')" />

                            <div v-if="form.invalid('receiver_id')" class="text-sm text-red-600">
                                {{ form.errors.receiver_id }}
                            </div>
                        </div>

                        <div class="grid gap-2">
                            <Label for="amount">Amount</Label>
                            <Input id="amount" v-model="form.amount" type="text" placeholder="0.00" inputmode="decimal"
                                @change="form.validate('amount')" />
                            <div v-if="form.invalid('amount')" class="text-sm text-red-600">
                                {{ form.errors.amount }}
                            </div>
                        </div>

                        <div class="flex flex-row items-center gap-4 sm:col-span-2">
                            <Button type="submit" :tabindex="4" :disabled="loading" data-test="login-button">
                                <LoaderCircle v-if="loading" class="h-4 w-4 animate-spin" />
                                Send
                            </Button>

                            <div v-if="errorMessage" class="text-sm text-red-600">
                                {{ errorMessage }}
                            </div>
                        </div>
                    </form>

                    <p class="mt-3 text-xs text-muted-foreground">
                        Commission: 1.5% (debited from sender). Amounts shown
                        with 2 decimal places.
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
                    <li v-for="transaction in transactions" :key="transaction.id" class="flex justify-between py-2">
                        <div>
                            <div class="text-sm">
                                <strong v-if="
                                    transaction.sender_id === currentUserId
                                ">Sent</strong>
                                <strong v-else>Received</strong>
                                <span class="ml-2 text-xs text-muted-foreground">#{{ transaction.id }}</span>
                            </div>
                            <div class="text-xs text-muted-foreground">
                                {{
                                    new Date(
                                        transaction.created_at,
                                    ).toLocaleString()
                                }}
                            </div>
                        </div>
                        <div class="text-right">
                            <div :class="transaction.sender_id === currentUserId
                                ? 'text-red-600'
                                : 'text-green-600'
                                ">
                                {{
                                    transaction.sender_id === currentUserId
                                        ? '-'
                                        : '+'
                                }}${{ transaction.amount }}
                            </div>
                            <div class="text-xs text-muted-foreground">
                                Fee: ${{ transaction.commission_fee }}
                            </div>
                        </div>
                    </li>
                </ul>
            </div>
        </div>
    </AppLayout>
</template>
