<?php

namespace App\Http\Controllers;

use App\Models\Medicine;
use App\Support\Basket;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class ShopController extends Controller
{
    /**
     * The catalogue, optionally narrowed to one category (?category=stomach).
     */
    public function index(Request $request, Basket $basket): View
    {
        $category = $request->query('category');
        if (! is_string($category) || ! array_key_exists($category, Medicine::CATEGORIES)) {
            $category = null;
        }

        return view('shop.index', [
            'medicines' => Medicine::active()->when($category, fn ($query) => $query->where('category', $category))->orderBy('id')->get(),
            'categories' => Medicine::CATEGORIES,
            'category' => $category,
            'basket' => $basket,
        ]);
    }

    /**
     * One medicine's page.
     */
    public function show(Medicine $medicine, Basket $basket): View
    {
        abort_unless($medicine->active, 404);

        return view('shop.show', [
            'medicine' => $medicine,
            'inBasket' => $basket->quantity($medicine),
            'max' => $basket->max(),
        ]);
    }
}
