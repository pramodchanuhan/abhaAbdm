<?php
namespace App\Http\Controllers;

use App\Services\AyushmanService;
use Illuminate\Http\Request;

class AyushmanController extends Controller
{
    protected $ayushmanService;

    public function __construct(AyushmanService $ayushmanService)
    {
        $this->ayushmanService = $ayushmanService;
    }

    public function verifyCard(Request $request)
    {
        $validated = $request->validate([
            //'abha_number' => 'required|string',
            'abha_number' => '91666002241311',
        ]);
        $abhaNumber = '91666002241311';
        $result = $this->ayushmanService->verifyCard($abhaNumber);

        return response()->json($result);
    }
}
