<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Documentation;
use App\Models\Faq;
use App\Models\Projet;
use App\Services\CohereEmbeddingService;
use App\Services\DocumentationIndexer;
use App\Services\PdfTextExtractor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DocsController extends Controller
{
    public function __construct(
        private PdfTextExtractor       $pdfExtractor,
        private DocumentationIndexer   $indexer,
        private CohereEmbeddingService $embeddings,
    ) {}

    // ═══════════════════════════════════════════════
    //  GET  /admin/docs?type=projet|doc|faq
    // ═══════════════════════════════════════════════
    public function index(Request $request)
    {
        $type = $request->query('type', 'projet');

        if ($type === 'projet') {
            $clientId = (int) $request->query('client_id');
            if (!$clientId) return response()->json(['success' => false, 'error' => 'client_id requis'], 400);
            return response()->json(['success' => true, 'data' =>
                Projet::where('client_id', $clientId)->orderByDesc('updated_at')->get()
            ]);
        }

        if ($type === 'doc') {
            $projetId = (int) $request->query('projet_id');
            if (!$projetId) return response()->json(['success' => false, 'error' => 'projet_id requis'], 400);
            return response()->json(['success' => true, 'data' =>
                Documentation::where('projet_id', $projetId)->orderByDesc('updated_at')->get()
            ]);
        }

        if ($type === 'faq') {
            $docId = (int) $request->query('documentation_id');
            // Sans documentation_id → toutes les FAQs (vue globale admin)
            $query = Faq::with(['documentation:id,titre', 'documentation.projet:id,nom_projet'])
                ->orderByDesc('updated_at');
            if ($docId) $query->where('documentation_id', $docId);
            $faqs = $query->get()->map(function ($f) {
                return [
                    'id'               => $f->id,
                    'question'         => $f->question,
                    'reponse'          => $f->reponse,
                    'documentation_id' => $f->documentation_id,
                    'doc_titre'        => $f->documentation?->titre,
                    'projet_name'      => $f->documentation?->projet?->nom_projet,
                    'updated_at'       => $f->updated_at,
                ];
            });
            return response()->json(['success' => true, 'data' => $faqs]);
        }

        return response()->json(['success' => false, 'error' => 'type invalide'], 400);
    }

    // ═══════════════════════════════════════════════
    //  POST  /admin/docs?type=projet|doc|faq
    // ═══════════════════════════════════════════════
    public function store(Request $request)
    {
        $type = $request->query('type', 'projet');

        if ($type === 'projet') {
            $data = $request->validate([
                'client_id'  => ['required', 'integer', 'exists:clients,id'],
                'nom_projet' => ['required', 'string', 'max:200'],
            ]);
            $projet = Projet::create($data);
            return response()->json(['success' => true, 'message' => 'Projet créé', 'data' => $projet], 201);
        }

        if ($type === 'doc') return $this->storeDoc($request);

        if ($type === 'faq') {
            $data = $request->validate([
                'documentation_id' => ['required', 'integer', 'exists:documentations,id'],
                'question'         => ['required', 'string', 'max:500'],
                'reponse'          => ['required', 'string', 'max:5000'],
            ]);
            // Générer l'embedding de la FAQ pour la recherche directe
            $embedding = $this->embeddings->embed($data['question'] . ' ' . $data['reponse']);
            $faq = Faq::create([...$data, 'embedding' => $embedding ? json_encode($embedding) : null]);
            return response()->json(['success' => true, 'message' => 'FAQ ajoutée', 'data' => $faq], 201);
        }

        return response()->json(['success' => false, 'error' => 'type invalide'], 400);
    }

    // ═══════════════════════════════════════════════
    //  PUT  /admin/docs?type=projet|doc|faq
    // ═══════════════════════════════════════════════
    public function update(Request $request)
    {
        $type = $request->query('type', 'projet');

        if ($type === 'projet') {
            $data = $request->validate([
                'id' => ['required', 'integer'], 
                'nom_projet' => ['required', 'string', 'max:255']
            ]);
            Projet::findOrFail($data['id'])->update(['nom_projet' => $data['nom_projet']]);
            return response()->json(['success' => true, 'message' => 'Projet mis à jour']);
        }

        if ($type === 'doc') {
            $data = $request->validate([
                'id' => ['required', 'integer'], 
                'titre' => ['required', 'string', 'max:255'], 
                'contenu' => ['required', 'string', 'max:500000']
            ]);
            $doc = Documentation::findOrFail($data['id']);
            $doc->update(['titre' => $data['titre'], 'contenu' => $data['contenu']]);
            try { $this->indexer->index($doc); } catch (\Throwable $e) { report($e); }
            return response()->json(['success' => true, 'message' => 'Documentation mise à jour']);
        }

        if ($type === 'faq') {
            $data = $request->validate([
                'id' => ['required', 'integer'], 
                'question' => ['required', 'string', 'max:500'], 
                'reponse' => ['required', 'string', 'max:5000']
            ]);
            $faq = Faq::findOrFail($data['id']);
            // Régénérer l'embedding après modification
            $embedding = $this->embeddings->embed($data['question'] . ' ' . $data['reponse']);
            $faq->update(['question' => $data['question'], 'reponse' => $data['reponse'], 'embedding' => $embedding ? json_encode($embedding) : null]);
            return response()->json(['success' => true, 'message' => 'FAQ mise à jour']);
        }

        return response()->json(['success' => false, 'error' => 'type invalide'], 400);
    }

    // ═══════════════════════════════════════════════
    //  DELETE  /admin/docs?type=projet|doc|faq
    // ═══════════════════════════════════════════════
    public function destroy(Request $request)
    {
        $type = $request->query('type', 'projet');
        $id   = (int) $request->input('id');
        if (!$id) return response()->json(['success' => false, 'error' => 'id requis'], 400);

        if ($type === 'projet') { Projet::findOrFail($id)->delete(); return response()->json(['success' => true, 'message' => 'Projet supprimé']); }
        if ($type === 'doc') {
            $doc = Documentation::findOrFail($id);
            if ($doc->file_path) Storage::disk('public')->delete($doc->file_path);
            $doc->delete();
            return response()->json(['success' => true, 'message' => 'Documentation supprimée']);
        }
        if ($type === 'faq') { Faq::findOrFail($id)->delete(); return response()->json(['success' => true, 'message' => 'FAQ supprimée']); }

        return response()->json(['success' => false, 'error' => 'type invalide'], 400);
    }

    // ─── Helper storeDoc ───────────────────────────
    private function storeDoc(Request $request)
    {
        $projetId = (int) $request->input('projet_id');
        $titre    = trim((string) $request->input('titre', ''));
        if (!$projetId || $titre === '') return response()->json(['success' => false, 'error' => 'projet_id et titre requis'], 400);
        if (!Projet::where('id', $projetId)->exists()) return response()->json(['success' => false, 'error' => 'Projet introuvable'], 404);

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $ext  = strtolower($file->getClientOriginalExtension());
            if (!in_array($ext, ['txt', 'pdf'], true)) return response()->json(['success' => false, 'error' => 'Seuls PDF et TXT acceptés'], 400);
            if ($file->getSize() > 5 * 1024 * 1024) return response()->json(['success' => false, 'error' => 'Fichier trop volumineux (max 5MB)'], 400);
            $storedPath = $file->store('uploads', 'public');
            $contenu    = $ext === 'txt' ? file_get_contents($file->getRealPath()) : $this->pdfExtractor->extract(Storage::disk('public')->path($storedPath));
            if (empty(trim((string) $contenu))) return response()->json(['success' => false, 'error' => 'Impossible de lire le contenu du fichier'], 422);
            $filePath = $storedPath;
        } else {
            $contenu  = trim((string) $request->input('contenu', ''));
            $filePath = null;
            if ($contenu === '') return response()->json(['success' => false, 'error' => 'contenu requis si pas de fichier'], 400);
        }

        $doc = Documentation::create(['projet_id' => $projetId, 'titre' => $titre, 'contenu' => $contenu, 'file_path' => $filePath]);
        \App\Jobs\IndexDocumentationJob::dispatch($doc);
        return response()->json(['success' => true, 'message' => 'Documentation créée, indexation IA en cours…', 'data' => $doc], 201);
    }
}
