<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Transaction;
use App\Traits\SoapResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

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
                     "Datos faltantes o repetidos. Uno o varios campos son requeridos",
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
                    "Datos faltantes o repetidos. Uno o varios campos son requeridos",
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
}
