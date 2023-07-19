<?php

namespace App;

use Workerman\MySQL\Connection as DB;

class Repository
{
    public function __construct(private DB $db)
    {
    }
}
