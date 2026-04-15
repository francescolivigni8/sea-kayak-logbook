<?php

namespace App\Http\Controllers;

use App\Support\UserInsightsData;
use Inertia\Inertia;
use Inertia\Response;

class UserInsightsController extends Controller
{
    public function __construct(
        private readonly UserInsightsData $insights,
    ) {}

    public function __invoke(): Response
    {
        return Inertia::render('insights/Users', $this->insights->build());
    }
}
