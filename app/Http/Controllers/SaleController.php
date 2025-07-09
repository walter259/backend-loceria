<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Sale;
use App\Models\Product;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SaleController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'products' => 'required|array',
            'products.*.product_id' => 'required|exists:products,id',
            'products.*.quantity' => 'required|integer|min:1',
        ]);

        $user = $request->user();
        
        // Obtener todos los IDs de productos únicos para una sola consulta
        $productIds = collect($data['products'])->pluck('product_id')->unique();
        $products = Product::whereIn('id', $productIds)->get()->keyBy('id');
        
        // Verificar que todos los productos existen
        if ($products->count() !== $productIds->count()) {
            return response()->json([
                'message' => 'Algunos productos no existen',
            ], 400);
        }

        $sales = [];
        $total_income = 0;
        $total_cost = 0;

        // Usar transacción para consistencia de datos
        DB::beginTransaction();
        try {
            foreach ($data['products'] as $item) {
                $product = $products->get($item['product_id']);
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
            return response()->json([
                'message' => 'Error al registrar la venta',
                'error' => $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'message' => 'Venta registrada exitosamente',
            'sales' => $sales,
            'total_income' => $total_income,
            'total_cost' => $total_cost,
            'total_utility' => $total_income - $total_cost,
        ], 201);
    }

    public function history(Request $request)
    {
        $query = Sale::with(['product:id,name', 'user:id,name']);
        
        // Aplicar filtros de fecha si se proporcionan
        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('created_at', [$request->start_date, $request->end_date]);
        } elseif ($request->filled('start_date')) {
            $query->where('created_at', '>=', $request->start_date);
        } elseif ($request->filled('end_date')) {
            $query->where('created_at', '<=', $request->end_date);
        }
        
        // Aplicar paginación para mejorar el rendimiento
        $perPage = $request->get('per_page', 15);
        $sales = $query->orderBy('created_at', 'desc')
                      ->paginate($perPage);
        
        // Transformar los datos de manera más eficiente
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
            'sales' => $sales->items(),
            'pagination' => [
                'current_page' => $sales->currentPage(),
                'last_page' => $sales->lastPage(),
                'per_page' => $sales->perPage(),
                'total' => $sales->total(),
            ],
        ]);
    }

    public function show($id)
    {
        try {
            $sale = Sale::with(['product:id,name,price,unit_cost', 'user:id,name'])
                       ->select(['id', 'user_id', 'product_id', 'quantity', 'unit_price', 'total', 'utility', 'created_at', 'updated_at'])
                       ->findOrFail($id);
            
            return response()->json([
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
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Venta no encontrada',
                'error' => $e->getMessage(),
            ], 404);
        }
    }

    public function destroy($id)
    {
        try {
            $sale = Sale::findOrFail($id);
            $sale->delete();
            return response()->json([
                'message' => 'Venta eliminada exitosamente',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al eliminar la venta',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
