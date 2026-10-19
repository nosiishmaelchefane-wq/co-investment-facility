<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class AccountSettingsController extends Controller
{
    public function index($id)
    {
        return view('settings.index', compact('id'));
    }
}