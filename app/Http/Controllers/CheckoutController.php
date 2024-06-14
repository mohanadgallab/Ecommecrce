<?php

namespace App\Http\Controllers;

use App\Http\Helpers\Cart;
use Illuminate\Http\Request;

class CheckoutController extends Controller
{
  // Payment Start from Here
  public function checkout(Request $request)
  {

    $key = 'sk_test_51PQ79nK5dXjrsTGrvnNimynzkzUCSrh6gPaxqLLQCGNvMAOaGlb5oBTYq85fJIeFBzztNG1zaToIUd69A0BDrOQ100DmAtQt3V';
    // $stripe = new \Stripe\StripeClient('sk_test_51PQ79nK5dXjrsTGrvnNimynzkzUCSrh6gPaxqLLQCGNvMAOaGlb5oBTYq85fJIeFBzztNG1zaToIUd69A0BDrOQ100DmAtQt3V');
    \Stripe\Stripe::setApiKey($key);

    [$products, $cartItems] = Cart::getProductsAndCartItems();
    $lineItems = [];

    foreach ($products as $product) {
      $lineItems[] = [

        'price_data' => [
          'currency' => 'usd',
          'product_data' => [
            'name' => $product->title
          ],
          'unit_amount_decimal' => $product->price * 100,
        ],
        'quantity' => $cartItems[$product->id]['quantity'],
      ];
    }

    $session = \Stripe\Checkout\Session::create([
      'line_items' => $lineItems,
      'mode' => 'payment',
      'success_url' => route('checkout.sucecess', [], true) . '?session_id={CHECKOUT_SESSION_ID}',
      'cancel_url' => route('checkout.failure', [], true),
      'customer_creation' => 'always',
    ]);

    return redirect($session->url);
  }
  public function success(Request $request)
  {
    $stripe = new \Stripe\StripeClient('sk_test_51PQ79nK5dXjrsTGrvnNimynzkzUCSrh6gPaxqLLQCGNvMAOaGlb5oBTYq85fJIeFBzztNG1zaToIUd69A0BDrOQ100DmAtQt3V');
    // \Stripe\Stripe::setApiKey('sk_test_51PQ79nK5dXjrsTGrvnNimynzkzUCSrh6gPaxqLLQCGNvMAOaGlb5oBTYq85fJIeFBzztNG1zaToIUd69A0BDrOQ100DmAtQt3V');
    try {
      $session = $stripe->checkout->sessions->retrieve($request->get('session_id'));
      if (!$session) {
        return view('checkout.failure') ;
      }
      $customer = $stripe->customers->retrieve($session->customer);
      return view('checkout.success', compact('customer'));

    } catch (\Exception $e) {
      return view('checkout.failure') ;
    }

  }
  public function failure(Request $request)
  {
  }
}
