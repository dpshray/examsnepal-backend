<?php

namespace App\Http\Controllers;

use App\Models\PromoCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

class PromoCodeController extends Controller
{

    /**
     * Store a newly created resource in storage.
     *
     * @OA\Post(
     *     path="/verify-promo-code",
     *     operationId="verifyPromoCode",
     *     tags={"PromoCode"},
     *     summary="Api for verifying promo code",
     *     description="Api for verifying promo code.",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"promo_code"},
     *             @OA\Property(
     *                 property="promo_code",
     *                 type="string",
     *                 example="VISITNEPAL2026"
     *             )
     *         )
     *     ),
     * 
     * @OA\Response(
     *     response=200,
     *     description="Promo code information",
     *     @OA\JsonContent(
     *         @OA\Property(property="status", type="boolean", example=true),
     *         @OA\Property(
     *             property="data",
     *             type="object",
     *             @OA\Property(property="code", type="string", example="NEPALMEDIA25"),
     *             @OA\Property(property="discount_percent", type="string", example="9.00"),
     *             @OA\Property(
     *                 property="detail",
     *                 type="string",
     *                 example="Nostrum velit id ratione dolorem cumque alias. Voluptatem nulla eaque dolores laborum quae."
     *             )
     *         ),
     *         @OA\Property(property="message", type="string", example="Promo code information")
     *     )
     * )
     * )
     */
    public function checkPromoCodes(Request $request) {
        $form_data = $request->validate([
            'promo_code' => 'required'
        ]);
        $promo_code = PromoCode::select('code','discount_percent','detail')
                        ->where('status',1)
                        ->firstWhere('code', $form_data['promo_code']);
        if (empty($promo_code) || $form_data['promo_code'] !== $promo_code->code) {
            return Response::apiError('This promo code does not match/exists',null , 404);
        }
        return Response::apiSuccess('Promo code information', $promo_code);
    }
}
