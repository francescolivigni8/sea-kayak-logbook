<?php

namespace App\Http\Controllers;

use App\Support\FeedbackInsightsData;
use Inertia\Inertia;
use Inertia\Response;

class FeedbackInsightsController extends Controller
{
    public function __construct(
        private readonly FeedbackInsightsData $insights,
    ) {}

    public function __invoke(): Response
    {
        return Inertia::render('insights/Feedback', $this->insights->build());
    }
}
