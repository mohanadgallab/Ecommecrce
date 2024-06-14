<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Http\Helpers\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\Request;

class CheckoutController extends Controller
{
  // Payment Start from Here
  public function checkout(Request $request)
  {
    // Grap USER
    $user = $request->user() ;

    $key = 'sk_test_51PQ79nK5dXjrsTGrvnNimynzkzUCSrh6gPaxqLLQCGNvMAOaGlb5oBTYq85fJIeFBzztNG1zaToIUd69A0BDrOQ100DmAtQt3V';
    // $stripe = new \Stripe\StripeClient('sk_test_51PQ79nK5dXjrsTGrvnNimynzkzUCSrh6gPaxqLLQCGNvMAOaGlb5oBTYq85fJIeFBzztNG1zaToIUd69A0BDrOQ100DmAtQt3V');
    \Stripe\Stripe::setApiKey($key);

    [$products, $cartItems] = Cart::getProductsAndCartItems();
    $lineItems = [];
    $totlaPrice = 0 ;
    foreach ($products as $product) {
      $quantity = $cartItems[$product->id]['quantity'] ;
      $totlaPrice += $product->price * $quantity ;
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
    }

    $session = \Stripe\Checkout\Session::create([
      'line_items' => $lineItems,
      'mode' => 'payment',
      'success_url' => route('checkout.sucecess', [], true) . '?session_id={CHECKOUT_SESSION_ID}',
      'cancel_url' => route('checkout.failure', [], true),
      'customer_creation' => 'always',
    ]);

    // ORDER
    $orderData = [
      'total_price' => $totlaPrice ,
      'status' => OrderStatus::Unpaid ,
      'created_by' => $user->id ,
      'updated_by' => $user->id ,
    ];

    $order = Order::create($orderData);

    // PAYMENT TABLE
    $paymentData = [
      'order_id' => $order->id ,
      'amount' => $totlaPrice ,
      'status' => PaymentStatus::Pending,
      'type' => 'cc',
      'created_by' => $user->id ,
      'updated_by' => $user->id ,
      'session_id' => $session->id
    ];
    $payment = Payment::create($paymentData) ;

    return redirect($session->url);
  }
  public function success(Request $request)
  {
    // Grap USER to use delete cart item
    $user = $request->user() ;

    $stripe = new \Stripe\StripeClient('sk_test_51PQ79nK5dXjrsTGrvnNimynzkzUCSrh6gPaxqLLQCGNvMAOaGlb5oBTYq85fJIeFBzztNG1zaToIUd69A0BDrOQ100DmAtQt3V');
    // \Stripe\Stripe::setApiKey('sk_test_51PQ79nK5dXjrsTGrvnNimynzkzUCSrh6gPaxqLLQCGNvMAOaGlb5oBTYq85fJIeFBzztNG1zaToIUd69A0BDrOQ100DmAtQt3V');
    try {
      $session = $stripe->checkout->sessions->retrieve($request->get('session_id'));
      if (!$session) {
        return view('checkout.failure', ['message' => 'Invalid Session ID']) ;
      }

      $payment = Payment::query()->where(['session_id' =>$session->id, 'status' => PaymentStatus::Pending ])->first();
      
      if (!$payment) {
        return view('checkout.failure', ['message' => 'Payment Does not Exist']) ;
      }

      $payment->status = PaymentStatus::Paid ;
      $payment->update() ;

      $order = $payment->order ;

      $order->status = OrderStatus::Paid ;
      $order->update() ;

      // Delete Cart Item 
      CartItem::where(['user_id' => $user->id])->delete();

      $customer = $stripe->customers->retrieve($session->customer);
      return view('checkout.success', compact('customer'));

    } catch (\Exception $e) {
      return view('checkout.failure', ['message' => $e->getMessage()]) ;
    }

  }
  public function failure(Request $request)
  {
  }
}
