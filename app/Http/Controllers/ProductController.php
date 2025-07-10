<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

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
     * Show all products belonging to the authenticated user.
     */
    public function show(Request $request): JsonResponse
    {
        try {
            // Check authorization
            $this->authorize('viewAny', Product::class);

            // Get all products for the authenticated user using the scope
            $products = Product::forUser($request->user()->id)
                            ->with('user')
                            ->orderBy('created_at', 'desc')
                            ->get()
                            ->map(function ($product) {
                                return [
                                    'id' => $product->id,
                                    'name' => $product->name,
                                    'price' => $product->price,
                                    'unit_cost' => $product->unit_cost,
                                    'bulk_cost' => $product->bulk_cost,
                                    'user_id' => $product->user_id,
                                    'created_at' => $product->created_at,
                                    'updated_at' => $product->updated_at,
                                ];
                            });

            return response()->json([
                'message' => 'Products retrieved successfully',
                'products' => $products,
                'count' => $products->count(),
            ]);

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
