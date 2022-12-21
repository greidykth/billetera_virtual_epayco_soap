<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Wallet;
use App\Traits\SoapResponseTrait;
use App\Types\TypeClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class ClientController
{
    use SoapResponseTrait;

    /**
     * Create an instance of client, require dni, name, email, cellphone
     * 
     * @param int $dni
     * @param string $name
     * @param string $email
     * @param int $cellphone
     * @return object
     */

    public function registerClient($dni, $name, $email, $cellphone)
    {
        $inputs = [
            'dni' => $dni,
            'name' => $name,
            'email' => $email,
            'cellphone' => $cellphone,
        ];

        $rules = [
            'dni' => "required|unique:clients|min_digits:8",
            'name' => "required",
            'email' => "required|unique:clients",
            'cellphone' => "required|unique:clients|min_digits:8",
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

            $client = new Client();
            $client->name = $name;
            $client->dni = $dni;
            $client->password = Hash::make($dni);
            $client->email = $email;
            $client->cellphone = $cellphone;
            $client->save();
            $token = $client->createToken('auth_token')->plainTextToken;

            $wallet = new Wallet();
            $wallet->balance = 0;
            $wallet->client_id = $client->id;
            $wallet->save();

            return $this->responseWhitData(
                true,
                "Cliente registrado satisfactoriamente",
                "00",
                [
                    'token' => $token,
                    'client' => new TypeClient($client),
                    'saldo' => $client->wallet->balance,
                ]
            );
        } catch (\Throwable $th) {
            return $this->responseWhitData(
                false,
                "Algo ha fallado durante el registro del cliente",
                422,
                ['error' => $th]
            );
        }
    }

    /**
     * Login, require dni and cellphone
     * 
     * @param int $dni
     * @param int $cellphone
     * @return object
     */

    public function login($dni, $cellphone)
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
                    "Las credenciales proporcionadas son inválidas",
                    401
                );
            }
            $token = $client->createToken('auth_token')->plainTextToken;

            return $this->responseWhitData(
                true,
                "Cliente autenticado. Bienvenido(a) a tu billetera virtual",
                "00",
                (object)[
                    'token' => $token,
                    'client' => new TypeClient($client),
                    'saldo' => $client->wallet->balance
                ],
            );
        } catch (\Throwable $th) {
            return $this->responseWhitData(
                false,
                "Algo ha fallado durante el login",
                422,
                ['error' => $th]
            );
        }
    }
}
