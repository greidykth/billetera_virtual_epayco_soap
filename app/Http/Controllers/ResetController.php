<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Wallet;
use App\Traits\SoapResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class ResetController
{
    use SoapResponseTrait;

   /**
     * Reset the tables, returns ok
     *
     * @return string
     */

     public function reset()//Método para resetear la aplicación
     {
        Artisan::call('migrate:fresh');
        return 'OK';
     }
    
}
