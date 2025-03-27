<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OrderController extends Controller
{
    public function index()
    {
        return Order::where('user_id', Auth::id())->get();
    }

    public function store(Request $request)
    {
        $request->validate([
            'total_price' => 'required|numeric',
        ]);

        $order = Order::create([
            'user_id' => Auth::id(),
            'total_price' => $request->total_price,
            'status' => 'pending',
        ]);

        return response()->json($order, 201);
    }

    public function show(Order $order)
    {
        if ($order->user_id !== Auth::id()) {
            return response()->json(['error' => 'Non autorisé'], 403);
        }

        return response()->json($order);
    }

    public function update(Request $request, Order $order)
    {
        if ($order->user_id !== Auth::id()) {
            return response()->json(['error' => 'Non autorisé'], 403);
        }

        $request->validate([
            'status' => 'required|in:pending,confirmed,shipped,delivered,cancelled',
        ]);

        $order->update(['status' => $request->status]);

        return response()->json($order);
    }

    public function destroy(Order $order)
    {
        if ($order->user_id !== Auth::id()) {
            return response()->json(['error' => 'Non autorisé'], 403);
        }

        $order->delete();

        return response()->json(['message' => 'Commande supprimée']);
    }
}

