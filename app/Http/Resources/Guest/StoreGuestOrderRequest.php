<?php

namespace App\Http\Requests\Guest;

use App\Models\MenuItem;
use App\Models\ModifierOption;
use Illuminate\Foundation\Http\FormRequest;

class StoreGuestOrderRequest extends FormRequest
{
    /**
     * Store calculated amounts internally during validation.
     */
    protected float $calculatedSubtotal = 0.00;
    protected float $calculatedTotal = 0.00;

    public function authorize(): bool
    {
        return true; // Middleware handles table and restaurant scoping
    }

    public function rules(): array
    {
        return [
            'type'                                   => 'required|string|in:dine_in,takeaway,delivery',
            'notes'                                  => 'nullable|string|max:2000',
            'subtotal'                               => 'required|numeric|min:0',
            'total_amount'                           => 'required|numeric|min:0',
            'items'                                  => 'required|array|min:1',
            'items.*.menu_item_id'                   => 'required|integer|exists:menu_items,id',
            'items.*.quantity'                       => 'required|integer|min:1|max:100',
            'items.*.special_instructions'             => 'nullable|string|max:500',
            'items.*.modifiers'                      => 'nullable|array',
            'items.*.modifiers.*.modifier_option_id' => 'required|integer|exists:modifier_options,id',
        ];
    }

    /**
     * Validate calculated server totals against incoming request payload.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $restaurant = $this->attributes->get('restaurant');

            if (! $restaurant) {
                return;
            }

            $items = $this->input('items', []);
            $calculatedSubtotal = 0.00;

            foreach ($items as $index => $itemData) {
                // Verify menu item belongs to active restaurant
                $menuItem = MenuItem::where('restaurant_id', $restaurant->id)
                    ->find($itemData['menu_item_id']);

                if (! $menuItem) {
                    $validator->errors()->add("items.{$index}.menu_item_id", "Menu item is invalid or unavailable.");
                    continue;
                }

                $basePrice = (float) $menuItem->price;
                $modifierTotal = 0.00;

                // Calculate modifier prices
                if (! empty($itemData['modifiers'])) {
                    foreach ($itemData['modifiers'] as $modIndex => $modData) {
                        $option = ModifierOption::whereHas('modifierGroup', function ($q) use ($restaurant) {
                            $q->where('restaurant_id', $restaurant->id);
                        })->find($modData['modifier_option_id']);

                        if (! $option) {
                            $validator->errors()->add(
                                "items.{$index}.modifiers.{$modIndex}.modifier_option_id",
                                "Modifier option is invalid for this restaurant."
                            );
                            continue;
                        }

                        $modifierTotal += (float) $option->price;
                    }
                }

                $lineSubtotal = ($basePrice + $modifierTotal) * (int) $itemData['quantity'];
                $calculatedSubtotal += $lineSubtotal;
            }

            // Standardize values to 2 decimal places for comparison
            $this->calculatedSubtotal = round($calculatedSubtotal, 2);
            $this->calculatedTotal = round($calculatedSubtotal, 2); // Extend here if adding taxes/fees

            $submittedSubtotal = round((float) $this->input('subtotal'), 2);
            $submittedTotal = round((float) $this->input('total_amount'), 2);

            if (abs($submittedSubtotal - $this->calculatedSubtotal) > 0.01) {
                $validator->errors()->add('subtotal', "Subtotal mismatch. Calculated: {$this->calculatedSubtotal}, Provided: {$submittedSubtotal}");
            }

            if (abs($submittedTotal - $this->calculatedTotal) > 0.01) {
                $validator->errors()->add('total_amount', "Total amount mismatch. Calculated: {$this->calculatedTotal}, Provided: {$submittedTotal}");
            }
        });
    }

    /**
     * Retrieve the server-verified calculation results.
     */
    public function getVerifiedTotals(): array
    {
        return [
            'subtotal'     => $this->calculatedSubtotal,
            'total_amount' => $this->calculatedTotal,
        ];
    }
}



// To prevent price tampering, client-submitted prices should never be trusted. The server must calculate the exact line items, modifiers, and overall totals independently.

// By moving your validation logic into a dedicated FormRequest, you can validate incoming item IDs and modifier choices first, then execute a custom validation pass that checks client-submitted totals against server calculations.