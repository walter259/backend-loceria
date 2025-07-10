<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Sale;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\DB;

class SaleController extends Controller
{
    use AuthorizesRequests;

    /**
     * Create a new sale for the authenticated user.
     * Only allows selling products that belong to the authenticated user.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            // Validate the request data
            $data = $request->validate([
                'products' => 'required|array|min:1',
                'products.*.product_id' => 'required|integer|exists:products,id',
                'products.*.quantity' => 'required|integer|min:1',
            ]);

            $user = $request->user();

            // Get all unique product IDs for efficient querying
            $productIds = collect($data['products'])->pluck('product_id')->unique()->values();

            // Check authorization for creating sales
            $this->authorize('create', Sale::class);

            // Verify that all products belong to the authenticated user
            $userProducts = Product::where('user_id', $user->id)
                ->whereIn('id', $productIds)
                ->get()
                ->keyBy('id');

            // Check if all requested products belong to the user
            if ($userProducts->count() !== $productIds->count()) {
                return response()->json([
                    'message' => 'Some products do not belong to you or do not exist',
                    'error' => 'Unauthorized to sell products that do not belong to you',
                ], 403);
            }

            $sales = [];
            $total_income = 0;
            $total_cost = 0;

            // Use transaction for data consistency
            DB::beginTransaction();
            try {
                foreach ($data['products'] as $item) {
                    $product = $userProducts->get($item['product_id']);

                    if (!$product) {
                        throw new AuthorizationException('Product not found or does not belong to you');
                    }

                    $quantity = $item['quantity'];
                    $unit_price = $product->price;
                    $unit_cost = $product->unit_cost ?? 0;
                    $total = $unit_price * $quantity;
                    $cost = $unit_cost * $quantity;
                    $utility = $total - $cost;

                    $sale = Sale::create([
                        'user_id' => $user->id,
                        'product_id' => $product->id,
                        'quantity' => $quantity,
                        'unit_price' => $unit_price,
                        'total' => $total,
                        'utility' => $utility,
                    ]);

                    $sales[] = $sale;
                    $total_income += $total;
                    $total_cost += $cost;
                }

                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

            return response()->json([
                'message' => 'Sale registered successfully',
                'sales' => collect($sales)->map(function ($sale) {
                    return [
                        'id' => $sale->id,
                        'product_id' => $sale->product_id,
                        'quantity' => $sale->quantity,
                        'unit_price' => $sale->unit_price,
                        'total' => $sale->total,
                        'utility' => $sale->utility,
                        'created_at' => $sale->created_at,
                    ];
                }),
                'summary' => [
                    'total_income' => $total_income,
                    'total_cost' => $total_cost,
                    'total_utility' => $total_income - $total_cost,
                    'items_count' => count($sales),
                ],
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (AuthorizationException $e) {
            return response()->json([
                'message' => 'Unauthorized to create this sale',
                'error' => $e->getMessage(),
            ], 403);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to register sale',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Show sales history for the authenticated user.
     */
    public function history(Request $request): JsonResponse
    {
        try {
            // Check authorization
            $this->authorize('viewAny', Sale::class);

            $user = $request->user();

            // Build query for user's sales only
            $query = Sale::forUser($user->id)
                ->with(['product:id,name,price,unit_cost', 'user:id,name']);

            // Apply date filters if provided
            if ($request->filled('start_date') && $request->filled('end_date')) {
                $query->withinDateRange($request->start_date, $request->end_date);
            } elseif ($request->filled('start_date')) {
                $query->where('created_at', '>=', $request->start_date);
            } elseif ($request->filled('end_date')) {
                $query->where('created_at', '<=', $request->end_date);
            }

            // Apply pagination for better performance
            $perPage = $request->get('per_page', 15);
            $sales = $query->orderBy('created_at', 'desc')
                ->paginate($perPage);

            // Transform the data efficiently
            $sales->getCollection()->transform(function ($sale) {
                return [
                    'id' => $sale->id,
                    'user' => $sale->user?->name,
                    'product' => $sale->product?->name,
                    'quantity' => $sale->quantity,
                    'unit_price' => $sale->unit_price,
                    'total' => $sale->total,
                    'utility' => $sale->utility,
                    'created_at' => $sale->created_at,
                ];
            });

            return response()->json([
                'message' => 'Sales history retrieved successfully',
                'sales' => $sales->items(),
                'pagination' => [
                    'current_page' => $sales->currentPage(),
                    'last_page' => $sales->lastPage(),
                    'per_page' => $sales->perPage(),
                    'total' => $sales->total(),
                ],
            ]);
        } catch (AuthorizationException $e) {
            return response()->json([
                'message' => 'Unauthorized to view sales history',
                'error' => $e->getMessage(),
            ], 403);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve sales history',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Show a specific sale belonging to the authenticated user.
     */
    public function show(Request $request, $id): JsonResponse
    {
        try {
            // Find the sale and check if it belongs to the authenticated user
            $sale = Sale::forUser($request->user()->id)
                ->with(['product:id,name,price,unit_cost', 'user:id,name'])
                ->select(['id', 'user_id', 'product_id', 'quantity', 'unit_price', 'total', 'utility', 'created_at', 'updated_at'])
                ->findOrFail($id);

            // Check authorization
            $this->authorize('view', $sale);

            return response()->json([
                'message' => 'Sale retrieved successfully',
                'sale' => [
                    'id' => $sale->id,
                    'user' => $sale->user?->name,
                    'product' => $sale->product?->name,
                    'quantity' => $sale->quantity,
                    'unit_price' => $sale->unit_price,
                    'total' => $sale->total,
                    'utility' => $sale->utility,
                    'created_at' => $sale->created_at,
                    'updated_at' => $sale->updated_at,
                ],
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Sale not found or you do not have permission to access it',
            ], 404);
        } catch (AuthorizationException $e) {
            return response()->json([
                'message' => 'Unauthorized to view this sale',
                'error' => $e->getMessage(),
            ], 403);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve sale',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete a sale belonging to the authenticated user.
     */
    public function destroy(Request $request, $id): JsonResponse
    {
        try {
            // Find the sale and check if it belongs to the authenticated user
            $sale = Sale::forUser($request->user()->id)->findOrFail($id);

            // Check authorization
            $this->authorize('delete', $sale);

            // Delete the sale
            $sale->delete();

            return response()->json([
                'message' => 'Sale deleted successfully',
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Sale not found or you do not have permission to access it',
            ], 404);
        } catch (AuthorizationException $e) {
            return response()->json([
                'message' => 'Unauthorized to delete this sale',
                'error' => $e->getMessage(),
            ], 403);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete sale',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
