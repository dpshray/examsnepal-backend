<?php

namespace App\Http\Controllers;

use App\Http\Resources\Admin\PromoCode\AdminPromoCodeResource;
use App\Models\PromoCode;
use App\Traits\NewPaginationTrait;
use App\Traits\PaginatorTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

class PromoCodeController extends Controller
{
    use NewPaginationTrait;
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
    public function checkPromoCodes(Request $request)
    {
        $form_data = $request->validate([
            'promo_code' => 'required'
        ]);
        $promo_code = PromoCode::select('code', 'discount_percent', 'detail')
            ->where('status', 1)
            ->firstWhere('code', $form_data['promo_code']);
        if (empty($promo_code) || $form_data['promo_code'] !== $promo_code->code) {
            return Response::apiError('This promo code does not match/exists', null, 404);
        }
        return Response::apiSuccess('Promo code information', $promo_code);
    }
    /**
     * @OA\Get(
     *      path="/admin/promo-code",
     *      operationId="getPromoCodes",
     *      security={{"bearerAuth": {}}},
     *      tags={"Admin PromoCode"},
     *      summary="Api for getting promo codes",
     *      description="Api for getting promo codes.",
     *      @OA\Response(
     *          response=200,
     *          description="Promo codes fetched successfully",
     *          @OA\JsonContent(
     *              @OA\Property(property="status", type="boolean", example=true),
     *              @OA\Property(
     *                  property="data",
     *                  type="object",
     *                  @OA\Property(property="data", type="array", @OA\Items(ref="#components/schemas/PromoCode")),
     *                  @OA\Property(property="meta", type="object", ref="#components/schemas/PaginationMeta")
     *              ),
     *              @OA\Property(property="message", type="string", example="Promo codes fetched successfully")
     *          )
     *      )
     * )
     */
    function index(Request $request)
    {
        $per_page = $request->per_page ?? 10;
        $search = $request->search ?? '';
        $promo_codes = PromoCode::when($search, function ($query) use ($search) {
            $query->where('code', 'like', '%' . $search . '%');
        })->paginate($per_page);
        $data = $this->makePaginationResponse($promo_codes, fn($item) => AdminPromoCodeResource::collection($item))->data;
        return Response::apiSuccess('Promo codes fetched', $data);
    }
    /**
     * @OA\Get(
     *      path="/admin/promo-code/{id}",
     *      operationId="getPromoCode",
     *      security={{"bearerAuth": {}}},
     *      tags={"Admin PromoCode"},
     *      summary="Api for getting promo code",
     *      description="Api for getting promo code.",
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          required=true,
     *          @OA\Schema(
     *              type="integer"
     *          )
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Promo code fetched successfully",
     *          @OA\JsonContent(
     *              @OA\Property(property="status", type="boolean", example=true),
     *              @OA\Property(
     *                  property="data",
     *                  type="object",
     *                  @OA\Property(property="data", type="array", @OA\Items(ref="#components/schemas/PromoCode")),
     *                  @OA\Property(property="meta", type="object", ref="#components/schemas/PaginationMeta")
     *              ),
     *              @OA\Property(property="message", type="string", example="Promo code fetched successfully")
     *          )
     *      )
     * )
     */
    function show($id)
    {
        $promo_code = PromoCode::find($id);
        return Response::apiSuccess('Promo code fetched', new AdminPromoCodeResource($promo_code));
    }
    /**
     * @OA\Post(
     *      path="/admin/promo-code",
     *      operationId="storePromoCode",
     *      security={{"bearerAuth": {}}},
     *      tags={"Admin PromoCode"},
     *      summary="Api for storing promo code",
     *      description="Api for storing promo code.",
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              @OA\Property(property="code", type="string", example="NEPALMEDIA25"),
     *              @OA\Property(property="discount_percent", type="string", example="9.00"),
     *              @OA\Property(property="detail", type="string", example="Nostrum velit id ratione dolorem cumque alias. Voluptatem nulla eaque dolores laborum quae."),
     *              @OA\Property(property="status", type="boolean", example=true)
     *          )
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Promo code stored successfully",
     *          @OA\JsonContent(
     *              @OA\Property(property="status", type="boolean", example=true),
     *              @OA\Property(
     *                  property="data",
     *                  type="object",
     *                  @OA\Property(property="data", type="array", @OA\Items(ref="#components/schemas/PromoCode")),
     *                  @OA\Property(property="meta", type="object", ref="#components/schemas/PaginationMeta")
     *              ),
     *              @OA\Property(property="message", type="string", example="Promo code stored successfully")
     *          )
     *      )
     * )
     */
    function store(Request $request)
    {
        $form_data = $request->validate([
            'code' => 'required',
            'discount_percent' => 'required',
            'detail' => 'required',
            'status' => 'required',
        ]);
        $promo_code = PromoCode::create($form_data);
        return Response::apiSuccess('Promo code created', $promo_code);
    }
    /**
     * @OA\Put(
     *      path="/admin/promo-code/{id}",
     *      operationId="updatePromoCode",
     *      security={{"bearerAuth": {}}},
     *      tags={"Admin PromoCode"},
     *      summary="Api for updating promo code",
     *      description="Api for updating promo code.",
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          required=true,
     *          @OA\Schema(
     *              type="integer"
     *          )
     *      ),
     *     @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              @OA\Property(property="code", type="string", example="NEPALMEDIA25"),
     *              @OA\Property(property="discount_percent", type="string", example="9.00"),
     *              @OA\Property(property="detail", type="string", example="Nostrum velit id ratione dolorem cumque alias. Voluptatem nulla eaque dolores laborum quae."),
     *              @OA\Property(property="status", type="boolean", example=true)
     *          )
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Promo code updated successfully",
     *          @OA\JsonContent(
     *              @OA\Property(property="status", type="boolean", example=true),
     *              @OA\Property(
     *                  property="data",
     *                  type="object",
     *                  @OA\Property(property="data", type="array", @OA\Items(ref="#components/schemas/PromoCode")),
     *                  @OA\Property(property="meta", type="object", ref="#components/schemas/PaginationMeta")
     *              ),
     *              @OA\Property(property="message", type="string", example="Promo code updated successfully")
     *          )
     *      )
     * )
     */
    function update(Request $request, $id)
    {
        $form_data = $request->validate([
            'code' => 'required',
            'discount_percent' => 'required',
            'detail' => 'required',
            'status' => 'required',
        ]);
        $promo_code = PromoCode::find($id);
        $promo_code->update($form_data);
        return Response::apiSuccess('Promo code updated', $promo_code);
    }
    /**
     * @OA\Delete(
     *      path="/admin/promo-code/{id}",
     *      operationId="deletePromoCode",
     *      security={{"bearerAuth": {}}},
     *      tags={"Admin PromoCode"},
     *      summary="Api for deleting promo code",
     *      description="Api for deleting promo code.",
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          required=true,
     *          @OA\Schema(
     *              type="integer"
     *          )
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Promo code deleted successfully",
     *          @OA\JsonContent(
     *              @OA\Property(property="status", type="boolean", example=true),
     *              @OA\Property(
     *                  property="data",
     *                  type="object",
     *                  @OA\Property(property="data", type="array", @OA\Items(ref="#components/schemas/PromoCode")),
     *                  @OA\Property(property="meta", type="object", ref="#components/schemas/PaginationMeta")
     *              ),
     *              @OA\Property(property="message", type="string", example="Promo code deleted successfully")
     *          )
     *      )
     * )
     */
    function destroy($id)
    {
        $promo_code = PromoCode::find($id);
        $promo_code->delete();
        return Response::apiSuccess('Promo code deleted', null);
    }
}
