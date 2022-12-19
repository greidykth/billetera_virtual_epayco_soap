<?php

namespace App\Traits;


trait SoapResponseTrait
{
    protected function responseWhitData($success, $message, $codError = 00, $data = null)
    {
        return (object) [
            "success" => $success,
            "cod_error" => $codError,
            "message_error" => $message,
            "data" => (object)$data];
    }
    
    protected function responseNoData($success, $message, $codError = 00)
    {
        return (object) [
            "success" => $success,
            "cod_error" => $codError,
            "message_error" => $message];
    }
}