<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\Logs;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Srmklive\PayPal\Services\PayPal as PayPalClient;

class PayPalController extends Controller
{
    /**
     * create transaction.
     *
     * @return \Illuminate\Http\Response
     */
    public function createTransaction(Request $req)
    {
        $order = new Order();
        $order->User_Id = Auth::id();
        $order->O_Name = 'lydia';
        $order->O_Email = 'lydia@gmail,com';
        $order->O_Street_1 = 'kl';
        $order->O_Postcode = '31400';
        $order->O_City = 'kl';
        $order->O_State = 'kl';
        $order->O_Phone = '0133879380';
        $order->O_Notes = 'nothing';
        $order->O_Payment = 'PayPal';
        $order->Tracking_No = rand(1000, 9999);
        $order->Remarks = 'call me';

        $total = 0;
        $cartitems_total = Cart::where('Cust_Id', Auth::id())->get();
        foreach ($cartitems_total as $prod) {
            $total += $prod->products->P_Price * $prod->Pro_Qty;
            $otype = $prod->Order_Type;
            $bookdate = $prod->BookDate;
            $booktime = $prod->BookTime;
            $bookpax = $prod->BookPax;
            $booktable = $prod->BookTable;
        }

        $combinedDT = date('Y-m-d H:i:s', strtotime("$bookdate $booktime"));

        $order->O_Type = $otype;
        $order->DateTime = $combinedDT;
        $order->T_Pax = $bookpax;
        $order->T_Id = $booktable;
        $order->O_Total_Price = $total;
        $order->save();

        $logs = new Logs;
        $logs->Cust_Id = Auth::id();
        $logs->Log_Module = $req->input('Log_Module');
        $logs->Log_Pay_Type = 0;
        $logs->Log_Status = $req->input('Log_Status');
        $logs->created_at = Carbon::now();
        $logs->updated_at = Carbon::now();

        $cartitems = Cart::where('Cust_Id', Auth::id())->get();
        foreach ($cartitems as $item) {
            OrderProduct::create([
                'Order_Id' => $order->id,
                'P_Id' => $item->Pro_Id,
                'Order_Quantity' => $item->Pro_Qty,
                'Order_Price' => $item->products->P_Price * $item->Pro_Qty,
            ]);
        }
        $cartitems = Cart::where('Cust_Id', Auth::id())->get();
        Cart::destroy($cartitems);

        return view('checkout_complete');
    }

    /**
     * process transaction.
     *
     * @return \Illuminate\Http\Response
     */
    public function processTransaction(Request $request)
    {
        $provider = new PayPalClient;
        $provider->setApiCredentials(config('paypal'));
        $paypalToken = $provider->getAccessToken();

        $total = 0;
        $cartitems_total = Cart::where('Cust_Id', Auth::id())->get();
        foreach($cartitems_total as $prod)
        {
            $total += $prod->products->P_Price * $prod->Pro_Qty;
        }

        $response = $provider->createOrder([
            "intent" => "CAPTURE",
            "application_context" => [
                "return_url" => route('successTransaction'),
                "cancel_url" => route('cancelTransaction'),
            ],
            "purchase_units" => [
                0 => [
                    "amount" => [
                        "currency_code" => "MYR",
                        "value" => $total
                    ]
                ]
            ]
        ]);

        if (isset($response['id']) && $response['id'] != null) {

            // redirect to approve href
            foreach ($response['links'] as $links) {
                if ($links['rel'] == 'approve') {
                    return redirect()->away($links['href']);
                }
            }

            return redirect()
                ->route('createTransaction')
                ->with('error', 'Something went wrong.');

        } else {
            return redirect()
                ->route('createTransaction')
                ->with('error', $response['message'] ?? 'Something went wrong.');
        }
    }

    /**
     * success transaction.
     *
     * @return \Illuminate\Http\Response
     */
    public function successTransaction(Request $request)
    {
        $provider = new PayPalClient;
        $provider->setApiCredentials(config('paypal'));
        $provider->getAccessToken();
        $response = $provider->capturePaymentOrder($request['token']);

        if (isset($response['status']) && $response['status'] == 'COMPLETED') {
            return redirect()
                ->route('createTransaction')
                ->with('success', 'Transaction complete.');
        } else {
            return redirect()
                ->route('createTransaction')
                ->with('error', $response['message'] ?? 'Something went wrong.');
        }
    }

    /**
     * cancel transaction.
     *
     * @return \Illuminate\Http\Response
     */
    public function cancelTransaction(Request $request)
    {
        return redirect()
            ->route('createTransaction')
            ->with('error', $response['message'] ?? 'Something went wrong.');
    }
}