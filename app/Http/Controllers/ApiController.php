<?php

namespace App\Http\Controllers;

use App\Domain\Model\AccessToken;
use App\Domain\Model\LicenseType;
use App\Domain\Model\Merchant;
use App\Domain\Model\Order;
use App\Domain\Model\Tenant;
use App\Jobs\ProcessMessages;
use DateTime;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ApiController extends Controller
{
    public function __construct()
    {

    }

    public function provisionTenant(Request $request){
        $tenantToRegisterArray = json_decode($request->getContent(), true);

        $tenant =  $tenantToRegisterArray['data']['tenant'];
        $adminuser =  $tenantToRegisterArray['data']['user'];


        DB::beginTransaction();

        try{
            $tenantData = [
                'id' =>$tenant['id'],
                'name' => $tenant['name'],
                'city' => $tenant['city'],
                'location' => $tenant['location'],
                'region' => $tenant['region'],
                'country' => $tenant['country'],
                'phone_number' => $tenant['phone_number'],
                'website' => $tenant['website'],
                'address' => $tenant['address'],
                'active' => $tenant['active'],
                'owner' => $adminuser['id'],
                'taxpayer_number' => $tenant['taxpayer_number'],
                'number_trade_register' => $tenant['number_trade_register'],
                'network' => $tenant['network'],
            ];

            $tenant = Tenant::create($tenantData);

            $merchantData = [
                'id' => $adminuser['id'],
                'tenant_id' => $adminuser['tenant_id'],
                'type' => $adminuser['type'],
                'full_name' => $adminuser['full_name'],
                'username' => $adminuser['username'],
                'email' => $adminuser['email'],
                'phone_number' => $adminuser['phone_number'],
                'location' => $tenant['location'],
                'region' => $adminuser['region'],
                'country' => $adminuser['country'],
                'active' => $adminuser['active'],
                'network' => $adminuser['network'],
            ];
            Merchant::create($merchantData);
        }catch (\Exception $e){

            DB::rollBack();

            return response(GlobalResultHandler::buildFaillureReasonArray('Faild to save Tenant Provision : ' .$e->getMessage()), 200);

        }
        DB::commit();

        return response(GlobalResultHandler::buildSuccesResponseArray('Tenant Provision saved successfully'), 200);

    }

    public function saveAccessToken(Request $request){
        $accessTokenArray = json_decode(json_decode($request->getContent(), true), true);
        $token_type = $accessTokenArray['token_type'];
        $expires_in = $accessTokenArray['expires_in'];
        $access_token = $accessTokenArray['access_token'];
        //$refresh_token = $accessTokenArray['refresh_token'];

        $tokendata = explode('.', $access_token);
        $jsonAccessTokendata = json_decode(base64_decode($tokendata[1]), true);
        $user_id = $jsonAccessTokendata['sub'];
        $client_id = $jsonAccessTokendata['aud'];
        $jti = $jsonAccessTokendata['jti'];
        $scopes = json_encode($jsonAccessTokendata['scopes']);
        $expires_at = $jsonAccessTokendata['exp'];
        AccessToken::create([
            'id' => $jti,
            'user_id' => $user_id,
            'client_id' => $client_id,
            'name' => '',
            'scopes' => $scopes,
            'revoked' => false,
            'token_type' => $token_type,
            'expires_in' => $expires_in,
            'expires_at' => new DateTime("@$expires_at")
        ]);

        return response(GlobalResultHandler::buildSuccesResponseArray('Access Token saved successfully'), 200);
    }

    public function logout(Request $request){
        $jtiArray = json_decode($request->getContent(), true);
        $jti = $jtiArray['jti'];
        $accessToken = AccessToken::where('id', $jti)->first();
        if($accessToken === null){
            return response(GlobalResultHandler::buildFaillureReasonArray('Token with jti: ' .$jti.' not found'), 200);
        }
        $accessToken->revoked = true;
        $accessToken->save();

        return response(GlobalResultHandler::buildSuccesResponseArray('Access Token revoked successfully'), 200);
    }

    public function createOrder(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'tenant_id' => 'required|exists:tenants,id',
            'merchant_id' => 'required|exists:merchants,id',
            'license_type_id' => 'required|exists:license_types,id',
            'amount' => 'required|numeric',
            'discount' => 'required|numeric:between:0,99.99',
        ],
        [
            'tenant_id.required' => __("Le tenant est obligatoire"),
            'tenant_id.exists' => __("Tenant inconnu"),
            'merchant_id.required' => __("Le marchand est obligatoire"),
            'merchant_id.exists' => __("Merchant inconnu"),
            'license_type_id.required' => __("Le type de licence est obligatoire"),
            'license_type_id.exists' => __("Type de licence inconnu"),
            'amount.required' => __("Le montant est obligatoire"),
            'amount.numeric' => __("Le montant est un nombre"),
            'discount.required' => __("La reduction est obligatoire"),
            'discount.numeric' => __("La reduction est un nombre"),
            'discount.between' => __("La reduction est un nombre compris entre 0 et 99.99"),
        ]);

        if ($validator->fails()) {
            return response(GlobalResultHandler::buildFaillureReasonArray($validator->errors()->first()), 202);
        }
        $licenseType = LicenseType::where('id', $request->get('license_type_id'))->first();
        $amount = doubleval($request->get('amount'));
        $discount = doubleval($request->get('discount'));
        Order::create([
            'id' => Str::uuid()->toString(),
            'tenant_id' => $request->get('tenant_id'),
            'merchant_id' => $request->get('merchant_id'),
            'license_type_id' => $request->get('license_type_id'),
            'amount' => $amount,
            'discount' => $discount,
            'final_amount' => $amount * (1 - $discount),
            'placed_at' => null,
            'status' => Order::PENDING,
            'paid_at' => null,
            'cancelled_at' => null,
            'active' => true,
            'items' => json_encode([$licenseType]),
        ]);

        return response(GlobalResultHandler::buildSuccesResponseArray('Order created successfully'), 202);
    }

    public function updateOrderOrderPlaced(Request $request, string $order_id)
    {
        $order = Order::where('id', $order_id)->first();
        if($order === null){
            return response(GlobalResultHandler::buildFaillureReasonArray('Order not found'), 200);
        }
        $order->placed_at = Carbon::now();
        $order->status = Order::PLACED;
        $order->save();
        ProcessMessages::dispatch(
            env('LICENSE_ORDER_PLACED_EXCHANGE'),
            env('RABBIT_MQ_EXCHANGE_TYPE', 'fanout'),
            json_encode($order)
        );
        return response(GlobalResultHandler::buildSuccesResponseArray('Order placed successfully'), 200);
    }

    public function updateOrderOrderPaid(Request $request, string $order_id)
    {
        $order = Order::where('id', $order_id)->first();
        if($order === null){
            return response(GlobalResultHandler::buildFaillureReasonArray('Order not found'), 200);
        }
        $order->paid_at = Carbon::now();
        $order->status = Order::COMPLETED;
        $order->save();
        ProcessMessages::dispatch(
            env('LICENSE_ORDER_COMPLETED_EXCHANGE'),
            env('RABBIT_MQ_EXCHANGE_TYPE', 'fanout'),
            json_encode(['id'=>$order->id, 'status'=>$order->status])
        );
        return response(GlobalResultHandler::buildSuccesResponseArray('Order placed successfully'), 200);
    }

    public function updateOrderOrderCancelled(Request $request, string $order_id)
    {
        $order = Order::where('id', $order_id)->first();
        if($order === null){
            return response(GlobalResultHandler::buildFaillureReasonArray('Order not found'), 200);
        }
        if ($order->status === Order::COMPLETED) {
            return response(GlobalResultHandler::buildFaillureReasonArray('Completed order can not be cancelled'), 200);
        }
        $order->cancelled_at = Carbon::now();
        $order->status = Order::CANCELLED;
        $order->save();
        ProcessMessages::dispatch(
            env('LICENSE_ORDER_CANCELLED_EXCHANGE'),
            env('RABBIT_MQ_EXCHANGE_TYPE', 'fanout'),
            json_encode(['id'=>$order->id, 'status'=>$order->status])
        );
        return response(GlobalResultHandler::buildSuccesResponseArray('Order cancelled successfully'), 200);
    }
}
