<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class RolesPermissionsController extends Controller
{
    public function index(){
        return view('roles.index');
    }
}
