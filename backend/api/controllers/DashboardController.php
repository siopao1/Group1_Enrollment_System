<?php

declare(strict_types=1);

/** GET /api/dashboard — live summary figures for the dashboard screen. */
final class DashboardController
{
    public function __construct(private DashboardRepository $dashboard, private SettingsRepository $settings)
    {
    }

    public function show(): never
    {
        // The term cards follow the default term chosen in Settings.
        $term = $this->settings->all();

        Response::success(
            'Dashboard summary retrieved.',
            $this->dashboard->summary($term['academicYear'], $term['semester'])
        );
    }
}
