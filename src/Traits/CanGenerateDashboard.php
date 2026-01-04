<?php

namespace QuickerFaster\CodeGen\Traits;



use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

trait CanGenerateDashboard {
    protected function generateDashboardView($dashboardId, $module) {
        $dashboardId = str_replace('_', '-', $dashboardId);
        $fileName = $dashboardId.".blade.php";
        // Ensure that the file name starts with the word: 'dashboard'
        if (!Str::startsWith($fileName, 'dashboard'))
            $fileName = "dashboard-{$dashboardId}.blade.php";
        $viewPath = app_path("Modules/".ucfirst($module)."/Resources/views/".$fileName);
        
        // Create directory if it doesn't exist
        if (!File::exists(dirname($viewPath))) {
            File::makeDirectory(dirname($viewPath), 0755, true);
        }

    // Convert to small case
    $module = strtolower($module);
    $stub = "<x-qf::livewire.bootstrap.layouts.app>
    <x-slot name=\"topNav\">
        <livewire:qf::layouts.navs.top-nav moduleName=\"$module\">
    </x-slot>



    <livewire:qf::dashboards.dashboard-manager moduleName=\"$module\" viewName=\"$dashboardId\" />

</x-qf::livewire.bootstrap.layouts.app>";
        File::put($viewPath, $stub);
        
        $this->command->info("Dashboard blade view created: {$viewPath}");
    }

}
