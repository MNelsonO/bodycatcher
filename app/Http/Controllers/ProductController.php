<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProductRequest;
use App\SalesPipelines\StageKind;
use App\Settings\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', Product::class);

        $openKinds = [StageKind::Active->value];
        $won = StageKind::Won->value;
        $lost = StageKind::Lost->value;

        $counts = DB::table('leads')
            ->join('pipeline_stages', 'pipeline_stages.id', '=', 'leads.current_stage_id')
            ->selectRaw('leads.product_id, count(*) as total')
            ->selectRaw("sum(case when pipeline_stages.kind = ? then 1 else 0 end) as open_count", [$openKinds[0]])
            ->selectRaw("sum(case when pipeline_stages.kind = ? then 1 else 0 end) as won_count", [$won])
            ->selectRaw("sum(case when pipeline_stages.kind = ? then 1 else 0 end) as lost_count", [$lost])
            ->groupBy('leads.product_id')
            ->get()
            ->keyBy('product_id');

        return Inertia::render('products/index', [
            'products' => Product::query()->orderBy('name')->get()->map(function (Product $product) use ($counts): array {
                $row = $counts->get($product->id);

                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'slug' => $product->slug,
                    'description' => $product->description,
                    'pricing_notes' => $product->pricing_notes,
                    'is_active' => $product->is_active,
                    'archived' => $product->archived_at !== null,
                    'leads_total' => (int) ($row->total ?? 0),
                    'leads_open' => (int) ($row->open_count ?? 0),
                    'leads_won' => (int) ($row->won_count ?? 0),
                    'leads_lost' => (int) ($row->lost_count ?? 0),
                ];
            }),
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
