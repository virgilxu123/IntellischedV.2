<?php

namespace App\Http\Controllers;

use App\Models\Term;
use App\Models\Subject;
use App\Models\AcademicYear;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Models\AcademicYearTerm;
use Illuminate\Validation\ValidationException;

class AcademicYearTermController extends Controller
{
    public function index() {
        $terms = Term::all();
        $academicYear = AcademicYear::orderBy('year_start', 'desc')->first();//get the the latest year in db-> this will be used to check if there is a need to create new year
        $academicYearTerms = AcademicYearTerm::all()->sortByDesc('created_at');
        $academicYearTerms->load('academic_year', 'term');
        $academicYears = [$academicYear->year_start, $academicYear->year_start - 1];
        if($academicYear->year_start != Date('Y')){//if latest year not equal to current year
            $academicYears = [$academicYear->year_start + 1, $academicYear->year_start];//increment latest year->to be used in the select input when creating new schedule
        }
        if(request()->ajax()) {
            return response()->json(['academicYearTerms' => $academicYearTerms, 'academicYears' => $academicYears, 'terms' => $terms]);
        }
        return view('schedule', compact('academicYearTerms', 'terms', 'academicYears'));
    }

    public function store(Request $request) {
        try {

            $year = $request->validate([
                'year' => 'required',
            ]);
            $academicYear = AcademicYear::where('year_start', $year['year'])->first();
            if (!$academicYear) {
                // Create a new academic year if it doesn't exist
                $academicYear = new AcademicYear();
                $academicYear->year_start = $year['year'];
                // Add any additional fields if needed
                $academicYear->save();
            }
            $academicYear = AcademicYear::where('year_start', $year['year'])->first();
            $academicYear_id = $academicYear->id;   
            $term = $request->validate([ 
                'term_id' => [
                    'required',
                    Rule::unique('academic_year_terms')->where(function ($query) use ($academicYear_id) {
                        return $query->where('academic_year_id', $academicYear_id);
                    }),
                ],
            ]);
            // Create the academic year term record
            $academicYearTerm = new AcademicYearTerm();
            $academicYearTerm->academic_year_id = $academicYear->id;
            $academicYearTerm->term_id = $term['term_id'];
            $academicYearTerm->save();
            $academicYearTerm->load('academic_year', 'term');
    
            if(request()->ajax()) {
                return response()->json(['success' => 'Schedule created successfully.','academicYearTerm' => $academicYearTerm]);
            }
    
            return redirect()->route('schedule')->with('success', 'Schedule created successfully.');
        } catch (ValidationException $e) {
            // If validation fails, you can return the error response
            if(request()->ajax()) {
                return response()->json(['error' => $e->errors(), 'message' => 'Schedule Already Exist.'], 422);
            }
            return redirect()->back()->withErrors($e->errors())->withInput();
        }
    }

    public function show(AcademicYearTerm $academicYearTerm)
    {
        //
    }
}
