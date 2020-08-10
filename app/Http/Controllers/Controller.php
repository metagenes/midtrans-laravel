<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

use Illuminate\Http\Controllers;
use App\Model\Checkout;
use Illuminate\Http\Request;
// use App\Model\Checkout;
use Veritrans_Config;
use Veritrans_Snap;
use Veritrans_Notification;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    public function welcome(){
        return "Welcome to Donation API";
    }

    /**
     * Make request global.
     *
     * @var \Illuminate\Http\Request
     */
    protected $request;
 
    /**
     * Class constructor.
     *
     * @param \Illuminate\Http\Request $request User Request
     *
     * @return void
     */
    public function __construct(Request $request)
    {
        $this->request = $request;
 
        // Set midtrans configuration
        Veritrans_Config::$serverKey = config('services.midtrans.serverKey');
        Veritrans_Config::$isProduction = config('services.midtrans.isProduction');
        Veritrans_Config::$isSanitized = config('services.midtrans.isSanitized');
        Veritrans_Config::$is3ds = config('services.midtrans.is3ds');
    }
 

 
    /**
     * Submit checkout.
     *
     * @return array
     */
    public function submitCheckout()
    {
        \DB::transaction(function(){
            // Save donasi ke database
            $checkout = Checkout::create([
                'customer_name' => $this->request->customer_name,
                'customer_email' => $this->request->customer_email,
                'customer_phone' => $this->request->customer_phone,
                'total_price' => floatval($this->request->total_price),
                'product_description' => $this->request->product_description,
            ]);
 
            // Buat transaksi ke midtrans kemudian save snap tokennya.
            $payload = [
                'transaction_details' => [
                    'order_id'      => $checkout->id,
                    'total price'  => $checkout->total_price,
                ],
                'customer_details' => [
                    'first_name'    => $checkout->customer_name,
                    'email'         => $checkout->customer_email,
                    'phone'         => $checkout->customer_phone,
                    
                ],
                'item_details' => [
                    [
                        'id'       => $checkout->id,
                        'price'    => $checkout->total_price,
                        'quantity' => 1,
                        'name'     => $checkout->product_description,
                    ]
                ]
            ];
          

          // return $transaction;
            $snapToken = Veritrans_Snap::getSnapToken($payload);
            $checkout->snap_token = $snapToken;
            $checkout->save();
 
            // Beri response snap token
            $this->response['snap_token'] = $snapToken;
            $this->response['redirect_url'] = 'https://app.sandbox.midtrans.com/snap/v1/transactions/'.$snapToken.'';
            $this->response['transaction'] = $payload;
        });
 
        return response()->json($this->response);
    }
 
    /**
     * Midtrans notification handler.
     *
     * @param Request $request
     * 
     * @return void
     */
    public function notificationHandler(Request $request)
    {
        $notif = new Veritrans_Notification();
        \DB::transaction(function() use($notif) {
 
          $transaction = $notif->transaction_status;
          $type = $notif->payment_type;
          $orderId = $notif->order_id;
          $fraud = $notif->fraud_status;
          $donation = Donation::findOrFail($orderId);
 
          if ($transaction == 'capture') {
 
            // For credit card transaction, we need to check whether transaction is challenge by FDS or not
            if ($type == 'credit_card') {
 
              if($fraud == 'challenge') {
                // TODO set payment status in merchant's database to 'Challenge by FDS'
                // TODO merchant should decide whether this transaction is authorized or not in MAP
                // $checkout->addUpdate("Transaction order_id: " . $orderId ." is challenged by FDS");
                $checkout->setPending();
              } else {
                // TODO set payment status in merchant's database to 'Success'
                // $donation->addUpdate("Transaction order_id: " . $orderId ." successfully captured using " . $type);
                $checkout->setSuccess();
              }
 
            }
 
          } elseif ($transaction == 'settlement') {
 
            // TODO set payment status in merchant's database to 'Settlement'
            // $donation->addUpdate("Transaction order_id: " . $orderId ." successfully transfered using " . $type);
            $checkout->setSuccess();
 
          } elseif($transaction == 'pending'){
 
            // TODO set payment status in merchant's database to 'Pending'
            // $donation->addUpdate("Waiting customer to finish transaction order_id: " . $orderId . " using " . $type);
            $checkout->setPending();
 
          } elseif ($transaction == 'deny') {
 
            // TODO set payment status in merchant's database to 'Failed'
            // $donation->addUpdate("Payment using " . $type . " for transaction order_id: " . $orderId . " is Failed.");
            $checkout->setFailed();
 
          } elseif ($transaction == 'expire') {
 
            // TODO set payment status in merchant's database to 'expire'
            // $donation->addUpdate("Payment using " . $type . " for transaction order_id: " . $orderId . " is expired.");
            $checkout->setExpired();
 
          } elseif ($transaction == 'cancel') {
 
            // TODO set payment status in merchant's database to 'Failed'
            // $donation->addUpdate("Payment using " . $type . " for transaction order_id: " . $orderId . " is canceled.");
            $checkout->setFailed();
 
          }
 
        });
 
        return;
    }

}
