<?php

namespace App\Http\Controllers\Participant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Participant\ParticipantRequest;
use App\Models\Participant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Response;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ParticipantController extends Controller
{
    public function store_from_excel(Request $request)
    {
        //Import from Excel
        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv',
        ]);

        $file = $request->file('file')->getRealPath();

        $spreadsheet = IOFactory::load($file);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray();

        foreach ($rows as $index => $row) {
            if ($index === 0) continue; // skip header

            Participant::create([
                'name'     => $row[0] ?? null,
                'username' => $row[1] ?? null,
                'email'    => $row[2] ?? null,
                'password' => isset($row[3]) ? Hash::make($row[3]) : null,
            ]);
        }

        return Response::apiSuccess('Participants imported successfully from Excel');
    }

    function store(Request $request)
    {
        $request->validate([
            'name'     => 'required|string|max:255',
            'username' => 'required|string|max:255|unique:participants,username',
            'email'    => 'required|email|unique:participants,email',
            'password' => 'required|string|min:6',
        ]);

        Participant::create([
            'name'     => $request->name,
            'username' => $request->username,
            'email'    => $request->email,
            'password' => Hash::make($request->password),
        ]);

        return Response::apiSuccess('Participant created successfully from form');
    }

    function update(ParticipantRequest $request, Participant $participant)
    {
        $data = $request->validated();
        $participant->updateQuietly($data);
        return Response::apiSuccess('Participants has been updated successfully');
    }
    function destroy(Participant $participant)
    {
        $name = $participant->name ?? $participant->username;
        $participant->delete();
        return Response::apiSuccess('Participants has been deleted successfully :{$name}');
    }
}
