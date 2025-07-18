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
     * Create a new sale transaction for the authenticated user.
     * Generates a unique transaction_id and assigns it to all products in the request.
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
                    'message' => 'Algunos productos no te pertenecen o no existen',
                    'error' => 'No autorizado para vender productos que no te pertenecen',
                ], 403);
            }

            // Generate unique transaction_id with user_id and timestamp
            do {
                $timestamp = (int) (microtime(true) * 1000000);
                $random = rand(1000, 9999);
                $transactionId = $timestamp + $random + $user->id;
            } while (Sale::where('transaction_id', $transactionId)->exists());

            $sales = [];
            $total_income = 0;
            $total_cost = 0;

            // Use transaction for data consistency
            DB::beginTransaction();
            try {
                foreach ($data['products'] as $item) {
                    $product = $userProducts->get($item['product_id']);

                    if (!$product) {
                        throw new AuthorizationException('Producto no encontrado o no te pertenece');
                    }

                    $quantity = $item['quantity'];
                    $unit_price = $product->price;
                    $unit_cost = $product->unit_cost ?? 0;
                    $total = $unit_price * $quantity;
                    $cost = $unit_cost * $quantity;
                    $utility = $total - $cost;

                    $sale = Sale::create([
                        'user_id' => $user->id,
                        'transaction_id' => $transactionId,
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
                'message' => 'Transacción registrada exitosamente',
                'transaction_id' => $transactionId,
                'sales' => collect($sales)->map(function ($sale) {
                    return [
                        'id' => $sale->id,
                        'transaction_id' => $sale->transaction_id,
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
                'message' => 'Error de validación',
                'errors' => $e->errors(),
            ], 422);
        } catch (AuthorizationException $e) {
            return response()->json([
                'message' => 'No autorizado para crear esta venta',
                'error' => $e->getMessage(),
            ], 403);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al registrar la transacción',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Show sales history for the authenticated user with performance optimizations.
     * Groups sales by transaction_id instead of timestamp grouping.
     */
    public function show(Request $request): JsonResponse
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
                // Get all sales with their relationships
                $query = Sale::query()
                    ->select(['id', 'user_id', 'transaction_id', 'product_id', 'quantity', 'unit_price', 'total', 'utility', 'created_at', 'updated_at'])
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

                // Group sales by transaction_id (much more efficient than timestamp grouping)
                $groupedSales = $allSales->groupBy('transaction_id');

                // Transform grouped sales into transactions
                $transactions = $groupedSales->map(function ($sales, $transactionId) {
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
                        'transaction_id' => $transactionId,
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
     * Show a complete transaction belonging to the authenticated user.
     * Uses transaction_id directly instead of finding it from a sale ID.
     */
    public function showById(Request $request, $transactionId): JsonResponse
    {
        try {
            // Verificar que la transacción pertenece al usuario autenticado
            $firstSale = Sale::forUser($request->user()->id)
                ->select(['id', 'user_id', 'transaction_id', 'product_id', 'quantity', 'unit_price', 'total', 'utility', 'created_at', 'updated_at'])
                ->where('transaction_id', $transactionId)
                ->first();

            if (!$firstSale) {
                return response()->json([
                    'message' => 'Transacción no encontrada o no tienes permiso para acceder a ella',
                ], 404);
            }

            // Verificar autorización
            $this->authorize('view', $firstSale);

            // Obtener todas las ventas en la misma transacción
            $transactionSales = Sale::forUser($request->user()->id)
                ->with(['product:id,name,price,unit_cost', 'user:id,name'])
                ->select(['id', 'user_id', 'transaction_id', 'product_id', 'quantity', 'unit_price', 'total', 'utility', 'created_at', 'updated_at'])
                ->where('transaction_id', $transactionId)
                ->get();

            // Calcular totales de la transacción
            $totalAmount = $transactionSales->sum('total');
            $totalUtility = $transactionSales->sum('utility');
            $totalItems = $transactionSales->sum('quantity');

            // Preparar lista de productos para esta transacción
            $products = $transactionSales->map(function ($sale) {
                return [
                    'product_name' => $sale->product?->name ?? 'Producto no encontrado',
                    'quantity' => $sale->quantity,
                    'unit_price' => $sale->unit_price,
                    'total' => $sale->total,
                    'utility' => $sale->utility,
                ];
            })->values();

            return response()->json([
                'message' => 'Transacción recuperada exitosamente',
                'transaction' => [
                    'transaction_id' => $transactionId,
                    'transaction_date' => $firstSale->created_at,
                    'total_amount' => $totalAmount,
                    'total_utility' => $totalUtility,
                    'total_items' => $totalItems,
                    'products_count' => $transactionSales->count(),
                    'products' => $products,
                    'user_name' => $firstSale->user?->name ?? 'Usuario no encontrado',
                ],
            ]);
        } catch (AuthorizationException $e) {
            return response()->json([
                'message' => 'No autorizado para ver esta transacción',
                'error' => $e->getMessage(),
            ], 403);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al recuperar la transacción',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete an entire transaction belonging to the authenticated user.
     * Uses transaction_id directly instead of finding it from a sale ID.
     */
    public function destroy(Request $request, $transactionId): JsonResponse
    {
        try {
            // Verificar que la transacción pertenece al usuario autenticado
            $firstSale = Sale::forUser($request->user()->id)
                ->where('transaction_id', $transactionId)
                ->first();

            if (!$firstSale) {
                return response()->json([
                    'message' => 'Transacción no encontrada o no tienes permiso para acceder a ella',
                ], 404);
            }

            // Verificar autorización
            $this->authorize('delete', $firstSale);

            // Obtener todas las ventas en la misma transacción para el reporte
            $transactionSales = Sale::forUser($request->user()->id)
                ->with(['product:id,name'])
                ->where('transaction_id', $transactionId)
                ->get();

            $totalAmount = $transactionSales->sum('total');
            $totalItems = $transactionSales->sum('quantity');
            $productsCount = $transactionSales->count();

            // Eliminar todas las ventas en la transacción
            DB::beginTransaction();
            try {
                Sale::forUser($request->user()->id)
                    ->where('transaction_id', $transactionId)
                    ->delete();
                
                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

            return response()->json([
                'message' => 'Transacción eliminada exitosamente',
                'deleted_transaction' => [
                    'transaction_id' => $transactionId,
                    'total_amount' => $totalAmount,
                    'total_items' => $totalItems,
                    'products_count' => $productsCount,
                    'deleted_at' => now(),
                ],
            ]);
        } catch (AuthorizationException $e) {
            return response()->json([
                'message' => 'No autorizado para eliminar esta transacción',
                'error' => $e->getMessage(),
            ], 403);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al eliminar la transacción',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update a specific sale within a transaction.
     * Uses transaction_id and requires product_id to identify which sale to update.
     */
    public function update(Request $request, $transactionId): JsonResponse
    {
        try {
            // Validar datos de entrada
            $data = $request->validate([
                'product_id' => 'required|integer|exists:products,id',
                'quantity' => 'required|integer|min:1',
                // otros campos que permitas actualizar
            ]);

            // Verificar que la transacción pertenece al usuario autenticado
            $sale = Sale::forUser($request->user()->id)
                ->where('transaction_id', $transactionId)
                ->where('product_id', $data['product_id'])
                ->first();

            if (!$sale) {
                return response()->json([
                    'message' => 'Transacción o producto no encontrado, o no tienes permiso para acceder a ellos',
                ], 404);
            }
            
            // Verificar autorización
            $this->authorize('update', $sale);

            // Actualizar la venta
            $sale->update($data);
            
            // Recalcular totales si es necesario (cantidad cambiada)
            if (isset($data['quantity'])) {
                $unit_price = $sale->unit_price;
                $unit_cost = $sale->product->unit_cost ?? 0;
                $new_total = $unit_price * $data['quantity'];
                $new_utility = $new_total - ($unit_cost * $data['quantity']);
                
                $sale->update([
                    'total' => $new_total,
                    'utility' => $new_utility,
                ]);
            }

            // Obtener información completa de la transacción después de la actualización
            $transactionSales = Sale::forUser($request->user()->id)
                ->with(['product:id,name,price,unit_cost', 'user:id,name'])
                ->where('transaction_id', $transactionId)
                ->get();

            $totalAmount = $transactionSales->sum('total');
            $totalUtility = $transactionSales->sum('utility');
            $totalItems = $transactionSales->sum('quantity');

            $products = $transactionSales->map(function ($sale) {
                return [
                    'product_name' => $sale->product?->name ?? 'Producto no encontrado',
                    'quantity' => $sale->quantity,
                    'unit_price' => $sale->unit_price,
                    'total' => $sale->total,
                    'utility' => $sale->utility,
                ];
            })->values();

            return response()->json([
                'message' => 'Venta actualizada exitosamente',
                'updated_transaction' => [
                    'transaction_id' => $transactionId,
                    'transaction_date' => $sale->created_at,
                    'total_amount' => $totalAmount,
                    'total_utility' => $totalUtility,
                    'total_items' => $totalItems,
                    'products_count' => $transactionSales->count(),
                    'products' => $products,
                    'user_name' => $sale->user?->name ?? 'Usuario no encontrado',
                ],
            ]);
            
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors(),
            ], 422);
        } catch (AuthorizationException $e) {
            return response()->json([
                'message' => 'No autorizado para actualizar esta venta',
                'error' => $e->getMessage(),
            ], 403);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al actualizar la venta',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
