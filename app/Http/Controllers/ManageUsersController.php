<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ManageUsersController extends Controller
{
    public function index(){
        return view('users.index');
    }
}
