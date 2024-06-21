<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Http\Helpers\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;

class CheckoutController extends Controller
{
  // Payment Start from Here
  public function checkout(Request $request)
  {
    // Grap USER
    $user = $request->user();

    $key = $_ENV['STRIPE_SECRET_KEY'];

    \Stripe\Stripe::setApiKey($key);

    [$products, $cartItems] = Cart::getProductsAndCartItems();
    $orderItems = [];
    $lineItems = [];
    $totlaPrice = 0;
    foreach ($products as $product) {
      $quantity = $cartItems[$product->id]['quantity'];
      $totlaPrice += $product->price * $quantity;
      $lineItems[] = [
        'price_data' => [
          'currency' => 'usd',
          'product_data' => [
            'name' => $product->title
          ],
          'unit_amount_decimal' => $product->price * 100,
        ],
        'quantity' => $quantity,
      ];
      $orderItems[] = [
        'product_id' => $product->id,
        'quantity' => $quantity,
        'unit_price' => $product->price
      ];
    }

    $session = \Stripe\Checkout\Session::create([
      'line_items' => $lineItems,
      'mode' => 'payment',
      'success_url' => route('checkout.sucecess', [], true) . '?session_id={CHECKOUT_SESSION_ID}',
      'cancel_url' => route('checkout.failure', [], true),
      'customer_creation' => 'always',
    ]);

    // CREATE ORDER
    $orderData = [
      'total_price' => $totlaPrice,
      'status' => OrderStatus::Unpaid,
      'created_by' => $user->id,
      'updated_by' => $user->id,
    ];

    $order = Order::create($orderData);

    // CREATE ORDER ITEMS
    foreach($orderItems as $orderItem){
      $orderItem['order_id'] = $order->id ;
      OrderItem::create($orderItem);
    }
    // CREATE PAYMENT 
    $paymentData = [
      'order_id' => $order->id,
      'amount' => $totlaPrice,
      'status' => PaymentStatus::Pending,
      'type' => 'cc',
      'created_by' => $user->id,
      'updated_by' => $user->id,
      'session_id' => $session->id
    ];
    Payment::create($paymentData);

    // Delete Cart Item 
    CartItem::where(['user_id' => $user->id])->delete();
    
    return redirect($session->url);
  }
  public function success(Request $request)
  {
    // Grap USER to use delete cart item
    $user = $request->user();

    // $stripe = new \Stripe\StripeClient('sk_test_51PQ79nK5dXjrsTGrvnNimynzkzUCSrh6gPaxqLLQCGNvMAOaGlb5oBTYq85fJIeFBzztNG1zaToIUd69A0BDrOQ100DmAtQt3V');
    $stripe = new \Stripe\StripeClient($_ENV['STRIPE_SECRET_KEY']);
    // \Stripe\Stripe::setApiKey('sk_test_51PQ79nK5dXjrsTGrvnNimynzkzUCSrh6gPaxqLLQCGNvMAOaGlb5oBTYq85fJIeFBzztNG1zaToIUd69A0BDrOQ100DmAtQt3V');
    try {
      $session = $stripe->checkout->sessions->retrieve($request->get('session_id'));
      if (!$session) {
        return view('checkout.failure', ['message' => 'Invalid Session ID']);
      }

      $payment = Payment::query()->where(['session_id' => $session->id, 'status' => PaymentStatus::Pending])->first();

      if (!$payment) {
        return view('checkout.failure', ['message' => 'Payment Does not Exist']);
      }

      $payment->status = PaymentStatus::Paid;
      $payment->update();

      $order = $payment->order;

      $order->status = OrderStatus::Paid;
      $order->update();

      

      $customer = $stripe->customers->retrieve($session->customer);
      return view('checkout.success', compact('customer'));
    } catch (\Exception $e) {
      return view('checkout.failure', ['message' => $e->getMessage()]);
    }
  }
  public function failure(Request $request)
  {
    return view('checkout.failure', ['message' => 'Payment Does not Exist']);
  }
  public function checkoutOrder(Order $order, Request $request)
  {
    // Grap USER
    $user = $request->user();

    $key = $_ENV['STRIPE_SECRET_KEY'];

    \Stripe\Stripe::setApiKey($key);

    $lineItems = [] ;
    foreach($order->items as $item){
      $lineItems[] = [
        'price_data' => [
          'currency' => 'usd',
          'product_data' => [
            'name' => $item->product->title
          ],
          'unit_amount' => $item->unit_price * 100
        ],
        'quantity' => $item->quantity
      ];
    }

    $session = \Stripe\Checkout\Session::create([
      'line_items' => $lineItems,
      'mode' => 'payment',
      'success_url' => route('checkout.sucecess', [], true) . '?session_id={CHECKOUT_SESSION_ID}',
      'cancel_url' => route('checkout.failure', [], true),
      'customer_creation' => 'always',
    ]);

    $order->payment->session_id = $session->id ;
    $order->payment->save();
    
    return redirect($session->url);
  }
}
