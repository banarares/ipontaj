<?php

namespace App\Http\Controllers;

use App\Services\CompanyRegistry;
use App\Support\CompanyCif;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompanyLookupController extends Controller
{
    public function __invoke(Request $request, CompanyRegistry $registry): JsonResponse
    {
        abort_if($request->user()->is_demo, 403, 'Căutarea firmelor este disponibilă după crearea unui cont.');
        $data = $request->validate(['cif' => 'required|string|max:20']);
        $result = $registry->lookup(CompanyCif::normalize($data['cif']));

        return match ($result['status']) {
            'found' => response()->json($result),
            'not_found' => response()->json([...$result, 'message' => 'Nu am găsit firma la ANAF. Verifică CIF-ul sau completează denumirea manual.'], 404),
            'busy' => response()->json([...$result, 'message' => 'Serviciul de căutare este ocupat. Încearcă din nou peste câteva secunde.'], 429)->header('Retry-After', '1'),
            default => response()->json([...$result, 'message' => 'ANAF nu răspunde acum. Poți încerca din nou sau completa denumirea manual.'], 503),
        };
    }
}
