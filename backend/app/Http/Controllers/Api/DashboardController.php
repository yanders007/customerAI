<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Conversation;
use App\Models\Documentation;
use App\Models\Faq;
use App\Models\Message;
use App\Models\Projet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    // ── Stats globales ──────────────────────────────────────────
    public function index()
    {
        $tokensToday = Message::whereDate('created_at', today())
            ->selectRaw('COALESCE(SUM(tokens_input + tokens_output), 0) as total')
            ->value('total');

        $tokensMonth = Message::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->selectRaw('COALESCE(SUM(tokens_input + tokens_output), 0) as total')
            ->value('total');

        // Statistiques RAG : compare chunks vs fallback sur les 30 derniers jours
        $ragStats = Message::where('created_at', '>=', now()->subDays(30))
            ->where('role', 'assistant')
            ->selectRaw('retrieval_source, COUNT(*) as count, COALESCE(SUM(tokens_input), 0) as tokens')
            ->groupBy('retrieval_source')
            ->get()
            ->keyBy('retrieval_source');

        // Tokens par jour sur les 7 derniers jours (avec détails par source)
        $tokensWeek = Message::where('created_at', '>=', now()->subDays(6))
            ->selectRaw('DATE(created_at) as date,
                COALESCE(SUM(tokens_input), 0) as input,
                COALESCE(SUM(tokens_output), 0) as output,
                COALESCE(SUM(tokens_input + tokens_output), 0) as total,
                COALESCE(SUM(CASE WHEN retrieval_source = 'chunks' THEN tokens_input ELSE 0 END), 0) as chunks_tokens,
                COALESCE(SUM(CASE WHEN retrieval_source = 'fallback' THEN tokens_input ELSE 0 END), 0) as fallback_tokens,
                COALESCE(SUM(CASE WHEN retrieval_source = 'faq' THEN tokens_input ELSE 0 END), 0) as faq_tokens,
                COALESCE(SUM(CASE WHEN retrieval_source = 'smalltalk' THEN tokens_input ELSE 0 END), 0) as smalltalk_tokens,
                COUNT(CASE WHEN retrieval_source IN ('chunks', 'faq') THEN 1 END) as efficient_count,
                COUNT(CASE WHEN retrieval_source = 'fallback' THEN 1 END) as fallback_count')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Tokens par jour sur les 30 derniers jours (version détaillée)
        $tokensMonth30 = Message::where('created_at', '>=', now()->subDays(29))
            ->selectRaw('DATE(created_at) as date,
                COALESCE(SUM(tokens_input), 0) as input,
                COALESCE(SUM(tokens_output), 0) as output,
                COALESCE(SUM(tokens_input + tokens_output), 0) as total,
                COALESCE(SUM(CASE WHEN retrieval_source = 'chunks' THEN tokens_input ELSE 0 END), 0) as chunks_tokens,
                COALESCE(SUM(CASE WHEN retrieval_source = 'fallback' THEN tokens_input ELSE 0 END), 0) as fallback_tokens,
                COUNT(CASE WHEN retrieval_source IN ('chunks', 'faq') THEN 1 END) as efficient_count,
                COUNT(CASE WHEN retrieval_source = 'fallback' THEN 1 END) as fallback_count')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Calculer les variations jour par jour
        $tokensMonth30 = $tokensMonth30->map(function ($item, $index) use ($tokensMonth30) {
            if ($index > 0) {
                $previous = $tokensMonth30[$index - 1];
                $item->variation = $previous->total > 0 
                    ? round((($item->total - $previous->total) / $previous->total) * 100, 1)
                    : 0;
                $item->dominant_source = $this->getDominantSource($item);
            } else {
                $item->variation = 0;
                $item->dominant_source = $this->getDominantSource($item);
            }
            return $item;
        });

        // Efficacité RAG : ratio chunks/fallback
        $chunkCount    = $ragStats->get('chunks')?->count ?? 0;
        $fallbackCount = $ragStats->get('fallback')?->count ?? 0;
        $faqCount      = $ragStats->get('faq')?->count ?? 0;
        $totalAnswers  = $chunkCount + $fallbackCount + $faqCount;
        $ragEfficiency = $totalAnswers > 0 ? round(($chunkCount + $faqCount) / $totalAnswers * 100, 1) : 0;

        // Statistiques d'analyse
        $avgTokensPerDay = $tokensMonth30->avg('total');
        $maxTokensDay = $tokensMonth30->max('total');
        $minTokensDay = $tokensMonth30->where('total', '>', 0)->min('total') ?? 0;

        return response()->json([
            'success' => true,
            'stats' => [
                'clients'        => Client::count(),
                'projets'        => Projet::count(),
                'documentations' => Documentation::count(),
                'faqs'           => Faq::count(),
                'conversations'  => Conversation::count(),
                'escalated'      => Conversation::where('status', 'escalated')->count(),
                'messages'       => Message::count(),
            ],
            'tokens' => [
                'today'          => (int) $tokensToday,
                'month'          => (int) $tokensMonth,
                'week_data'      => $tokensWeek,
                'month30_data'   => $tokensMonth30,
                'rag_efficiency' => $ragEfficiency,
                'avg_per_day'    => round($avgTokensPerDay, 0),
                'max_day'        => (int) $maxTokensDay,
                'min_day'        => (int) $minTokensDay,
                'by_source'      => [
                    'chunks'    => (int) ($ragStats->get('chunks')?->count ?? 0),
                    'fallback'  => (int) ($ragStats->get('fallback')?->count ?? 0),
                    'faq'       => (int) ($ragStats->get('faq')?->count ?? 0),
                    'smalltalk' => (int) ($ragStats->get('smalltalk')?->count ?? 0),
                ],
                'tokens_by_source' => [
                    'chunks'   => (int) ($ragStats->get('chunks')?->tokens ?? 0),
                    'fallback' => (int) ($ragStats->get('fallback')?->tokens ?? 0),
                    'faq'      => (int) ($ragStats->get('faq')?->tokens ?? 0),
                ],
            ],
        ]);
    }

    // Helper pour déterminer la source dominante d'un jour
    private function getDominantSource($dayData): string
    {
        $sources = [
            'chunks' => $dayData->chunks_tokens ?? 0,
            'fallback' => $dayData->fallback_tokens ?? 0,
            'faq' => $dayData->faq_tokens ?? 0,
            'smalltalk' => $dayData->smalltalk_tokens ?? 0,
        ];
        
        arsort($sources);
        return array_key_first($sources);
    }

    // ── Stats d'un client ───────────────────────────────────────
    public function client(int $id)
    {
        $client    = Client::findOrFail($id);
        $projetIds = Projet::where('client_id', $id)->pluck('id');
        $docIds    = Documentation::whereIn('projet_id', $projetIds)->pluck('id');
        $convIds   = Conversation::where('client_id', $id)->pluck('id');

        $tokens = Message::whereIn('conversation_id', $convIds)
            ->selectRaw('COALESCE(SUM(tokens_input + tokens_output), 0) as total')
            ->value('total');

        $tokensWeek = Message::whereIn('conversation_id', $convIds)
            ->where('created_at', '>=', now()->subDays(6))
            ->selectRaw('DATE(created_at) as date, COALESCE(SUM(tokens_input + tokens_output), 0) as total')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return response()->json([
            'success' => true,
            'client'  => ['id' => $client->id, 'name' => $client->name, 'email' => $client->email],
            'stats'   => [
                'projets'        => $projetIds->count(),
                'documentations' => $docIds->count(),
                'faqs'           => Faq::whereIn('documentation_id', $docIds)->count(),
                'conversations'  => $convIds->count(),
                'escalated'      => Conversation::whereIn('id', $convIds)->where('status', 'escalated')->count(),
                'tokens_total'   => (int) $tokens,
                'tokens_week'    => $tokensWeek,
            ],
        ]);
    }

    // ── Stats tokens (endpoint dédié) ──────────────────────────
    public function tokens(Request $request)
    {
        $period = $request->query('period', 'week'); // week | month

        $days  = $period === 'month' ? 30 : 7;
        $data  = Message::where('created_at', '>=', now()->subDays($days - 1))
            ->where('role', 'assistant')
            ->selectRaw('DATE(created_at) as date,
                COALESCE(SUM(tokens_input), 0) as input,
                COALESCE(SUM(tokens_output), 0) as output,
                COALESCE(SUM(tokens_input + tokens_output), 0) as total,
                COUNT(CASE WHEN retrieval_source IN (\'chunks\',\'faq\') THEN 1 END) as rag_count,
                COUNT(CASE WHEN retrieval_source = \'fallback\' THEN 1 END) as fallback_count')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return response()->json([
            'success' => true,
            'period'  => $period,
            'data'    => $data,
        ]);
    }
}
