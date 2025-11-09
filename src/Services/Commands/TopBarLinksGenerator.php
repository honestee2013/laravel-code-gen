<?php

namespace QuickerFaster\CodeGen\Services\Commands;

use Exception;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;

class TopBarLinksGenerator extends BaseLinksGenerator
{
    /**
     * Generate top nav links from the given schema data.
     *
     * @param string $module
     * @param string $modelName
     * @param array $modelData
     * @return void
     * @throws Exception
     */
public function generateTopBarLinks($module, $modelName, $modelData)
{
    $topNav = $modelData['topNav'] ?? [];

    // Check if top nav should be added
    if (!isset($topNav['add']) || $topNav['add'] == false) {
        return;
    }

    $fileName = "top_bar_menu.php";

    try {
        $topNavConfigPath = base_path("app/Modules/{$module}/Config/$fileName");
        $newEntry = $this->getTopBarEntryArray($module, $modelName, $modelData);
        
        // Read existing configuration
        $existing = $this->readConfig($topNavConfigPath);
        
        // Check for duplicates
        if ($this->isDuplicateEntry($existing, $newEntry)) {
            $this->command->info("Top bar entry already exists. Skipping config update: {$topNavConfigPath}");
            
            // The function was previously returning here:
            // return; 
            
            // The configuration update is skipped, but execution continues below:

        } else {
            // Add the new entry
            $existing[] = $newEntry;
            
            // Write the updated configuration
            $this->writeConfig($topNavConfigPath, $existing);
            
            $this->command->info("Top bar menu updated: {$topNavConfigPath}");
        }

        // >>> MOVE THIS LINE OUTSIDE the if/else block <<<
        // This code will now execute whether the entry was new or existing.
        $this->generateBladeComponent($module, $modelName, $modelData); 
        
    } catch (Exception $e) {
        $this->command->error("Failed to generate top nav links: {$e->getMessage()}");
        throw $e;
    }
}


    /**
     * Get the top nav entry array for a model.
     *
     * @param string $module
     * @param string $modelName
     * @param array $modelData
     * @return array
     */
    protected function getTopBarEntryArray($module, $modelName, $modelData)
    {
        $topNav = $modelData['topNav'] ?? [];
        
        $icon = $topNav['iconClasses'] ?? $modelData['iconClasses'] ?? 'fas fa-cube';
        $url = $topNav['url'] ?? Str::kebab(Str::plural($modelName));
        $title = $topNav['title'] ?? Str::title(Str::snake(Str::plural($modelName), ' '));
        
        $entry = [
            'title' => $title,
            'icon'  => $icon,
            'url'   => "{$module}/{$url}",
            'permission' => $topNav['permission'] ?? 'view_' . Str::snake($modelName),
        ];
        
        // Add itemType if specified
        if (isset($topNav['itemType'])) {
            $entry['itemType'] = $topNav['itemType'];
        }
        
        return $entry;
    }

    /**
     * Generate a Blade component for the top nav links.
     *
     * @param string $module
     * @param string $modelName
     * @param array $modelData
     * @return void
     */
    protected function generateBladeComponent($module, $modelName, $modelData)
    {
        $topNav = $modelData['topNav'] ?? [];
        // Only generate Blade component if explicitly requested
        /*if (!($topNav['generateBlade'] ?? false)) {
            return;
        }*/
        
        /*$context = $topNav['context']?? '';
        $context = $context? $context."-": '';*/
        $fileName = "top-nav-links.blade.php"; // Top bar is usually a nav, so naming it accordingly

        //$bladePath = app_path("Modules/{$module}/Resources/views/components/layouts/navbars/auth/$fileName");
        // Top bar is shared among multiple pages they are housed inside the [Core] module
        $bladePath = app_path("Modules/".ucfirst($module)."/Resources/views/components/layouts/navbars/auth/$fileName");

        if (!File::exists(dirname($bladePath))) {
            File::makeDirectory(dirname($bladePath), 0755, true);
        }
        
        $stub = $this->getTopBarLinksStub($module, $modelName, $modelData);
        
        // Check if the file already exists
        if (File::exists($bladePath)) {
            $existingContent = File::get($bladePath);
            
            if (str_contains($existingContent, $stub)) {
                $this->command->info("Top nav link already exists in Blade component: {$bladePath}");
                return;
            }
            
            // Append to existing file
            File::append($bladePath, "\n" . $stub);
            $this->command->info("Top nav link appended to Blade component's view: {$bladePath}");
        } else {
            // Create new file with a proper structure
            $content = "{{-- Top Nav Links for {$module} --}}\n\n";
            $content .= $stub;
            File::put($bladePath, $content);
            $this->command->info("Top bar Blade component created: {$bladePath}");
        }
    }

    /**
     * Get the Blade stub for top nav links.
     *
     * @param string $module
     * @param string $modelName
     * @param array $modelData
     * @return string
     */
    protected function getTopBarLinksStub($module, $modelName, $modelData)
    {
        $topNav = $modelData['topNav'] ?? [];
        $iconClasses = $topNav['iconClasses'] ?? $modelData['iconClasses'] ?? 'fas fa-user';
        
        $url = $topNav['url'] ?? Str::plural(str_replace('_', '-', Str::snake($modelName)));
        $title = $topNav['title'] ?? Str::title(str_replace('_', ' ', Str::plural(Str::snake($modelName))));
        $permission = $topNav['permission'] ?? 'view_' . Str::snake($modelName);

        $topNav = $modelData['topNav'] ?? [];
        $context = $topNav['context']?? $title; // Default to title if context not provided
        $context = ucfirst($context); // Capitalize first letter
        
    return <<<BLADE
<li class="nav-item">
    <a href="/{$module}/{$url}"
        class="nav-link ">
        <i class="fas {$iconClasses}" aria-hidden="true"></i>
        <span>{$context}</span>
    </a>
</li>
BLADE;


        /*return <<<BLADE
@if(auth()->user()?->can('{$permission}'))
    <x-core.views::layouts.navbars.top-bar-link-item
        iconClasses="{$iconClasses} top-bar-icon"
        url="{$module}/{$url}"
        title="{$title}"
    />
@endif
BLADE;*/
    }
}