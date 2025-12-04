<?php

namespace App\Http\Controllers\Contact;

use App\Http\Controllers\Controller;
use App\Http\Requests\Contact\ContactRequest;
use App\Models\Contact;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

class ContactController extends Controller
{
    //
    function store(ContactRequest $request)
    {
        $data=$request->validated();
        $contact=Contact::create($data);
        return Response::apiSuccess('your contact has been added', $data);
    }
}
