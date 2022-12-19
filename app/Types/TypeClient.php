<?php

namespace App\Types;


class TypeClient
{
    /**
     * @var int
     */
    public $id;
    /**
     * @var int
     */
    public $dni;

    /**
     * @var string
     */
    public $name;

    /**
     * @var string
     */
    public $email;

    /**
     * @var int
     */
    public $cellphone;


    /**
     * Client constructor.
     *
     * @param int $id
     * @param int $dni
     * @param string $name
     * @param string $email
     * @param int $cellphone
     */
    public function __construct($client)
    {
        $this->id = $client->id;
        $this->dni = $client->dni;
        $this->name = $client->name;
        $this->email = $client->email;
        $this->cellphone = $client->cellphone;
    }

}