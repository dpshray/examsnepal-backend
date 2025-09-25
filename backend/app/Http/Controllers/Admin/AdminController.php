<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminUpdateSubRequest;
use App\Http\Resources\Subscription\SubscriptionTypeResource;
use App\Models\StudentProfile;
use App\Models\Subscriber;
use App\Models\SubscriptionType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

class AdminController extends Controller
{
    //
    function addorupdate(AdminUpdateSubRequest $request ,$studentId)
    {
        $validated=$request->validated();
         $subscriber = Subscriber::updateOrCreate(
            [
                'student_profile_id' => $studentId,
            ],
            [
                'subscription_type_id' =>$validated['subscription_type_id'],
                'price' => $validated['price']??0.00,
                'paid' => $validated['paid']??0.00,
                'paid_in_paisa' => $validated['paid'] * 100??0.00,
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'],
                'transaction_id' => 'TXN' . rand(10000, 99999),
                'payment_status' => 'PAYMENT_SUCCESS', // manual add by admin
                'status' => 1,
                'data' => json_encode(['remark' => $request->remark]),
                'subscribed_at' => now(),
            ]
        );

        return response()->json([
            'message' => 'Subscription added updated successfully.',
            'subscriber' => $subscriber
        ], 200);
    }
    public function subtype(StudentProfile $student)
    {
        $rows = SubscriptionType::select('id as subscription_type_id','duration', 'price')
            ->where('status', 1)
            ->where('exam_type_id',$student->exam_type_id)
            ->get();
        $data = SubscriptionTypeResource::collection($rows);
        return Response::apiSuccess('Active package list', $data);
    }
}
