<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProductController extends Controller
{
    public function index()
    {
        return Product::all();
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric',
            'image' => 'nullable|string',
        ]);

        $product = Product::create([
            'user_id' => Auth::id(),
            'name' => $request->name,
            'description' => $request->description,
            'price' => $request->price,
            'image' => $request->image,
        ]);

        return response()->json($product, 201);
    }

    public function show(Product $product)
    {
        return response()->json($product);
    }

    public function update(Request $request, Product $product)
    {
        if ($product->user_id !== Auth::id()) {
            return response()->json(['error' => 'Non autorisé'], 403);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric',
            'image' => 'nullable|string',
        ]);

        $product->update($request->all());

        return response()->json($product);
    }

    public function destroy(Product $product)
    {
        if ($product->user_id !== Auth::id()) {
            return response()->json(['error' => 'Non autorisé'], 403);
        }

        $product->delete();

        return response()->json(['message' => 'Produit supprimé']);
    }
}

