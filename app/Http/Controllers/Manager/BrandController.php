<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use Illuminate\Http\Request;
use Inertia\Inertia;

class BrandController extends Controller
{
    public function index()
    {
        // Cache brands list for 1 hour to reduce database load
        $brands = cache()->remember('brands_list_full', 3600, function () {
            return Brand::orderBy('name')->get();
        });

        return Inertia::render('Manager/Brands/Index', [
            'brands' => $brands
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:brands,name',
        ]);

        $brand = Brand::create($validated);

        return redirect()->route('manager.brands.index')
            ->with('success', 'Бренд успешно создан');
    }

    public function edit(Brand $brand)
    {
        return Inertia::render('Manager/Brands/Edit', [
            'brand' => $brand
        ]);
    }

    public function update(Request $request, Brand $brand)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:brands,name,' . $brand->id,
        ]);

        $brand->update($validated);

        return redirect()->route('manager.brands.index')
            ->with('success', 'Бренд успешно обновлен');
    }
}
