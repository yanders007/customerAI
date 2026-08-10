<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Projet;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    // Liste les projets du client connecté
    public function index(Request $request)
    {
        $clientId = $request->session()->get('client_id');

        $projets = Projet::where('client_id', $clientId)
            ->orderBy('nom_projet')
            ->select('id', 'nom_projet', 'updated_at')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $projets,
        ]);
    }

    // Sélectionne un projet (stocke en session)
    public function select(Request $request)
    {
        $data     = $request->validate(['project_id' => ['required', 'integer']]);
        $clientId = $request->session()->get('client_id');

        $projet = Projet::where('id', $data['project_id'])
            ->where('client_id', $clientId)
            ->first();

        if (!$projet) {
            return response()->json(['success' => false, 'error' => 'Projet introuvable'], 404);
        }

        $request->session()->put('project_id', $projet->id);
        $request->session()->put('project_nom', $projet->nom_projet);

        return response()->json([
            'success' => true,
            'project' => [
                'id'         => $projet->id,
                'nom_projet' => $projet->nom_projet,
            ],
        ]);
    }
}
