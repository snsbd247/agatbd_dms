<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductExportController extends Controller
{
    public function __invoke(Request $request)
    {
        $query = Product::query()
            ->with(['company', 'brand', 'category', 'unit'])
            ->withSum('stocks', 'quantity')
            ->when($request->query('search'), fn ($q) => $q->where('name', 'like', '%'.$request->query('search').'%')
                ->orWhere('sku', 'like', '%'.$request->query('search').'%')
                ->orWhere('barcode', 'like', '%'.$request->query('search').'%'))
            ->when($request->query('filter_category_id'), fn ($q) => $q->where('category_id', $request->query('filter_category_id')))
            ->when($request->query('filter_brand_id'), fn ($q) => $q->where('brand_id', $request->query('filter_brand_id')))
            ->when($request->query('filter_company_id'), fn ($q) => $q->where('company_id', $request->query('filter_company_id')))
            ->orderBy('name');

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Product Code', 'Product Name', 'Category', 'Brand', 'Unit', 'Price', 'Stock Qty', 'Status']);
            $query->chunk(200, function ($chunk) use ($out) {
                foreach ($chunk as $product) {
                    fputcsv($out, [
                        $product->sku,
                        $product->name,
                        $product->category?->name,
                        $product->brand?->name,
                        $product->unit?->short_code,
                        $product->sale_price,
                        $product->stocks_sum_quantity ?? 0,
                        $product->active ? 'Active' : 'Inactive',
                    ]);
                }
            });
            fclose($out);
        }, 'products-'.today()->toDateString().'.csv', ['Content-Type' => 'text/csv']);
    }
}
