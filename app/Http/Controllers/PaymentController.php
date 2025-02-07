<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use App\Models\Stkrequest;
use App\Models\C2brequest;

class PaymentController extends Controller
{
   public function token(){
    $consumerkey='kvujedPA6FfGsBussL8r7iCEBrZkpQWpw105Rhb3RNnYkCyz';
    $consumerSecret='whWZnDs29xYXIteILQNxuZWerPIjbtBwewOYeusNaBwdqpEwmAUR7tDW18YGSegc';
    $url='https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials';


    $response=Http::withBasicAuth($consumerkey,$consumerSecret)->get($url);
    return $response['access_token'];
   }

   public function initiateStkPush(){
        $accessToken=$this->token();
        $url='https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest';
        $PassKey='bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919';
        $BusinessShortCode='174379';
        $Timestamp=Carbon::now()->format('YmdHis');
        $password=base64_encode($BusinessShortCode.$PassKey.$Timestamp);
        $TransactionType='CustomerPayBillOnline';
        $Amount=1;
        $PartyA=254714274905;
        $PartyB='174379';
        $PhoneNumber=254714274905;
        $CallBackURL='https://4939-102-219-208-44.ngrok-free.app/payments/stkcallback';
        $AccountReference='Coders Base';
        $TransactionDesc='payment for goods';

          try{
        $response=Http::withToken($accessToken)->post($url,[
            'BusinessShortCode'=>$BusinessShortCode,
            'Password'=>$password,
            'Timestamp'=>$Timestamp,
            'TransactionType'=>$TransactionType,
            'Amount'=>$Amount,
            'PartyA'=>$PartyA,
            'PartyB'=>$PartyB,
            'PhoneNumber'=>$PhoneNumber,
            'CallBackURL'=>$CallBackURL,
            'AccountReference'=>$AccountReference,
            'TransactionDesc'=>$TransactionDesc
          ]);

                      }catch(\Throwable $e){
               return $e->getMessage();
          }
          //return $response;
          $res=json_decode($response);

          $ResponseCode=$res->ResponseCode;
          if($ResponseCode==0){
            $MerchantRequestID=$res->MerchantRequestID;
            $CheckoutRequestID=$res->CheckoutRequestID;
            $CustomerMessage=$res->CustomerMessage;

            //save to database
            $payment=new Stkrequest;
            $payment->phone=$PhoneNumber;
            $payment->amount=$Amount;
            $payment->reference=$AccountReference;
            $payment->description=$TransactionDesc;
            $payment->MerchantRequestID=$MerchantRequestID;
            $payment->CheckoutRequestID=$CheckoutRequestID;
            $payment->status='Requested';
            $payment->save();

            return $CustomerMessage;

          }
     }

     public function stkCallback(){
          $data=file_get_contents('php://input');
          Storage::disk('local')->put('stk.txt',$data);

          $response=json_decode($data);

          $ResultCode=$response->Body->stkCallback->ResultCode;

          if($ResultCode==0){
               $MerchantRequestID=$response->Body->stkCallback->MerchantRequestID;
               $CheckoutRequestID=$response->Body->stkCallback->CheckoutRequestID;
               $ResultDesc=$response->Body->stkCallback->ResultDesc;
               $Amount=$response->Body->stkCallback->CallbackMetadata->Item[0]->Value;
               $MpesaReceiptNumber=$response->Body->stkCallbackMetadata->Item[1]->Value;
               //$Balance=$response->Body->stkCallback->CallbackMetadata->Item[2]->Value;
               $TransactionDate=$response->Body->stkCallback->CallbackMetadata->Item[3]->Value;
               $PhoneNumber=$response->Body->stkCallback->CallbackMetadata->Item[4]->Value;

               $payment=Stkrequest::where('CheckoutRequestID',$CheckoutRequestID)->firstOrfail();
               $payment->status='Paid';
               $payment->TransactionDate=$TransactionDate;
               $payment->MpesaReceiptNumber=$MpesaReceiptNumber;
               $payment->ResultDesc=$ResultDesc;
               $payment->save();

          }else{
               $CheckoutRequestID=$response->Body->stkCallback->CheckoutRequestID;
               $ResultDesc=$response->Body->stkCallback->ResultDesc;
               $payment=Stkrequest::where('CheckoutRequestID',$CheckoutRequestID)->firstOrfail();

               $payment->ResultDesc=$ResultDesc;
               $payment->status='Failed';
               $payment->save();
          
          }

   }

   public function stkQuery(){
     $accessToken=$this->token();
     $BusinessShortCode=174379;
     $PassKey='bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919';
     $url='https://sandbox.safaricom.co.ke/mpesa/stkpushquery/v1/query';
         $Timestamp=Carbon::now()->format('YmdHis');
         $Password=base64_encode($BusinessShortCode.$PassKey.$Timestamp);
         $CheckoutRequestID='ws_CO_16102024111842912714274905';
 
         $response=Http::withToken($accessToken)->post($url,[
 
             'BusinessShortCode'=>$BusinessShortCode,
             'Timestamp'=>$Timestamp,
             'Password'=>$Password,
             'CheckoutRequestID'=>$CheckoutRequestID
         ]);
         return $response;
    }

    public function registerUrl(){
     $accessToken=$this->token();
     $url='https://sandbox.safaricom.co.ke/mpesa/c2b/v1/registerurl';
     $ShortCode=600984;
     $ResponseType='Completed';
     $ConfirmationURL='https://4939-102-219-208-44.ngrok-free.app/payments/confirmation';
    $ValidationURL='https://4939-102-219-208-44.ngrok-free.app/payments/validation';
 
    $response=Http::withToken($accessToken)->post($url,[
     'ShortCode'=>$ShortCode,
     'ResponseType'=>$ResponseType,
     'ConfirmationURL'=>$ConfirmationURL,
     'ValidationURL'=>$ValidationURL
    ]);
    return $response;
 
    }

    public function Simulate(){
          $accessToken = $this->token();
          $url='https://sandbox.safaricom.co.ke/mpesa/c2b/v1/simulate';
          $ShortCode=600984;
          $CommandID='CustomerPayBillOnline'; //CustomerBuyGoodsOnline
          $Amount=1;
          $Msisdn=254708374149;
          $BillRefNumber='00000';

          $response=Http::withToken($accessToken)->post($url,[
               'ShortCode'=>$ShortCode,
               'CommandID'=>$CommandID,
               'Amount'=>$Amount,
               'Msisdn'=>$Msisdn,
               'BillRefNumber'=>$BillRefNumber
          ]);

     return $response;

    }

    public function Validation(){
     $data=file_get_contents('php://input');
     Storage::disk('local')->put('validation.txt',$data);

     //validaton logic
     return response()->json([
          'ResultCode'=>0,
          'ResultDesc'=>'Accepted'
     ]);
     /*
     return response()->json([
          'ResultCode'=>'C2B00011',
          'ResultDesc'=>'Rejected'
     ])
     */
    }

    public function Confirmation(){
     $data=file_get_contents('php://input');
     Storage::disk('local')->put('confirmation.txt',$data);
     //save data to DB
     $response=json_decode($data);
     $TransactionType=$response->TransactionType;
     $TransID=$response->TransID;
     $TransTime=$response->TransTime;
     $TransAmount=$response->TransAmount;
     $BusinessShortCode=$response->BusinessShortCode;
     $BillRefNumber=$response->BillRefNumber;
     $InvoiceNumber=$response->InvoiceNumber;
     $OrgAccountBalance=$response->OrgAccountBalance;
     $ThirdPartyTransID=$response->ThirdPartyTransID;
     $MSISDN=$response->MSISDN;
     $FirstName=$response->FirstName;
     $MiddleName=$response->MiddleName;
     $LastName=$response->LastName;

     $c2b=new C2brequest;
     $c2b->TransactionType=$TransactionType;
     $c2b->TransID=$TransID;
     $c2b->TransTime=$TransTime;
     $c2b->TransAmount=$TransAmount;
     $c2b->BusinessShortCode=$BusinessShortCode;
     $c2b->BillRefNumber=$BillRefNumber;
     $c2b->InvoiceNumber=$InvoiceNumber;
     $c2b->OrgAccountBalance=$OrgAccountBalance;
     $c2b->ThirdPartyTransID=$ThirdPartyTransID;
     $c2b->MSISDN=$MSISDN;
     $c2b->FirstName=$FirstName;
     $c2b->MiddleName=$MiddleName;
     $c2b->LastName=$LastName;
     $c2b->save();

     return response()->json([
          'ResultCode'=>0,
          'ResultDesc'=>'Accepted'
     ]);

     public function b2cresult(){
          $data=file_get_contents('php://input');
          Storage::disk('local')->put('b2cresult.txt',$data);
     }

     public function b2ctimeout()
     {
         $data = file_get_contents('php://input');
         Storage::disk('local')->put('b2ctimeout.txt', $data);
     }

    public function qrcode(){
     $consumerKey=\config('safaricom.consumer_key');
     $consumerSecret=\config('safaricom.consumer_secret');
     $authUrl='https://api.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials';

     $request=Http::withBasicAuth($consumerKey,$consumerSecret)->get($authUrl);

     $accessToken=$request['access_token'];

     $MerchantName='ESKULI REVISION';
     $RefNo='gggsgsgg';
     $Amount=1;
     $TrxCode='PB'; //BG-buy goods till, WA-mpesa agent, SM-send money, SB-send to business
     $CPI=572555;

     $url='https://api.safaricom.co.ke/mpesa/b2c/v1/generate';
     $response=Http::withToken($accessToken)->post($url,[

          'MerchantName'=>$MerchantName,
          'RefNo'=>$RefNo,
          'Amount'=>$Amount,
          'TrxCode'=>$TrxCode,
          'CPI'=>$CPI
     ]);

     $data=$response['QRCode'];

     return view('welcome'->with('qrcode',$data));

     return $response;
    }
}