<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;

class ProductController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'price' => 'required|numeric',
            'unit_cost' => 'nullable|numeric',
            'bulk_cost' => 'nullable|numeric',
        ]);

        try {
            $product = Product::create($data);
            return response()->json([
                'message' => 'Product created successfully',
                'product' => $product,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create product',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'price' => 'sometimes|numeric',
            'unit_cost' => 'nullable|numeric',
            'bulk_cost' => 'nullable|numeric',
        ]);

        try {
            $product = Product::findOrFail($id);
            $product->update($data);
            return response()->json([
                'message' => 'Product updated successfully',
                'product' => $product,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update product',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $product = Product::findOrFail($id);
            $product->delete();
            return response()->json([
                'message' => 'Product deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete product',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function showbyid($id)
    {
        try {
            $product = Product::findOrFail($id);
            return response()->json([
                'product' => $product,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Product not found',
                'error' => $e->getMessage(),
            ], 404);
        }
    }

    public function show()
    {
        $products = Product::all();
        return response()->json([
            'products' => $products,
        ]);
    }
}
