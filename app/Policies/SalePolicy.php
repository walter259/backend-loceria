<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Sale;
use App\Models\Product;
use Illuminate\Auth\Access\HandlesAuthorization;

class SalePolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any sales.
     */
    public function viewAny(User $user): bool
    {
        return true; // Users can view their own sales
    }

    /**
     * Determine whether the user can view the sale.
     */
    public function view(User $user, Sale $sale): bool
    {
        return $user->id === $sale->user_id;
    }

    /**
     * Determine whether the user can create sales.
     */
    public function create(User $user): bool
    {
        return true; // Authenticated users can create sales
    }

    /**
     * Determine whether the user can create a sale with specific products.
     */
    public function createWithProducts(User $user, array $productIds): bool
    {
        // Check if all products belong to the authenticated user
        $userProductCount = Product::where('user_id', $user->id)
                                  ->whereIn('id', $productIds)
                                  ->count();
        
        return $userProductCount === count($productIds);
    }

    /**
     * Determine whether the user can delete the sale.
     */
    public function delete(User $user, Sale $sale): bool
    {
        return $user->id === $sale->user_id;
    }

    /**
     * Determina si el usuario puede actualizar la venta.
     */
    public function update(User $user, Sale $sale): bool
    {
        return $user->id === $sale->user_id;
    }
} 