<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FeedbackSubmission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FeedbackController extends Controller
{
    public function index(Request $request): View
    {
        $status = $this->normalizeStatusFilter($request->query('status'));

        return view('admin.feedback.index', [
            'feedbackItems' => $this->filteredQuery($status)
                ->orderByDesc('created_at')
                ->paginate(20)
                ->withQueryString(),
            'statusFilter' => $status,
            'openCount' => FeedbackSubmission::query()->whereNull('resolved_at')->count(),
            'resolvedCount' => FeedbackSubmission::query()->whereNotNull('resolved_at')->count(),
        ]);
    }

    public function resolve(FeedbackSubmission $feedback): RedirectResponse
    {
        if ($feedback->isResolved()) {
            return redirect()
            ->route('admin.feedback.index', $this->redirectStatusParams())
            ->with('status', 'Feedback is already marked as resolved.');
        }

        $feedback->resolved_at = now();
        $feedback->save();

        return redirect()
            ->route('admin.feedback.index', $this->redirectStatusParams())
            ->with('status', 'Feedback marked as resolved.');
    }

    public function export(Request $request): StreamedResponse|Response
    {
        $status = $this->normalizeStatusFilter($request->query('status'));
        $items = $this->filteredQuery($status)
            ->orderByDesc('created_at')
            ->get();

        $filename = 'feedback-export-' . now()->format('Y-m-d-His') . '.csv';

        return response()->streamDownload(function () use ($items): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, ['ID', 'Issue Faced', 'Description', 'Status', 'Submitted At', 'Resolved At']);

            foreach ($items as $item) {
                fputcsv($handle, [
                    $item->id,
                    $item->issue_faced,
                    $item->description,
                    $item->statusLabel(),
                    $item->created_at?->format('Y-m-d H:i:s') ?? '',
                    $item->resolved_at?->format('Y-m-d H:i:s') ?? '',
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function normalizeStatusFilter(?string $status): string
    {
        return in_array($status, ['open', 'resolved'], true) ? $status : 'all';
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<FeedbackSubmission>
     */
    private function filteredQuery(string $status)
    {
        $query = FeedbackSubmission::query();

        if ($status === 'open') {
            $query->whereNull('resolved_at');
        } elseif ($status === 'resolved') {
            $query->whereNotNull('resolved_at');
        }

        return $query;
    }

    /**
     * @return array<string, string>
     */
    private function redirectStatusParams(): array
    {
        $status = request('status');

        return in_array($status, ['open', 'resolved'], true) ? ['status' => $status] : [];
    }
}
