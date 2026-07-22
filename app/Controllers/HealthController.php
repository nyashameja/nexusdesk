<?php

declare(strict_types=1);

namespace ParagonHostOps\Controllers;

use ParagonHostOps\Core\Controller;
use ParagonHostOps\Core\Request;
use ParagonHostOps\Core\Response;
use ParagonHostOps\Repositories\HealthRepository;
use ParagonHostOps\Services\AuditLogger;
use ParagonHostOps\Services\HealthScoreService;

/**
 * Client / account health scoring. Scores are computed transparently from the
 * available data; each is shown with the factors that shaped it.
 */
final class HealthController extends Controller
{
    public function __construct(
        private HealthRepository $health,
        private HealthScoreService $scorer,
        private AuditLogger $audit,
    ) {
    }

    public function index(Request $request, array $params): Response
    {
        $rows = $this->health->accountInputs();

        $scored = [];
        $bands = ['excellent' => 0, 'good' => 0, 'attention' => 0, 'risk' => 0, 'critical' => 0, 'incomplete' => 0];

        foreach ($rows as $row) {
            $result = $this->scorer->evaluate($this->health->toEvaluatorInput($row));
            $bands[$result['band']]++;
            $scored[] = [
                'account' => $row,
                'result'  => $result,
            ];
        }

        // Order: incomplete last, otherwise lowest score first (most at risk).
        usort($scored, static function (array $a, array $b): int {
            $sa = $a['result']['score'];
            $sb = $b['result']['score'];
            if ($sa === null && $sb === null) {
                return 0;
            }
            if ($sa === null) {
                return 1;
            }
            if ($sb === null) {
                return -1;
            }
            return $sa <=> $sb;
        });

        return $this->view('health.index', [
            'title'  => 'Client Health',
            'scored' => $scored,
            'bands'  => $bands,
            'total'  => count($scored),
        ]);
    }

    /**
     * Recompute and persist all account health scores.
     */
    public function recompute(Request $request, array $params): Response
    {
        $rows = $this->health->accountInputs();
        $count = 0;

        foreach ($rows as $row) {
            $result = $this->scorer->evaluate($this->health->toEvaluatorInput($row));
            $this->health->persist('account', (int) $row['id'], $result['score'], $result['band'], $result['factors']);
            $count++;
        }

        $this->audit->record('health.recomputed', "Recomputed {$count} account health score(s).");
        $this->session()->flash('success', "Recomputed {$count} health score(s).");

        return $this->redirect('/health');
    }
}
