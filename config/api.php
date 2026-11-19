<?php

/*
| API settings.
|   cif  — the CAFI Co-Investment platform calling GET /api/cif/enterprise-match
|          token  shared bearer token (same value as COLLAB_API_TOKEN on the Co-Investment platform)
*/
return [
    'cif' => [
        'token' => env('CIF_API_TOKEN'),
    ],
];