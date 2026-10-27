<?php

namespace App\Http\Controllers\Api\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\Product;
use App\Services\Accounting\StockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    private StockService $stock;

    public function __construct(StockService $stock)
    {
        $this->stock = $stock;
    }

    /** GET /api/accounting/products  (?search, ?category, ?stock=low|out) */
    public function index(Request $request)
    {
        $query = Product::where('active', true);

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                  ->orWhere('sku', 'like', "%{$s}%")
                  ->orWhere('brand', 'like', "%{$s}%")
                  ->orWhere('model', 'like', "%{$s}%")
                  ->orWhere('hsn_code', 'like', "%{$s}%")
                  ->orWhere('category', 'like', "%{$s}%");
            });
        }
        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }
        if ($request->stock === 'out') {
            $query->where('track_stock', true)->where('stock_quantity', '<=', 0);
        } elseif ($request->stock === 'low') {
            $query->where('track_stock', true)->whereNotNull('reorder_level')
                ->whereColumn('stock_quantity', '<=', 'reorder_level');
        }

        $perPage  = min((int) $request->get('per_page', 20), 500);
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
        $validated = $request->validate($this->rules() + [
            'name'          => 'required|string|max:255',
            'unit_price'    => 'required|numeric|min:0',
            'opening_stock' => 'nullable|numeric|min:0',
        ]);

        $opening = (float) ($validated['opening_stock'] ?? 0);
        unset($validated['opening_stock']);
        $validated['user_id'] = Auth::id();
        $validated['active']  = true;

        $product = DB::transaction(function () use ($validated, $opening) {
            $product = Product::create($validated);
            if ($product->track_stock && $opening > 0) {
                $this->stock->move($product, 'opening', $opening, ['note' => 'Opening stock']);
            }
            return $product->fresh();
        });

        return response()->json([
            'message' => 'Product created successfully',
            'data'    => $product,
        ], 201);
    }

    /** PUT /api/accounting/products/{id} */
    public function update(Request $request, $id)
    {
        $product = Product::findOrFail($id);

        $validated = $request->validate($this->rules() + [
            'name'       => 'sometimes|required|string|max:255',
            'unit_price' => 'sometimes|required|numeric|min:0',
            'active'     => 'sometimes|boolean',
        ]);

        $product->update($validated);

        return response()->json([
            'message' => 'Product updated successfully',
            'data'    => $product->fresh(),
        ]);
    }

    /** DELETE /api/accounting/products/{id} */
    public function destroy($id)
    {
        $product = Product::findOrFail($id);
        $product->delete();

        return response()->json(['message' => 'Product deleted successfully']);
    }

    /** POST /api/accounting/products/{id}/image  (multipart: image) */
    public function uploadImage(Request $request, $id)
    {
        $product = Product::findOrFail($id);
        $request->validate(['image' => 'required|image|mimes:jpg,jpeg,png,webp|max:4096']);

        $this->deleteImageFile($product->image);
        $path = $request->file('image')->storeAs(
            'products',
            $product->id . '-' . Str::random(8) . '.' . $request->file('image')->extension(),
            'public'
        );
        $product->update(['image' => $path]);

        return response()->json(['message' => 'Product image uploaded', 'data' => $product->fresh()]);
    }

    /** DELETE /api/accounting/products/{id}/image */
    public function deleteImage($id)
    {
        $product = Product::findOrFail($id);
        $this->deleteImageFile($product->image);
        $product->update(['image' => null]);

        return response()->json(['message' => 'Product image removed', 'data' => $product->fresh()]);
    }

    /** GET /api/accounting/products/{id}/stock-movements */
    public function stockMovements($id)
    {
        $product   = Product::findOrFail($id);
        $movements = $product->stockMovements()->with('user:id,name')
            ->orderByDesc('id')->limit(200)->get();

        return response()->json(['data' => $movements]);
    }

    /**
     * POST /api/accounting/products/{id}/stock
     * type: in (received), out (issued/damaged), set (physical count)
     */
    public function adjustStock(Request $request, $id)
    {
        $product = Product::findOrFail($id);
        $v = $request->validate([
            'type'      => 'required|in:in,out,set',
            'quantity'  => 'required|numeric|min:0|max:999999999',
            'date'      => 'nullable|date',
            'reference' => 'nullable|string|max:100',
            'note'      => 'nullable|string|max:500',
        ]);

        if (!$product->track_stock) {
            $product->update(['track_stock' => true]);
        }

        $qty    = (float) $v['quantity'];
        $change = $v['type'] === 'in' ? $qty : ($v['type'] === 'out' ? -$qty : $qty - (float) $product->stock_quantity);
        $type   = ['in' => 'in', 'out' => 'out', 'set' => 'adjustment'][$v['type']];

        $movement = $this->stock->move($product, $type, $change, [
            'date'      => $v['date'] ?? null,
            'reference' => isset($v['reference']) ? strip_tags($v['reference']) : null,
            'note'      => isset($v['note']) ? strip_tags($v['note']) : null,
        ], $v['type'] !== 'out');

        return response()->json([
            'message'  => $movement ? 'Stock updated' : 'Stock unchanged',
            'data'     => $product->fresh(),
            'movement' => $movement,
        ]);
    }

    private function rules(): array
    {
        return [
            'sku'            => 'nullable|string|max:60',
            'brand'          => 'nullable|string|max:120',
            'model'          => 'nullable|string|max:120',
            'description'    => 'nullable|string|max:2000',
            'features'       => 'nullable|string|max:5000',
            'hsn_code'       => 'nullable|string|max:20',
            'category'       => 'nullable|string|max:150',
            'unit'           => 'nullable|string|max:30',
            'purchase_price' => 'nullable|numeric|min:0',
            'tax_rate'       => 'nullable|numeric|min:0|max:100',
            'track_stock'    => 'sometimes|boolean',
            'reorder_level'  => 'nullable|numeric|min:0',
        ];
    }

    private function deleteImageFile(?string $path): void
    {
        if ($path && str_starts_with($path, 'products/') && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }
}
