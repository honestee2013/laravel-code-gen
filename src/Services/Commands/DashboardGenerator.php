<?php

namespace QuickerFaster\CodeGen\Services\Commands;

use Illuminate\Support\Str;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class DashboardGenerator extends Command
{
    public function __construct($command = null)
    {
        parent::__construct();
        $this->command = $command;
        if ($command) {
            $this->setLaravel($command->getLaravel());
            $this->output = $command->getOutput();
        }
    }

// In DashboardGenerator.php - add this method
protected function generateDefaultDashboardConfig($module, $schema)
{
    $configPath = app_path("Modules/{$module}/Data/dashboards");
    if (!File::exists($configPath)) {
        File::makeDirectory($configPath, 0755, true);
    }

    // Create default.php that includes the first dashboard
    $firstDashboard = collect($schema['dashboards'] ?? [])->first();
    
    if ($firstDashboard) {
        $defaultConfig = [
            'title' => $firstDashboard['title'] ?? 'Dashboard',
            'description' => $firstDashboard['description'] ?? '',
            'widgets' => $this->processDashboardWidgets($firstDashboard['widgets'] ?? [], $schema),
        ];

        $content = "<?php\n\nreturn " . var_export($defaultConfig, true) . ";\n";
        File::put("{$configPath}/default.php", $content);
        $this->command->info("Default dashboard config created: {$configPath}/default.php");
    }
}

// Update generateDashboards method to call this
public function generateDashboards($schemaFile, $schema)
{
    if (empty($schema['dashboards'])) {
        return;
    }

    $modulesUsed = [];

    foreach ($schema['dashboards'] as $dashboardId => $dashboardData) {
        $targetModule = $this->resolveDashboardModule($dashboardId, $dashboardData, $schema);
        
        if (!$targetModule) {
            $this->command->error("Could not determine module for dashboard: {$dashboardId}. Add 'module: your_module' to dashboard config.");
            continue;
        }

        $modulesUsed[] = $targetModule;

        $configPath = app_path("Modules/{$targetModule}/Data/dashboards");
        if (!File::exists($configPath)) {
            File::makeDirectory($configPath, 0755, true);
        }

        $this->generateDashboardConfig($dashboardId, $dashboardData, $configPath, $schema);
        $this->generateDashboardManagerView($dashboardId, $dashboardData, $targetModule);

        $this->command->info("Dashboard '{$dashboardId}' generated in module '{$targetModule}'");
    }

    // Create default dashboard files for each module used
    foreach (array_unique($modulesUsed) as $module) {
        $this->generateDefaultDashboardConfig($module, $schema);
    }
}

    protected function resolveDashboardModule($dashboardId, $dashboardData, $schema)
    {
        // Priority 1: Explicit module declaration
        if (!empty($dashboardData['module'])) {
            return $dashboardData['module'];
        }

        // Priority 2: Context-based module resolution
        if (!empty($dashboardData['context'])) {
            return $dashboardData['context'];
        }

        // Priority 3: Extract from widgets
        $modules = [];
        foreach ($dashboardData['widgets'] ?? [] as $widgetId => $widgetConfig) {
            if (!empty($widgetConfig['model'])) {
                $module = $this->getModelModuleFromFqcn($widgetConfig['model']);
                if ($module) {
                    $modules[] = $module;
                }
            }
        }

        $uniqueModules = array_unique($modules);
        
        if (count($uniqueModules) === 1) {
            return $uniqueModules[0];
        }

        // Priority 4: Use dashboard ID as fallback
        return Str::camel($dashboardId);
    }

    protected function getModelModuleFromFqcn($fqcn)
    {
        $parts = explode('\\', $fqcn);
        $modulesIndex = array_search('Modules', $parts);
        
        if ($modulesIndex === false || !isset($parts[$modulesIndex + 1])) {
            return null;
        }
        
        $moduleName = $parts[$modulesIndex + 1];
        return Str::camel($moduleName);
    }

    protected function generateDashboardConfig($dashboardId, $dashboardData, $configPath, $schema)
    {
        $filePath = "{$configPath}/{$dashboardId}.php";
        
        $configData = [
            'title' => $dashboardData['title'] ?? Str::title(str_replace('_', ' ', $dashboardId)),
            'description' => $dashboardData['description'] ?? '',
            'widgets' => $this->processDashboardWidgets($dashboardData['widgets'] ?? [], $schema),
        ];

        // Add role-based configuration if specified
        if (!empty($dashboardData['roles'])) {
            $configData['roles'] = $dashboardData['roles'];
        }

        $content = "<?php\n\nreturn " . var_export($configData, true) . ";\n";
        File::put($filePath, $content);
        
        $this->command->info("Dashboard config created: {$filePath}");
    }

    protected function processDashboardWidgets($widgets, $schema)
    {
        $processedWidgets = [];
        
        foreach ($widgets as $widgetId => $widgetConfig) {
            $processedWidgets[$widgetId] = $this->processWidgetConfig($widgetConfig, $schema);
        }

        return $processedWidgets;
    }

    protected function processWidgetConfig($widgetConfig, $schema)
    {
        $processed = [
            'type' => $widgetConfig['type'],
            'title' => $widgetConfig['title'] ?? '',
            'size' => $widgetConfig['size'] ?? 'col-12',
        ];

        // Handle model-based widgets
        if (!empty($widgetConfig['model'])) {
            $processed['model'] = $this->normalizeModelName($widgetConfig['model'], $schema);
        }

        // Handle static data widgets
        if (!empty($widgetConfig['static_data'])) {
            $processed['static_data'] = $widgetConfig['static_data'];
        }

        // Handle calculation configuration
        if (!empty($widgetConfig['calculation_method'])) {
            $processed['calculation_method'] = $widgetConfig['calculation_method'];
        }

        // Handle filters
        if (!empty($widgetConfig['filters'])) {
            $processed['filters'] = $widgetConfig['filters'];
        }

        // Handle pivot configuration for charts
        if (!empty($widgetConfig['pivot'])) {
            $processed['pivot'] = $widgetConfig['pivot'];
        }

        // Handle group by configuration
        if (!empty($widgetConfig['group_by'])) {
            $processed['group_by'] = $widgetConfig['group_by'];
        }

        if (!empty($widgetConfig['group_by_table'])) {
            $processed['group_by_table'] = $widgetConfig['group_by_table'];
            $processed['group_by_table_column'] = $widgetConfig['group_by_table_column'] ?? 'name';
        }

        // Handle chart-specific configurations
        if ($widgetConfig['type'] === 'chart') {
            if (!empty($widgetConfig['chart_type'])) {
                $processed['chart_type'] = $widgetConfig['chart_type'];
            }
            if (!empty($widgetConfig['controls'])) {
                $processed['controls'] = $widgetConfig['controls'];
            }
        }

        // Handle count-up/down configurations
        if (in_array($widgetConfig['type'], ['count-up', 'count-down'])) {
            if (!empty($widgetConfig['prefix'])) {
                $processed['prefix'] = $widgetConfig['prefix'];
            }
            if (!empty($widgetConfig['suffix'])) {
                $processed['suffix'] = $widgetConfig['suffix'];
            }
            if (!empty($widgetConfig['duration'])) {
                $processed['duration'] = $widgetConfig['duration'];
            }
        }

        // Handle progress bar configurations
        if ($widgetConfig['type'] === 'progress-bar') {
            if (!empty($widgetConfig['element_label'])) {
                $processed['element_label'] = $widgetConfig['element_label'];
            }
            if (!empty($widgetConfig['progress_colors'])) {
                $processed['progress_colors'] = $widgetConfig['progress_colors'];
            }
        }

        // Handle icon configurations
        if (!empty($widgetConfig['icon'])) {
            $processed['icon'] = $widgetConfig['icon'];
        }

        // Handle color configurations
        if (!empty($widgetConfig['color'])) {
            $processed['color'] = $widgetConfig['color'];
        }

        return $processed;
    }

    protected function normalizeModelName($modelName, $schema)
    {
        // Already FQCN?
        if (strpos($modelName, '\\') !== false) {
            return $modelName;
        }
        
        // Resolve from schema
        if (isset($schema['models'][$modelName]['module'])) {
            $module = $schema['models'][$modelName]['module'];
            $ucModule = ucfirst($module);
            return "App\\Modules\\{$ucModule}\\Models\\{$modelName}";
        }
        
        // Fallback
        return "App\\Models\\{$modelName}";
    }

    protected function generateDashboardManagerView($dashboardId, $dashboardData, $targetModule)
    {
        $viewName = 'dashboard-manager';
        $viewPath = app_path("Modules/{$targetModule}/Resources/views/{$viewName}.blade.php");

        // Ensure directory exists
        if (!File::exists(dirname($viewPath))) {
            File::makeDirectory(dirname($viewPath), 0755, true);
        }

        $stub = $this->getDashboardManagerStub($dashboardId, $targetModule, $dashboardData);

        File::put($viewPath, $stub);
        $this->command->info("Dashboard view created: {$viewPath}");
    }



protected function getDashboardManagerStub($dashboardId, $module, $dashboardData)
{
    // Check for stub file first
    $stubPath = __DIR__ . '/../../Stubs/dashboard-manager.blade.stub';
    
    if (File::exists($stubPath)) {
        $stub = File::get($stubPath);
        return str_replace('{{ $title }}', $dashboardData['title'] ?? 'Dashboard', $stub);
    }
    
    // Fallback to inline template (the fixed version above)
    return $this->getInlineDashboardStub($dashboardId, $module, $dashboardData);
}



    protected function getInlineDashboardStub($dashboardId, $module, $dashboardData)
    {
        $moduleName = ucfirst($module);
        $camelModule = Str::camel($module);
        $context = $dashboardData['context'] ?? $camelModule;
        $title = $dashboardData['title'] ?? 'Dashboard';

        return <<<BLADE
<x-qf::livewire.bootstrap.layouts.dashboards.default-dashboard>
    <x-slot name="mainTitle">
        <strong class="text-info text-gradient">{$title}</strong> Overview
    </x-slot>

    <x-slot name="subtitle">
        <span class="text-primary text-xs fst-italic">
            @if(\$isLoading)
                <i class="fas fa-spinner fa-spin"></i> Updating...
            @else
                Last updated: {{\$lastUpdated->diffForHumans()}}
            @endif
        </span>
    </x-slot>

    <x-slot name="controls">
        @include('qf::components.livewire.bootstrap.layouts.dashboards.dashboard-control')

        <button wire:click="refreshData" class="btn btn-sm btn-outline-primary ms-2"
                wire:loading.attr="disabled">
            <i class="fas fa-sync-alt" wire:loading.class="fa-spin"></i>
            <span wire:loading>Refreshing...</span>
            <span wire:loading.remove>Refresh</span>
        </button>
    </x-slot>

    {{-- Counter Widgets (CountUp & CountDown) --}}
    <div class="row g-4 mb-4">
        @foreach(\$widgetsConfig as \$widgetId => \$config)
            @if(in_array(\$config['type'], ['count-up', 'count-down']))
                <div class="{{ '{{' }}\$config['size'] ?? 'col-12 col-sm-6 col-lg-3' }}">
                    @if(\$config['type'] === 'count-up')
                        <livewire:qf::widgets.counters.count-up-widget
                            :widgetId="\$widgetId"
                            :config="\$config"
                            :initialData="\$dashboardData[\$widgetId] ?? null"
                            :key="'countup-'.\$widgetId"
                        />
                    @elseif(\$config['type'] === 'count-down')
                        <livewire:qf::widgets.counters.count-down-widget
                            :widgetId="\$widgetId"
                            :config="\$config"
                            :initialData="\$dashboardData[\$widgetId] ?? null"
                            :key="'countdown-'.\$widgetId"
                        />
                    @endif
                </div>
            @endif
        @endforeach
    </div>

    {{-- Icon Card Widgets --}}
    <div class="row g-4 mb-4">
        @foreach(\$widgetsConfig as \$widgetId => \$config)
            @if(\$config['type'] === 'icon-card')
                <div class="{{ '{{' }}\$config['size'] ?? 'col-12 col-sm-4' }}">
                    <livewire:qf::widgets.cards.icon-card-widget
                        :widgetId="\$widgetId"
                        :config="\$config"
                        :initialData="\$dashboardData[\$widgetId] ?? null"
                        :key="'icon-'.\$widgetId"
                    />
                </div>
            @endif
        @endforeach
    </div>

    {{-- Progress Bar Widgets --}}
    <div class="row g-4 mb-4">
        @foreach(\$widgetsConfig as \$widgetId => \$config)
            @if(\$config['type'] === 'progress-bar')
                <div class="{{ '{{' }}\$config['size'] ?? 'col-12 col-sm-6' }}">
                    <livewire:qf::widgets.progresses.progress-bar-widget
                        :widgetId="\$widgetId"
                        :config="\$config"
                        :initialData="\$dashboardData[\$widgetId] ?? null"
                        :key="'progress-'.\$widgetId"
                    />
                </div>
            @endif
        @endforeach
    </div>

    {{-- Chart Widgets --}}
    <div class="row g-4 mb-4">
        @foreach(\$widgetsConfig as \$widgetId => \$config)
            @if(\$config['type'] === 'chart')
                <div class="{{ '{{' }}\$config['size'] ?? 'col-12' }}">
                    <livewire:qf::widgets.charts.chart-widget
                        :widgetId="\$widgetId"
                        :config="\$config"
                        :initialData="\$dashboardData[\$widgetId] ?? null"
                        :key="'chart-'.\$widgetId"
                    />
                </div>
            @endif
        @endforeach
    </div>
</x-qf::livewire.bootstrap.layouts.dashboards.default-dashboard>
BLADE;
    }
}