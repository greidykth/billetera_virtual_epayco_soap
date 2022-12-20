<?php

namespace App\Http\Controllers;

use App\Mail\MailConfirmationMailable;
use App\Models\Client;
use App\Models\Transaction;
use App\Traits\SoapResponseTrait;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Laravel\Sanctum\PersonalAccessToken;

class TransactionController
{
    use SoapResponseTrait;

    /**
     * Create an instance of a transaction, require dni, cellphone
     * 
     * @param int $dni
     * @param int $cellphone
     * @return object
     */

    public function checkBalance($dni, $cellphone)
    {
        $inputs = [
            'dni' => $dni,
            'cellphone' => $cellphone,
        ];

        $rules = [
            'dni' => "required",
            'cellphone' => "required",
        ];

        try {
            $validation = Validator::make($inputs, $rules);


            if ($validation->fails()) {
                return $this->responseWhitData(
                    false,
                    "Datos faltantes. Uno o varios campos son requeridos",
                    422,
                    (object)['error' => $validation->errors()->getMessages()]
                );
            }

            $client = Client::where('dni', $dni)->where('cellphone', $cellphone)->first();

            if (is_null($client)) {
                return $this->responseNoData(
                    false,
                    "Las credenciales no son correctas",
                    401
                );
            }

            $wallet = $client->wallet;
            if (is_null($wallet)) {
                return $this->responseNoData(
                    false,
                    'El cliente no posee una billetera virtual',
                    401
                );
            }

            $transaction = new Transaction();
            $transaction->type = 'check_balance';
            $transaction->status = 'executed';
            $transaction->client_executer_id = $client->id;
            $transaction->wallet_id = $wallet->id;
            $transaction->save();

            return $this->responseWhitData(
                true,
                "Consulta de saldo realizada satisfactoriamente",
                00,
                (object)[
                    'balance' => $wallet->balance,
                ],
            );
        } catch (\Throwable $th) {
            DB::rollback();

            return $this->responseWhitData(
                false,
                "Se produjo un error durante la consulta",
                422,
                ['error' => $th]
            );
        }
    }

    /**
     * Create an instance of a transaction, require dni, cellphone and value
     * 
     * @param int $dni
     * @param int $cellphone
     * @param int $value
     * @return object
     */

    public function depositWallet($dni, $cellphone, $value)
    {
        $inputs = [
            'dni' => $dni,
            'cellphone' => $cellphone,
            'value' => $value,
        ];

        $rules = [
            'dni' => "required",
            'cellphone' => "required",
            'value' => "required",
        ];

        try {
            $validation = Validator::make($inputs, $rules);

            if ($validation->fails()) {
                return $this->responseWhitData(
                    false,
                    "Datos faltantes. Uno o varios campos son requeridos",
                    422,
                    (object)['error' => $validation->errors()->getMessages()]
                );
            }

            $client = Client::where('dni', $dni)->where('cellphone', $cellphone)->first();

            if (is_null($client)) {
                return $this->responseNoData(
                    false,
                    "Las credenciales no son correctas",
                    401
                );
            }

            $wallet = $client->wallet;
            if (is_null($wallet)) {
                return $this->responseNoData(
                    false,
                    'El cliente no posee una billetera virtual',
                    401
                );
            }
            DB::beginTransaction();
            $transaction = new Transaction();
            $transaction->type = 'deposit';
            $transaction->status = 'executed';
            $transaction->value = $value;
            $transaction->client_executer_id = $client->id;
            $transaction->client_receptor_id =  $client->id;
            $transaction->wallet_id = $wallet->id;
            $transaction->save();

            $wallet->balance += $value;
            $wallet->save();
            DB::commit();

            return $this->responseWhitData(
                true,
                "Recarga de saldo realizada satisfactoriamente",
                00,
                (object)[
                    'value' => $transaction->value,
                    'balance' => $wallet->balance,
                ],
            );
        } catch (\Throwable $th) {
            DB::rollback();

            return $this->responseWhitData(
                false,
                "Se produjo un error durante la recarga",
                422,
                ['error' => $th]
            );
        }
    }

    /**
     * Pay a purchase, require dni, cellphone, value and token
     * 
     * @param int $dni
     * @param int $cellphone
     * @param int $value
     * @param string $token
     * @return object
     */

    public function payPurchase($dni, $cellphone, $value, $token)
    {
        $inputs = [
            'dni' => $dni,
            'cellphone' => $cellphone,
            'value' => $value,
            'token' => $token,
        ];

        $rules = [
            'dni' => "required",
            'cellphone' => "required",
            'value' => "required",
            'token' => "required",
        ];

        $validation = Validator::make($inputs, $rules);

        if ($validation->fails()) {
            return $this->responseWhitData(
                false,
                "Datos faltantes. Uno o varios campos son requeridos",
                422,
                (object)['error' => $validation->errors()->getMessages()]
            );
        }

        $tokenClient = PersonalAccessToken::findToken($token);
        if (is_null($tokenClient)) {
            return $this->responseNoData(
                false,
                "Las credenciales del cliente pagador no son correctas",
                401
            );
        }

        $clientPayer = Client::find($tokenClient->tokenable_id);
        if (is_null($clientPayer)) {
            return $this->responseNoData(
                false,
                "Las credenciales del cliente pagador no son correctas",
                401
            );
        }

        if ($clientPayer->wallet->balance < $value) {
            return $this->responseNoData(
                false,
                'El saldo disponible es insuficiente para pagar',
                401
            );
        }

        $clientReceptor = Client::where('dni', $dni)->where('cellphone', $cellphone)->first();
        if (is_null($clientReceptor)) {
            return $this->responseNoData(
                false,
                'Las credenciales del cliente receptor no son correctas',
                401
            );
        }
        
        //actualizar estado de transacciones que han quedado abandonadas del mismo pagador
        $transactionsAbandonedPayer = Transaction::where('status', 'processing')
        ->where('wallet_id', $clientReceptor->wallet->id)
        ->where('created_at', '<', Carbon::now())
        ->update(['status' => 'failed']);
        
        //actualizar estado de transacciones que han quedado abandonadas del mismo receptor
        $transactionsAbandonedReceptor = Transaction::where('status', 'processing')
        ->where('wallet_id', $clientPayer->wallet->id)
        ->where('created_at', '<', Carbon::now())
        ->update(['status' => 'failed']);
        
        $numbers = '0123456789';

        try {
            DB::beginTransaction();
            //Crear transacción del cliente que paga
            $transactionPayer = new Transaction();
            $transactionPayer->value = abs($value);
            $transactionPayer->type = 'pay';
            $transactionPayer->wallet_id = $clientPayer->wallet->id;
            $transactionPayer->status = 'processing';
            $transactionPayer->client_executer_id =  $clientPayer->id;
            $transactionPayer->client_receptor_id =  $clientReceptor->id;
            $transactionPayer->token_client =  $token;
            $transactionPayer->token_confirmation = substr(str_shuffle($numbers), 0, 6);
            $transactionPayer->save();

            //Crear transacción del cliente que recibe
            $transactionReceptor = new Transaction();
            $transactionReceptor->value = abs($value);
            $transactionReceptor->type = 'deposit';
            $transactionReceptor->wallet_id = $clientReceptor->wallet->id;
            $transactionReceptor->status = 'processing';
            $transactionReceptor->client_executer_id =  $clientPayer->id;
            $transactionReceptor->client_receptor_id =  $clientReceptor->id;
            $transactionReceptor->token_client =  $token;
            $transactionReceptor->token_confirmation = $transactionPayer->token_confirmation;
            $transactionReceptor->save();

            Mail::to($clientPayer->email)->send(new MailConfirmationMailable($transactionPayer->token_confirmation));

            DB::commit();
            return $this->responseWhitData(
                true,
                'Solicitud de pago realizada satisfactoriamente. Se ha enviado un token de confirmación a su email',
                00,
                ['token_confirmacion' => $transactionPayer->token_confirmation] //se retorna el token de confirmacion solo para fines de testing
            );
        } catch (\Throwable $th) {
            DB::rollback();
            return $this->responseWhitData(
                false,
                "Se produjo un error durante el pago",
                422,
                ['error' => $th]
            );
        }
    }

    /**
     * Pay confirmation, require token and token_confirmation
     * 
     * @param string $token
     * @param string $tokenConfirmation
     * @return object
     */

     public function payConfirmation($token, $tokenConfirmation)
     {
        $inputs = [
            'tokenConfirmation' => $tokenConfirmation,
            'token' => $token,
        ];

        $rules = [
            'tokenConfirmation' => "required",
            'token' => "required",
        ];

        $validation = Validator::make($inputs, $rules);

        if ($validation->fails()) {
            return $this->responseWhitData(
                false,
                "Datos faltantes. Uno o varios campos son requeridos",
                422,
                (object)['error' => $validation->errors()->getMessages()]
            );
        }

        $tokenClient = PersonalAccessToken::findToken($token);
        
        if (is_null($tokenClient)) {
            return $this->responseNoData(
                false,
                "Las credenciales del cliente pagador no son correctas",
                401
            );
        }

        try {
            $client_payer = Client::find($tokenClient->tokenable_id);

            if (is_null($client_payer)) {
                return $this->responseNoData(
                    false,
                    'Cliente no encontrado',
                    401
                );
            }

            $transaction = Transaction::where("status", "processing")
                ->where("token_confirmation", $tokenConfirmation)
                ->where("wallet_id", $client_payer->wallet->id)
                ->where("token_client", $token)
                ->first();

            if (is_null($transaction)) {
                //Si falla la verificación, se cancelan las transacciones
                $transactionsFailed = Transaction::where('status', 'processing')
                    ->where('created_at', '<', Carbon::now())
                    ->update(['status' => 'failed']);

                return $this->responseNoData(
                    false,
                    'Falló la confirmación. Transacción cancelada',
                    401
                );
            }

            $client_receptor = Client::find($transaction->client_receptor_id);
            if (is_null($client_receptor)) {
                return $this->responseNoData(
                    false,
                    'Cliente receptor no fue encontrado',
                    401
                );
            }

            DB::beginTransaction();

            $walletPayer = $client_payer->wallet;
            $walletReceptor = $client_receptor->wallet;

            //debitar pago al cliente que paga
            $walletPayer->balance -= abs($transaction->value);
            $walletPayer->save();

            //cargar pago al cliente receptor
            $walletReceptor->balance += abs($transaction->value);
            $walletReceptor->save();

            $transaction->status = 'executed';
            $transaction->save();

            $transactionReceptor = Transaction::where('status', 'processing')
                ->where("token_confirmation", $tokenConfirmation)
                ->where("wallet_id", $walletReceptor->id)->first();

            $transactionReceptor->status = 'executed';
            $transactionReceptor->save();

            DB::commit();
            return $this->responseWhitData(
                true,
                'El pago se realizó satisfactoriamente',
                00,
                ['saldo_disponible' => $walletPayer->balance],
            );
        } catch (\Throwable $th) {

            DB::rollback();
            return $this->responseWhitData(
                false,
                "Falló la confirmación del pago",
                422,
                ['error' => $th]
            );
        }
     }
}
