<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BrandController extends Controller
{
    public function index(Request $request): Response
    {
        $brands = Brand::query()
            ->orderByDesc('created_at')
            ->get(['id', 'name', 'created_at']);

        return Inertia::render('Brands/Index', [
            'brands' => $brands,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        Brand::create($data);

        return back()->with('status', 'brand-created');
    }

    public function update(Request $request, Brand $brand): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $brand->update($data);

        return back()->with('status', 'brand-updated');
    }

    public function destroy(Brand $brand): RedirectResponse
    {
        $brand->delete();
        return back()->with('status', 'brand-deleted');
    }
}
