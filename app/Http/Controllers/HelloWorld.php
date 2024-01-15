<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

class HelloWorld extends Controller
{
    public function __invoke(Request $request)
    {
        $name = Str::ucfirst($request->query('name') ?? 'World');
        $uuid = Str::uuid();

        return "Hello, {$name}! #{$uuid}";
    }
}
