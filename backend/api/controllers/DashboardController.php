<?php

declare(strict_types=1);

/** GET /api/dashboard — live summary figures for the dashboard screen. */
final class DashboardController
{
    public function __construct(private DashboardRepository $dashboard)
    {
    }

    public function show(): never
    {
        Response::success('Dashboard summary retrieved.', $this->dashboard->summary());
    }
}
