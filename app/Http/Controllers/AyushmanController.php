<?php
namespace App\Http\Controllers;

use App\Services\AbdmService;
use App\Services\AyushmanService;
use Illuminate\Http\Request;

class AyushmanController extends Controller
{
    protected $ayushmanService;
    protected $abdmService;


    public function __construct(AyushmanService $ayushmanService,AbdmService $abdmService)
    {
        $this->ayushmanService = $ayushmanService;
        $this->abdmService = $abdmService;
    }

    public function verifyCard(Request $request)
    {
        try {
            // $validated = $request->validate([
                //'abha_number' => 'required|string',
                //'abha_number' => '527613815535',
            // ]);
            //
             $validated['abha_number'] = '91-6660-0224-1311';
            // $abhaNumber = '91666002241311';
            // $result = $this->ayushmanService->verifyCard($abhaNumber);
    
            $response = $this->abdmService->verifyCard($validated);
            return response()->json($response);
        }
        catch (\Exception $e) {
            logger($e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
       
    }
}
