<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesTenantRequests;

abstract class Controller
{
    use AuthorizesTenantRequests;
}
