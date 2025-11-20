<?php

namespace QuickerFaster\CodeGen\Services\Commands;

use Exception;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;

class SidebarLinksGenerator extends BaseLinksGenerator
{
    /**
     * Generate sidebar links from the given schema data.
     *
     * @param string $module
     * @param string $modelName
     * @param array $modelData
     * @return void
     * @throws Exception
     */
    public function generateSidebarLinks($module, $modelName, $modelData)
    {
        $sidebar = $modelData['sidebar'] ?? [];

        // Check if sidebar should be added
        /*if (isset($sidebar['add']) && !$sidebar['add']) {
            return;
        }*/

        /*$context = $sidebar['context']?? '';
        $context = $context? $context."_": '';*/
        $fileName = "sidebar_menu.php";

        try {
            $sidebarConfigPath = base_path("app/Modules/{$module}/Config/$fileName");
            $newEntry = $this->getSidebarEntryArray($module, $modelName, $modelData);
            
            // Read existing configuration
            $existing = $this->readConfig($sidebarConfigPath);
            
            // Check for duplicates
            if ($this->isDuplicateEntry($existing, $newEntry)) {
                $this->command->info("Sidebar entry already exists. Skipping: {$sidebarConfigPath}");
                return;
            }
            
            // Add group separator if needed
            if (isset($newEntry['groupTitle'])) {
                $existing = $this->addGroupSeparator($existing, $newEntry['groupTitle']);
            }
            
            // Add the new entry
            $existing[] = $newEntry;
            
            // Write the updated configuration
            $this->writeConfig($sidebarConfigPath, $existing);
            
            $this->command->info("Sidebar menu updated: {$sidebarConfigPath}");
            
            // Generate Blade component if needed
            $this->generateBladeComponent($module, $modelName, $modelData);
            
        } catch (Exception $e) {
            $this->command->error("Failed to generate sidebar links: {$e->getMessage()}");
            throw $e;
        }
    }

    /**
     * Add a group separator to the sidebar configuration.
     *
     * @param array $existingConfig
     * @param string $groupTitle
     * @return array
     */
    protected function addGroupSeparator($existingConfig, $groupTitle)
    {
        // Check if group separator already exists
        $groupExists = collect($existingConfig)->contains(function ($entry) use ($groupTitle) {
            return isset($entry['itemType']) && 
                   $entry['itemType'] === 'item-separator' && 
                   str_contains($entry['title'], $groupTitle);
        });
        
        if (!$groupExists) {
            $existingConfig[] = [
                'itemType' => 'item-separator',
                'title' => '<h6 class="ps-3 mt-4 mb-2 text-uppercase text-xs font-weight-bolder opacity-6 group-title">'.$groupTitle.'</h6>',
                'url' => null,
            ];
        }
        
        return $existingConfig;
    }

    /**
     * Get the sidebar entry array for a model.
     *
     * @param string $module
     * @param string $modelName
     * @param array $modelData
     * @return array
     */
    protected function getSidebarEntryArray($module, $modelName, $modelData)
    {
        $sidebar = $modelData['sidebar'] ?? [];
        
        $icon = $sidebar['iconClasses'] ?? $modelData['iconClasses'] ?? 'fas fa-cube';
        $url = $sidebar['url'] ?? Str::kebab(Str::plural($modelName));
        $title = $sidebar['title'] ?? Str::title(Str::snake(Str::plural($modelName), ' '));
        
        $entry = [
            'title' => $title,
            'icon'  => $icon,
            'url'   => "{$module}/{$url}",
            'permission' => $sidebar['permission'] ?? 'view_' . Str::snake($modelName),
        ];
        
        // Add groupTitle if it exists
        if (isset($sidebar['groupTitle'])) {
            $entry['groupTitle'] = $sidebar['groupTitle'];
        }
        
        // Add itemType if specified
        if (isset($sidebar['itemType'])) {
            $entry['itemType'] = $sidebar['itemType'];
        }
        
        // Add submenu if exists
        if (!empty($sidebar['submenu']) && is_array($sidebar['submenu'])) {
            $entry['submenu'] = collect($sidebar['submenu'])->map(function ($sub) use ($module) {
                $subItem = [
                    'title' => $sub['title'] ?? 'Subitem',
                    'url'   => "{$module}/" . ltrim($sub['url'] ?? '', '/'),
                ];
                
                if (isset($sub['permission'])) {
                    $subItem['permission'] = $sub['permission'];
                }
                
                if (isset($sub['icon'])) {
                    $subItem['icon'] = $sub['icon'];
                }
                
                return $subItem;
            })->toArray();
        }
        
        return $entry;
    }

    /**
     * Generate a Blade component for the sidebar links.
     *
     * @param string $module
     * @param string $modelName
     * @param array $modelData
     * @return void
     */
protected function generateBladeComponent($module, $modelName, $modelData)
{
    $sidebar = $modelData['sidebar'] ?? [];

    $context = strtolower($sidebar['context'] ?? '');
    $contextPath = $context ? $context . '/' : '';
    $fileName = $contextPath . 'sidebar-links.blade.php';

    $bladePath = app_path("Modules/{$module}/Resources/views/components/layouts/navbars/auth/{$fileName}");

    if (!File::exists(dirname($bladePath))) {
        File::makeDirectory(dirname($bladePath), 0755, true);
    }

    $stub = $this->getSidebarLinksStub($module, $modelName, $modelData);

    // Define pre/post sidebar link paths
    $preLinksPath = app_path("Modules/{$module}/Resources/views/components/layouts/navbars/auth/{$contextPath}sidebar-pre-links.blade.php");
    $postLinksPath = app_path("Modules/{$module}/Resources/views/components/layouts/navbars/auth/{$contextPath}sidebar-post-links.blade.php");

    // Generate dashboard link (same as top bar)
    $dashboardPath = strtolower($module) . '/dashboard';
    $dashboardLink = <<<HTML
<li class="nav-item">
    <a href="/{$dashboardPath}"
        class="nav-link @if(request()->is('{$dashboardPath}') || request()->is('{$dashboardPath}/*')) fw-bold text-primary @endif">
        @if(request()->is('{$dashboardPath}') || request()->is('{$dashboardPath}/*'))
            <i class="fas fa-tachometer-alt" aria-hidden="true"></i>
        @endif
        <span>Dashboard</span>
    </a>
</li>
HTML;

$dashboardLink = ""; // For now there is no need to add the dashboard link the sidebar. It is on the topbar already

    // Handle pre-links file (with dashboard)
    if (!File::exists($preLinksPath)) {
        $preLinksContent = "{{-- Pre-links section for {$module} sidebar --}}\n";
        $preLinksContent .= $dashboardLink . "\n";
        File::put($preLinksPath, $preLinksContent);
        $this->command->info("Sidebar pre-links file created with dashboard: {$preLinksPath}");
    } else {
        $existingPreContent = File::get($preLinksPath);
        if (!str_contains($existingPreContent, $dashboardLink)) {
            $newPreContent = "{{-- Pre-links section for {$module} sidebar --}}\n";
            $newPreContent .= $dashboardLink . "\n";
            $newPreContent .= $existingPreContent;
            File::put($preLinksPath, $newPreContent);
            $this->command->info("Dashboard link added to sidebar pre-links: {$preLinksPath}");
        }
    }

    // Ensure post-links file exists
    if (!File::exists($postLinksPath)) {
        File::put($postLinksPath, "{{-- Post-links section for {$module} sidebar --}}\n");
        $this->command->info("Sidebar post-links file created: {$postLinksPath}");
    }

    // Build include statements
    $viewPrefix = "{$module}.views::components.layouts.navbars.auth.{$contextPath}";
    $preLinksInclude = "@include('{$viewPrefix}sidebar-pre-links')";
    $postLinksInclude = "@include('{$viewPrefix}sidebar-post-links')";

    // Extract or initialize existing generated links
    $existingStubs = [];
    if (File::exists($bladePath)) {
        $existingContent = File::get($bladePath);
        if (str_contains($existingContent, $stub)) {
            $this->command->info("Sidebar link already exists in Blade component: {$bladePath}");
            return;
        }

        // Extract content between pre and post includes
        $pattern = '/' . preg_quote($preLinksInclude, '/') . '\s*(.*?)\s*' . preg_quote($postLinksInclude, '/') . '/s';
        if (preg_match($pattern, $existingContent, $matches)) {
            $existingStubs = array_filter(array_map('trim', explode("\n", $matches[1])));
        }
    }

    // Add new stub
    $existingStubs[] = trim($stub);

    // Build final content
    $content = "{{-- Sidebar Links for {$module} --}}\n\n";
    $content .= $preLinksInclude . "\n\n";
    $content .= "{{-- Generated Links --}}\n";
    $content .= implode("\n", $existingStubs) . "\n\n";
    $content .= $postLinksInclude . "\n";

    File::put($bladePath, $content);
    $this->command->info("Sidebar Blade component " . (File::exists($bladePath) ? 'updated' : 'created') . ": {$bladePath}");
}

    /**
     * Get the Blade stub for sidebar links.
     *
     * @param string $module
     * @param string $modelName
     * @param array $modelData
     * @return string
     */
    protected function getSidebarLinksStub($module, $modelName, $modelData)
    {
        $sidebar = $modelData['sidebar'] ?? [];
        $iconClasses = $sidebar['iconClasses'] ?? $modelData['iconClasses'] ?? 'fas fa-user';
        
        $url = $sidebar['url'] ?? Str::plural(str_replace('_', '-', Str::snake($modelName)));
        $title = $sidebar['title'] ?? Str::title(str_replace('_', ' ', Str::plural(Str::snake($modelName))));
        $permission = $sidebar['permission'] ?? 'view_' . Str::snake($modelName);
        
        return <<<BLADE
<li class="nav-item text-nowrap">
    <a href="/{$module}/{$url}" class="nav-link d-flex align-items-center" data-bs-toggle="tooltip" wire:ignore.self
        data-bs-placement="right" title="{$title}">
        <i class="{$iconClasses} me-2"></i>
        @if (\$state === 'full')
            <span>{$title}</span>
        @endif
    </a>
</li>
BLADE;

        /*return <<<BLADE
@if(auth()->user()?->can('{$permission}'))
    <x-core.views::layouts.navbars.sidebar-link-item
        iconClasses="{$iconClasses} sidebar-icon"
        url="{$module}/{$url}"
        title="{$title}"
    />
@endif
BLADE;*////////
    }
}