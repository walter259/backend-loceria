<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Log;

class ProductController extends Controller
{
    use AuthorizesRequests;

    /**
     * Create a new product for the authenticated user.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            // Validate the request data
            $data = $request->validate([
                'name' => 'required|string|max:255',
                'brand' => 'required|string|max:255',
                'price' => 'required|numeric|min:0',
                'unit_cost' => 'nullable|numeric|min:0',
                'bulk_cost' => 'nullable|numeric|min:0',
            ]);

            // Get the authenticated user
            $user = $request->user();

            // Check authorization
            $this->authorize('create', Product::class);

            // Create the product with user_id automatically assigned
            $product = Product::create([
                ...$data,
                'user_id' => $user->id,
            ]);

            // Load the user relationship for response
            $product->load('user');

            return response()->json([
                'message' => 'Product created successfully',
                'product' => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'brand' => $product->brand,
                    'price' => $product->price,
                    'unit_cost' => $product->unit_cost,
                    'bulk_cost' => $product->bulk_cost,
                    'user_id' => $product->user_id,
                    'created_at' => $product->created_at,
                    'updated_at' => $product->updated_at,
                ],
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (AuthorizationException $e) {
            return response()->json([
                'message' => 'Unauthorized to create products',
                'error' => $e->getMessage(),
            ], 403);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create product',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update a product belonging to the authenticated user.
     */
    public function update(Request $request, $id): JsonResponse
    {
        try {
            // Validate the request data
            $data = $request->validate([
                'name' => 'sometimes|string|max:255',
                'brand' => 'sometimes|string|max:255',
                'price' => 'sometimes|numeric|min:0',
                'unit_cost' => 'nullable|numeric|min:0',
                'bulk_cost' => 'nullable|numeric|min:0',
            ]);

            // Find the product and check if it belongs to the authenticated user
            $product = Product::where('user_id', $request->user()->id)
                ->findOrFail($id);

            // Check authorization
            $this->authorize('update', $product);

            // Update the product
            $product->update($data);

            // Load the user relationship for response
            $product->load('user');

            return response()->json([
                'message' => 'Product updated successfully',
                'product' => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'brand' => $product->brand,
                    'price' => $product->price,
                    'unit_cost' => $product->unit_cost,
                    'bulk_cost' => $product->bulk_cost,
                    'user_id' => $product->user_id,
                    'created_at' => $product->created_at,
                    'updated_at' => $product->updated_at,
                ],
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Product not found or you do not have permission to access it',
            ], 404);
        } catch (AuthorizationException $e) {
            return response()->json([
                'message' => 'Unauthorized to update this product',
                'error' => $e->getMessage(),
            ], 403);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update product',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete a product belonging to the authenticated user.
     */
    public function destroy(Request $request, $id): JsonResponse
    {
        try {
            // Find the product and check if it belongs to the authenticated user
            $product = Product::where('user_id', $request->user()->id)
                ->findOrFail($id);

            // Check authorization
            $this->authorize('delete', $product);

            // Delete the product
            $product->delete();

            return response()->json([
                'message' => 'Product deleted successfully',
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Product not found or you do not have permission to access it',
            ], 404);
        } catch (AuthorizationException $e) {
            return response()->json([
                'message' => 'Unauthorized to delete this product',
                'error' => $e->getMessage(),
            ], 403);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete product',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Show a specific product belonging to the authenticated user.
     */
    public function showbyid(Request $request, $id): JsonResponse
    {
        try {
            // Find the product and check if it belongs to the authenticated user
            $product = Product::where('user_id', $request->user()->id)
                ->findOrFail($id);

            // Check authorization
            $this->authorize('view', $product);

            // Load the user relationship for response
            $product->load('user');

            return response()->json([
                'product' => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'brand' => $product->brand,
                    'price' => $product->price,
                    'unit_cost' => $product->unit_cost,
                    'bulk_cost' => $product->bulk_cost,
                    'user_id' => $product->user_id,
                    'created_at' => $product->created_at,
                    'updated_at' => $product->updated_at,
                ],
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Product not found or you do not have permission to access it',
            ], 404);
        } catch (AuthorizationException $e) {
            return response()->json([
                'message' => 'Unauthorized to view this product',
                'error' => $e->getMessage(),
            ], 403);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve product',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Show all products belonging to the authenticated user with performance optimizations.
     */
    public function show(Request $request): JsonResponse
    {
        $start = microtime(true); // Performance monitoring
        try {
            $this->authorize('viewAny', Product::class);

            $userId = $request->user()->id;
            $search = $request->input('search', '');
            $brand = $request->input('brand', null);
            $perPage = (int) $request->input('per_page', 15);
            $cursor = $request->input('cursor', null);
            $useCursor = $request->boolean('cursor', true); // allow fallback to offset

            // Build cache key
            $cacheKey = "products:{$userId}:{$search}:{$brand}:{$cursor}:{$perPage}:{$useCursor}";
            $ttl = 60; // seconds

            $products = cache()->remember($cacheKey, $ttl, function () use ($userId, $search, $brand, $perPage, $cursor, $useCursor) {
                $query = Product::query()
                    ->select(['id', 'name', 'brand', 'price', 'unit_cost', 'bulk_cost', 'user_id', 'created_at', 'updated_at'])
                    ->with('user:id,name')
                    ->where('user_id', $userId)
                    ->orderByDesc('created_at');

                // Full-text search
                if ($search) {
                    $query->whereFullText(['name', 'brand'], $search);
                }
                // Filter by brand
                if ($brand) {
                    $query->where('brand', $brand);
                }

                // Cursor-based pagination (default), fallback to offset if requested
                if ($useCursor) {
                    return $query->cursorPaginate($perPage, ['*'], 'cursor', $cursor);
                } else {
                    return $query->paginate($perPage);
                }
            });

            $duration = microtime(true) - $start;
            Log::info('ProductController@show duration', ['duration_ms' => $duration * 1000]);

            // Prepare response (compatible with both cursor and offset pagination)
            $response = [
                'message' => 'Products retrieved successfully',
                'products' => $products->items(),
                'count' => $products->count(),
                'cached' => true,
                'performance_ms' => round($duration * 1000, 2),
            ];
            if (method_exists($products, 'nextCursor')) {
                $response['next_cursor'] = $products->nextCursor()?->encode();
                $response['prev_cursor'] = $products->previousCursor()?->encode();
                $response['has_more'] = $products->hasMorePages();
            } else {
                $response['pagination'] = [
                    'current_page' => $products->currentPage(),
                    'last_page' => $products->lastPage(),
                    'per_page' => $products->perPage(),
                    'total' => $products->total(),
                ];
            }
            return response()->json($response);
        } catch (AuthorizationException $e) {
            return response()->json([
                'message' => 'Unauthorized to view products',
                'error' => $e->getMessage(),
            ], 403);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve products',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
