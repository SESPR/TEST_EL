<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class WelcomeController extends Controller
{
    public function index()
    {
        $response = Http::withoutVerifying()->get('http://109.73.206.144:6969/api/stocks', [
            'dateFrom' => '2026-03-29',
            //'dateTo' => '2026-03-29',
            'page' => 1,
            'key' => 'E6kUTYrYwZq2tN4QEtyzsbEBk3ie'
        ]);
        //dd($response->json());
        return view('welcome');
    }
}
