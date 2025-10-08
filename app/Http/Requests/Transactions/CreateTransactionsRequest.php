<?php

namespace App\Http\Requests\Transactions;

use App\Models\Transaction;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Class CreateTransactionsRequest
 *
 * @property int $receiver_id
 * @property float $amount
 */
class CreateTransactionsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Prepare the data for validation.
     */
    public function prepareForValidation(): void
    {
        if ($this->has('receiver_id')) {
            $this->merge(['receiver_id' => (int) $this->input('receiver_id')]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'receiver_id' => ['required', 'integer', 'exists:users,id'],
            // regex to allow up to 2 decimal places
            'amount' => ['required', 'numeric', 'min:0.01', 'regex:/^\d+(\.\d{1,'.Transaction::DECIMAL_PLACES.'})?$/',
            ],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $user = $this->user();

            // validate that sender is not receiver
            if ($this->filled('receiver_id') && $user->id === (int) $this->input('receiver_id')) {
                $validator->errors()->add('receiver_id', 'Receiver must be different from sender.');

                return;
            }

            // validate sufficient balance (pre-check).
            if ($this->filled('amount')) {
                $amount = formatAmount($this->input('amount'));
                $commission = calculateCommissionFee($amount);
                $totalDebit = bcadd($amount, $commission, Transaction::DECIMAL_PLACES);

                if (bccomp((string) $user->balance, $totalDebit, Transaction::DECIMAL_PLACES) < 0) {
                    $validator->errors()->add('amount', 'Insufficient balance to cover amount plus commission (1.5%).');
                }

                $this->merge(['amount' => $amount]);
            }
        });
    }
}
