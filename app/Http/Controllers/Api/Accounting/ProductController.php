<?php

namespace App\Http\Controllers\Api\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProductController extends Controller
{
    /** GET /api/accounting/products */
    public function index(Request $request)
    {
        $query = Product::where('active', true);

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                  ->orWhere('hsn_code', 'like', "%{$s}%")
                  ->orWhere('category', 'like', "%{$s}%");
            });
        }
        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        $perPage = $request->get('per_page', 20);
        $products = $query->orderBy('name')->paginate($perPage);

        return response()->json([
            'data' => $products->items(),
            'meta' => [
                'current_page' => $products->currentPage(),
                'last_page'    => $products->lastPage(),
                'per_page'     => $products->perPage(),
                'total'        => $products->total(),
            ],
        ]);
    }

    /** GET /api/accounting/products/categories */
    public function categories()
    {
        $categories = Product::whereNotNull('category')
            ->where('category', '!=', '')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');

        return response()->json(['data' => $categories]);
    }

    /** GET /api/accounting/products/{id} */
    public function show($id)
    {
        $product = Product::findOrFail($id);
        return response()->json(['data' => $product]);
    }

    /** POST /api/accounting/products */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'hsn_code'    => 'nullable|string|max:20',
            'category'    => 'nullable|string|max:150',
            'unit'        => 'nullable|string|max:30',
            'unit_price'  => 'required|numeric|min:0',
            'tax_rate'    => 'nullable|numeric|min:0|max:100',
        ]);

        $validated['user_id'] = Auth::id();
        $validated['active'] = true;

        $product = Product::create($validated);

        return response()->json([
            'message' => 'Product created successfully',
            'data'    => $product,
        ], 201);
    }

    /** PUT /api/accounting/products/{id} */
    public function update(Request $request, $id)
    {
        $product = Product::findOrFail($id);

        $validated = $request->validate([
            'name'        => 'sometimes|required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'hsn_code'    => 'nullable|string|max:20',
            'category'    => 'nullable|string|max:150',
            'unit'        => 'nullable|string|max:30',
            'unit_price'  => 'sometimes|required|numeric|min:0',
            'tax_rate'    => 'nullable|numeric|min:0|max:100',
            'active'      => 'sometimes|boolean',
        ]);

        $product->update($validated);

        return response()->json([
            'message' => 'Product updated successfully',
            'data'    => $product,
        ]);
    }

    /** DELETE /api/accounting/products/{id} */
    public function destroy($id)
    {
        $product = Product::findOrFail($id);
        $product->delete();

        return response()->json(['message' => 'Product deleted successfully']);
    }
}
