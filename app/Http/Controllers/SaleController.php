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
use Illuminate\Support\Facades\Log;

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
     * Show sales history for the authenticated user with performance optimizations.
     * Groups sales by transaction instead of showing individual product sales.
     */
    public function history(Request $request): JsonResponse
    {
        $start = microtime(true); // Performance monitoring
        try {
            $this->authorize('viewAny', Sale::class);

            $userId = $request->user()->id;
            $startDate = $request->input('start_date');
            $endDate = $request->input('end_date');
            $productId = $request->input('product_id');
            $perPage = (int) $request->input('per_page', 15);
            $cursor = $request->input('cursor', null);
            $useCursor = $request->boolean('cursor', true); // allow fallback to offset

            // Build cache key
            $cacheKey = "sales_transactions:{$userId}:{$startDate}:{$endDate}:{$productId}:{$cursor}:{$perPage}:{$useCursor}";
            $ttl = 60; // seconds

            $transactions = cache()->remember($cacheKey, $ttl, function () use ($userId, $startDate, $endDate, $productId, $perPage, $cursor, $useCursor, $request) {
                // First, get all sales with their relationships
                $query = Sale::query()
                    ->select(['id', 'user_id', 'product_id', 'quantity', 'unit_price', 'total', 'utility', 'created_at', 'updated_at'])
                    ->with(['product:id,name,price,unit_cost', 'user:id,name'])
                    ->where('user_id', $userId)
                    ->orderByDesc('created_at');

                // Date range filter
                if ($startDate && $endDate) {
                    $query->whereBetween('created_at', [$startDate, $endDate]);
                } elseif ($startDate) {
                    $query->where('created_at', '>=', $startDate);
                } elseif ($endDate) {
                    $query->where('created_at', '<=', $endDate);
                }
                // Product filter
                if ($productId) {
                    $query->where('product_id', $productId);
                }

                // Get all sales for grouping
                $allSales = $query->get();

                // Group sales by transaction (same user_id and created_at timestamp)
                $groupedSales = $allSales->groupBy(function ($sale) {
                    // Group by user_id and created_at (rounded to seconds for transaction grouping)
                    return $sale->user_id . '_' . $sale->created_at->format('Y-m-d H:i:s');
                });

                // Transform grouped sales into transactions
                $transactions = $groupedSales->map(function ($sales, $groupKey) {
                    $firstSale = $sales->first();
                    
                    // Calculate transaction totals
                    $totalAmount = $sales->sum('total');
                    $totalUtility = $sales->sum('utility');
                    $totalItems = $sales->sum('quantity');
                    
                    // Prepare products list for this transaction
                    $products = $sales->map(function ($sale) {
                        return [
                            'product_name' => $sale->product?->name ?? 'Producto no encontrado',
                            'quantity' => $sale->quantity,
                            'unit_price' => $sale->unit_price,
                            'total' => $sale->total,
                            'utility' => $sale->utility,
                        ];
                    })->values();

                    return [
                        'transaction_id' => $firstSale->id, // Use first sale ID as transaction ID
                        'transaction_date' => $firstSale->created_at,
                        'total_amount' => $totalAmount,
                        'total_utility' => $totalUtility,
                        'total_items' => $totalItems,
                        'products_count' => $sales->count(), // Number of different products
                        'products' => $products,
                        'user_name' => $firstSale->user?->name ?? 'Usuario no encontrado',
                    ];
                })->values();

                // Sort transactions by date (newest first)
                $transactions = $transactions->sortByDesc('transaction_date')->values();

                // Apply pagination to the grouped transactions
                $totalTransactions = $transactions->count();
                $offset = 0;
                
                if ($cursor && $useCursor) {
                    // Simple cursor implementation for grouped data
                    $cursorData = json_decode(base64_decode($cursor), true);
                    $offset = $cursorData['offset'] ?? 0;
                } elseif (!$useCursor) {
                    $page = $request->input('page', 1);
                    $offset = ($page - 1) * $perPage;
                }

                $paginatedTransactions = $transactions->slice($offset, $perPage);
                
                // Create a custom paginator-like object
                $hasMore = ($offset + $perPage) < $totalTransactions;
                $nextCursor = $hasMore ? base64_encode(json_encode(['offset' => $offset + $perPage])) : null;
                $prevCursor = $offset > 0 ? base64_encode(json_encode(['offset' => max(0, $offset - $perPage)])) : null;

                return (object) [
                    'items' => $paginatedTransactions,
                    'count' => $paginatedTransactions->count(),
                    'total' => $totalTransactions,
                    'has_more' => $hasMore,
                    'next_cursor' => $nextCursor,
                    'prev_cursor' => $prevCursor,
                    'current_page' => $useCursor ? null : ($offset / $perPage) + 1,
                    'last_page' => $useCursor ? null : ceil($totalTransactions / $perPage),
                    'per_page' => $perPage,
                ];
            });

            $duration = microtime(true) - $start;
            Log::info('SaleController@history duration', ['duration_ms' => $duration * 1000]);

            // Prepare response (compatible with both cursor and offset pagination)
            $response = [
                'message' => 'Historial de transacciones recuperado exitosamente',
                'transactions' => $transactions->items,
                'count' => $transactions->count,
                'cached' => true,
                'performance_ms' => round($duration * 1000, 2),
            ];

            if ($useCursor) {
                $response['next_cursor'] = $transactions->next_cursor;
                $response['prev_cursor'] = $transactions->prev_cursor;
                $response['has_more'] = $transactions->has_more;
            } else {
                $response['pagination'] = [
                    'current_page' => $transactions->current_page,
                    'last_page' => $transactions->last_page,
                    'per_page' => $transactions->per_page,
                    'total' => $transactions->total,
                ];
            }

            return response()->json($response);
        } catch (AuthorizationException $e) {
            return response()->json([
                'message' => 'No autorizado para ver el historial de ventas',
                'error' => $e->getMessage(),
            ], 403);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al recuperar el historial de ventas',
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

    public function update(Request $request, $id): JsonResponse
{
    try {
        // Validar datos de entrada
        $data = $request->validate([
            'quantity' => 'required|integer|min:1',
            // otros campos que permitas actualizar
        ]);

        // Encontrar la venta del usuario autenticado
        $sale = Sale::forUser($request->user()->id)->findOrFail($id);
        
        // Verificar autorización
        $this->authorize('update', $sale);

        // Actualizar la venta
        $sale->update($data);
        
        return response()->json([
            'message' => 'Sale updated successfully',
            'sale' => $sale
        ]);
        
    } catch (ModelNotFoundException $e) {
        return response()->json([
            'message' => 'Sale not found or you do not have permission to access it',
        ], 404);
    } catch (AuthorizationException $e) {
        return response()->json([
            'message' => 'Unauthorized to update this sale',
            'error' => $e->getMessage(),
        ], 403);
    } catch (\Exception $e) {
        return response()->json([
            'message' => 'Failed to update sale',
            'error' => $e->getMessage(),
        ], 500);
    }
}
}
