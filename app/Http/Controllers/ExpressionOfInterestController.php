<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ExpressionOfInterestController extends Controller
{
    public function index()
    {
        return view('expression-of-interest.index');
    }

    public function show(){
         return view('expression-of-interest.show');
    }
}
