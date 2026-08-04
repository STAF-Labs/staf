<?php

namespace App\Http\Controllers\Api\Admin\Licence;

use App\Http\Controllers\Controller;
use App\Services\Admin\Licence\SpdxLicenceCatalog;
use Illuminate\Http\JsonResponse;

class LicenceController extends Controller
{
    public function index(SpdxLicenceCatalog $catalog): JsonResponse
    {
        $licences = $catalog->all();

        return response()->json([
            'data' => $licences,
            'total' => count($licences),
        ]);
    }
}
