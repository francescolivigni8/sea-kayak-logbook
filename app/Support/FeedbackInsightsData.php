<?php

namespace App\Support;

use App\Models\FeedbackReport;

class FeedbackInsightsData
{
    public function build(): array
    {
        $reports = FeedbackReport::query()
            ->with([
                'user:id,name,email',
                'profile:id,name,slug',
            ])
            ->latest()
            ->get();

        $mappedReports = $reports->map(fn (FeedbackReport $report) => [
            'id' => $report->id,
            'kind' => $report->kind,
            'subject' => $report->subject,
            'message' => $report->message,
            'pageContext' => $report->page_context,
            'submittedFromPath' => $report->submitted_from_path,
            'status' => $report->status,
            'createdAt' => $report->created_at?->toIso8601String(),
            'createdRelative' => $report->created_at?->diffForHumans(),
            'user' => [
                'name' => $report->user?->name,
                'email' => $report->user?->email,
            ],
            'profile' => [
                'name' => $report->profile?->name,
                'slug' => $report->profile?->slug,
            ],
        ])->values();

        $overviewCards = [
            [
                'label' => 'Total reports',
                'value' => $reports->count(),
                'detail' => 'All issue reports, questions, feedback notes, and ideas sent from the app.',
            ],
            [
                'label' => 'Issues',
                'value' => $reports->where('kind', 'issue')->count(),
                'detail' => 'Reports explicitly marked as bugs or broken behaviour.',
            ],
            [
                'label' => 'Last 7 days',
                'value' => $reports->filter(fn (FeedbackReport $report) => $report->created_at?->gte(now()->subDays(7)))->count(),
                'detail' => 'Fresh reports from the active testing window.',
            ],
            [
                'label' => 'Questions + ideas',
                'value' => $reports->whereIn('kind', ['question', 'idea'])->count(),
                'detail' => 'Potential product improvements or unclear flows flagged by testers.',
            ],
        ];

        $kindBreakdown = collect(['issue', 'feedback', 'idea', 'question'])
            ->map(fn (string $kind) => [
                'kind' => $kind,
                'label' => match ($kind) {
                    'issue' => 'Issues',
                    'feedback' => 'Feedback',
                    'idea' => 'Ideas',
                    default => 'Questions',
                },
                'count' => $reports->where('kind', $kind)->count(),
            ])
            ->values();

        return [
            'overviewCards' => $overviewCards,
            'kindBreakdown' => $kindBreakdown,
            'reports' => $mappedReports,
        ];
    }
}
