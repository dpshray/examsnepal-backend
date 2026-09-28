<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RoleEnum;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Traits\PaginatorTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;

class AdminInstituteController extends Controller
{
    use PaginatorTrait;

    /**
     * Teachers/corporate accounts with their class, enrollment and exam counts.
     */
    public function index(Request $request)
    {
        $perPage = $request->query('per_page', 15);
        $search = $request->query('search');
        $status = $request->query('status'); // active | disabled

        $studentsEnrolled = DB::table('class_students')
            ->join('classes', 'classes.id', '=', 'class_students.class_id')
            ->whereColumn('classes.institute_id', 'users.id')
            ->whereNull('classes.deleted_at')
            ->selectRaw('COUNT(DISTINCT class_students.institute_student_id)');

        $institutes = User::query()
            ->whereHas('role', fn ($q) => $q->where('name', RoleEnum::CORPORATE->value))
            ->select('users.id', 'users.fullname', 'users.username', 'users.email', 'users.phone', 'users.org', 'users.slug', 'users.created_date', 'users.is_disabled', 'users.disabled_at')
            ->selectSub($studentsEnrolled, 'students_enrolled')
            ->selectSub(DB::table('corporate_exams')->whereColumn('corporate_id', 'users.id')->selectRaw('COUNT(*)'), 'external_exams_count')
            ->selectSub(DB::table('exams')->whereColumn('user_id', 'users.id')->selectRaw('COUNT(*)'), 'app_exams_count')
            ->withCount('classes')
            ->when($search, fn ($q) => $q->where(function ($q) use ($search) {
                $q->where('fullname', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('org', 'like', "%{$search}%");
            }))
            ->when($status === 'active', fn ($q) => $q->where('is_disabled', false))
            ->when($status === 'disabled', fn ($q) => $q->where('is_disabled', true))
            ->orderByDesc('id')
            ->paginate($perPage);

        $data = $this->setupPagination($institutes, fn ($items) => collect($items)->map(fn (User $user) => [
            'id' => $user->id,
            'name' => $user->org ?: $user->fullname,
            'fullname' => $user->fullname,
            'username' => $user->username,
            'email' => $user->email,
            'phone' => $user->phone,
            'slug' => $user->slug,
            'registered_at' => $user->created_date ?: null,
            'is_disabled' => (bool) $user->is_disabled,
            'disabled_at' => $user->disabled_at,
            'classes_count' => (int) $user->classes_count,
            'students_enrolled' => (int) $user->students_enrolled,
            'external_exams_count' => (int) $user->external_exams_count,
            'app_exams_count' => (int) $user->app_exams_count,
        ]))->data;

        return Response::apiSuccess('institutes list', $data);
    }

    public function toggleStatus(User $user)
    {
        if ($user->role?->name !== RoleEnum::CORPORATE->value) {
            return Response::apiError('Only teacher/corporate accounts can be disabled', null, 422);
        }

        $disabled = ! $user->is_disabled;
        $user->forceFill([
            'is_disabled' => $disabled,
            'disabled_at' => $disabled ? now() : null,
        ])->save();

        return Response::apiSuccess($disabled ? 'Account disabled' : 'Account enabled', [
            'id' => $user->id,
            'is_disabled' => $disabled,
        ]);
    }
}
