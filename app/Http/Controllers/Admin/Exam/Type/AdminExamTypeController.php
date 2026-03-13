<?php

namespace App\Http\Controllers\Admin\Exam\Type;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\Exam\Type\AdminExamTypeResource;
use App\Models\ExamType;
use App\Traits\PaginatorTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

class AdminExamTypeController extends Controller
{
    //
    use PaginatorTrait;
    function index(Request $request)
    {
        $per_page = $request->query('per_page', 10);
        $search = $request->query('search');
        $examtype = ExamType::when($search, fn($qry) => $qry->where('name', 'like', '%' . $search . '%'))
            ->orderBy('id', 'DESC')
            ->paginate($per_page);
        $data = $this->setupPagination($examtype, AdminExamTypeResource::class);
        return Response::apiSuccess("Exam types", $data);
    }
    function show(ExamType $examtype)
    {
        $data = new AdminExamTypeResource($examtype);
        return Response::apiSuccess("Exam type", $data);
    }
    function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required',
            'is_active' => 'required',
        ]);
        $examtype = ExamType::create($data);
        return Response::apiSuccess("Exam type created successfully");
    }
    function update(Request $request, ExamType $examtype)
    {
        $data = $request->validate([
            'name' => 'required',
            'is_active' => 'required',
        ]);
        $examtype->update($data);
        return Response::apiSuccess("Exam type updated successfully");
    }
    function destroy(ExamType $examtype)
    {
        $examtype->delete();
        return Response::apiSuccess("Exam type deleted successfully");
    }
}
