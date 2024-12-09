<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Subject;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    /*===========
    Subject Controller
    =============*/
    //get subject
    public function getSubject()
    {
        $subjects = Subject::all();
        if ($subjects->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Subject not found',
            ], 404);
        }
        return response()->json([
            'success' => true,
            'subjects' => $subjects,
        ]);
    }
    //store subject
    public function storeSubject(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
            ]);

            $subject = new Subject();
            $subject->name = $request->name;
            $subject->description = $request->description;
            $subject->save();

            return response()->json([
                'success' => true,
                'message' => 'Subject created successfully',
                'subject' => $subject,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    //destroy subject
    public function destroySubject($id)
    {
        $subject = Subject::find($id);
        if (!$subject) {
            return response()->json([
                'success' => false,
                'message' => 'Subject not found',
            ], 404);
        }
        $subject->delete();
        return response()->json([
            'success' => true,
            'message' => 'Subject deleted successfully',
        ]);
    }

    /*====================
    Subject Controller end
    =====================*/
}
