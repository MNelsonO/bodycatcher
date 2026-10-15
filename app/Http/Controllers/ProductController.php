<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProductRequest;
use App\Settings\Product;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', Product::class);

        return Inertia::render('products/index', [
            'products' => Product::query()->orderBy('name')->get()->map(fn (Product $product): array => [
                'id' => $product->id,
                'name' => $product->name,
                'slug' => $product->slug,
                'description' => $product->description,
                'pricing_notes' => $product->pricing_notes,
                'is_active' => $product->is_active,
                'archived' => $product->archived_at !== null,
            ]),
        ]);
    }

    public function store(ProductRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $active = $request->boolean('is_active', true);

        Product::query()->create([
            'name' => $data['name'],
            'slug' => $data['slug'],
            'description' => $data['description'] ?? null,
            'pricing_notes' => $data['pricing_notes'] ?? null,
            'is_active' => $active,
            'archived_at' => $active ? null : now(),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Product saved.']);

        return redirect()->route('products.index');
    }

    public function update(ProductRequest $request, Product $product): RedirectResponse
    {
        $data = $request->validated();
        $active = $request->boolean('is_active');

        $product->update([
            'name' => $data['name'],
            'slug' => $data['slug'],
            'description' => $data['description'] ?? null,
            'pricing_notes' => $data['pricing_notes'] ?? null,
            'is_active' => $active,
            'archived_at' => $active ? null : ($product->archived_at ?? now()),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Product updated.']);

        return redirect()->route('products.index');
    }

    public function destroy(Product $product): RedirectResponse
    {
        $this->authorize('delete', $product);

        $product->update([
            'is_active' => false,
            'archived_at' => $product->archived_at ?? now(),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Product archived.']);

        return redirect()->route('products.index');
    }
}
