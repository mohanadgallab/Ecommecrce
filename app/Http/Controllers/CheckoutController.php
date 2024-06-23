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
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class CheckoutController extends Controller
{
  // Payment Start from Here
  public function checkout(Request $request)
  {
    // Grap USER
    $user = $request->user();

    $key = 'sk_test_51PQ79nK5dXjrsTGrvnNimynzkzUCSrh6gPaxqLLQCGNvMAOaGlb5oBTYq85fJIeFBzztNG1zaToIUd69A0BDrOQ100DmAtQt3V';

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
    foreach ($orderItems as $orderItem) {
      $orderItem['order_id'] = $order->id;
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

    $key = 'sk_test_51PQ79nK5dXjrsTGrvnNimynzkzUCSrh6gPaxqLLQCGNvMAOaGlb5oBTYq85fJIeFBzztNG1zaToIUd69A0BDrOQ100DmAtQt3V';
    // $stripe = new \Stripe\StripeClient('sk_test_51PQ79nK5dXjrsTGrvnNimynzkzUCSrh6gPaxqLLQCGNvMAOaGlb5oBTYq85fJIeFBzztNG1zaToIUd69A0BDrOQ100DmAtQt3V');
    $stripe = new \Stripe\StripeClient($key);
    // \Stripe\Stripe::setApiKey('sk_test_51PQ79nK5dXjrsTGrvnNimynzkzUCSrh6gPaxqLLQCGNvMAOaGlb5oBTYq85fJIeFBzztNG1zaToIUd69A0BDrOQ100DmAtQt3V');
    try {
      $session_id = $request->get('session_id');
      $session = $stripe->checkout->sessions->retrieve($session_id);
      if (!$session) {
        return view('checkout.failure', ['message' => 'Invalid Session ID']);
      }

      $payment = Payment::query()
        ->where(['session_id' => $session_id])
        ->whereIn('status', [PaymentStatus::Pending, PaymentStatus::Paid])
        ->first();
      if (!$payment) {
        throw new NotFoundHttpException();
      }
      if ($payment->status === PaymentStatus::Pending->value) {
        $this->updateOrderAndSession($payment);
      }

      $customer = $stripe->customers->retrieve($session->customer);
      return view('checkout.success', compact('customer'));
    } catch (NotFoundHttpException $e) {
      throw $e;
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


    $key = 'sk_test_51PQ79nK5dXjrsTGrvnNimynzkzUCSrh6gPaxqLLQCGNvMAOaGlb5oBTYq85fJIeFBzztNG1zaToIUd69A0BDrOQ100DmAtQt3V';

    \Stripe\Stripe::setApiKey($key);

    $lineItems = [];
    foreach ($order->items as $item) {
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

    $order->payment->session_id = $session->id;
    $order->payment->save();

    return redirect($session->url);
  }
  // WebHook
  public function webhook()
  {
    // return response('', 200);
    $key = 'sk_test_51PQ79nK5dXjrsTGrvnNimynzkzUCSrh6gPaxqLLQCGNvMAOaGlb5oBTYq85fJIeFBzztNG1zaToIUd69A0BDrOQ100DmAtQt3V';

    $stripe = new \Stripe\StripeClient($key);

    //  'whsec_53236465a7a40bc301ef336d4a7cec577fdd8b0ed66362125c59585f9c285d4a'
    $endpoint_secret = 'whsec_53236465a7a40bc301ef336d4a7cec577fdd8b0ed66362125c59585f9c285d4a';
    // $endpoint_secret = env('STRIPE_WEBHOOK_SECRET');
    $payload = @file_get_contents('php://input');
    $sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'];
    $event = null;

    try {
      $event = \Stripe\Webhook::constructEvent(
        $payload,
        $sig_header,
        $endpoint_secret
      );
    } catch (\UnexpectedValueException $e) {
      // Invalid payload
      return response('', 401);
      exit();
    } catch (\Stripe\Exception\SignatureVerificationException $e) {
      // Invalid signature
      return response('', 402);
    }

    // Handle the event
    switch ($event->type) {
      case 'checkout.session.completed':
        $paymentIntent = $event->data->object;
        $sessionId = $paymentIntent['id'];
        $payment = Payment::query()
          ->where(['session_id' => $sessionId, 'status' => PaymentStatus::Pending])
          ->first();
        if ($payment) {
          $this->updateOrderAndSession($payment);
        }
      default:
        echo 'Received unknown event type ' . $event->type;
    }

    return response('', 200);
  }

  public function updateOrderAndSession(Payment $payment)
  {


    $payment->status = PaymentStatus::Paid;
    $payment->update();

    $order = $payment->order;

    $order->status = OrderStatus::Paid;
    $order->update();
  }
}
