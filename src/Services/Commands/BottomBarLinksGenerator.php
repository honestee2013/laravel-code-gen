<?php

namespace QuickerFaster\CodeGen\Services\Commands;


use Exception;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;

class BottomBarLinksGenerator extends BaseLinksGenerator
{
    /**
     * Generate bottom bar links from the given schema data.
     *
     * @param string $module
     * @param string $modelName
     * @param array $modelData
     * @return void
     * @throws Exception
     */
    public function generateBottomBarLinks($module, $modelName, $modelData)
    {
        $bottomBar = $modelData['bottomBar'] ?? [];

        // Check if bottom bar should be added
        if (isset($bottomBar['add']) && !$bottomBar['add']) {
            return;
        }

        $fileName = "bottom_bar_menu.php";

        try {
            $bottomBarConfigPath = base_path("app/Modules/{$module}/Config/$fileName");
            $newEntry = $this->getBottomBarEntryArray($module, $modelName, $modelData);
            
            // Read existing configuration
            $existing = $this->readConfig($bottomBarConfigPath);
            
            // Check for duplicates
            if ($this->isDuplicateEntry($existing, $newEntry)) {
                $this->command->info("Bottom bar entry already exists. Skipping: {$bottomBarConfigPath}");
                return;
            }
            
            // Add the new entry
            $existing[] = $newEntry;
            
            // Write the updated configuration
            $this->writeConfig($bottomBarConfigPath, $existing);
            
            $this->command->info("Bottom bar menu updated: {$bottomBarConfigPath}");
            
            // Generate Blade component if needed
            $this->generateBladeComponent($module, $modelName, $modelData);
            
        } catch (Exception $e) {
            $this->command->error("Failed to generate bottom bar links: {$e->getMessage()}");
            throw $e;
        }
    }

    /**
     * Get the bottom bar entry array for a model.
     *
     * @param string $module
     * @param string $modelName
     * @param array $modelData
     * @return array
     */
    protected function getBottomBarEntryArray($module, $modelName, $modelData)
    {
        $bottomBar = $modelData['bottomBar'] ?? [];
        
        $icon = $bottomBar['iconClasses'] ?? $modelData['iconClasses'] ?? 'fas fa-cube';
        $url = $bottomBar['url'] ?? Str::kebab(Str::plural($modelName));
        $title = $bottomBar['title'] ?? Str::title(Str::snake(Str::plural($modelName), ' '));
        
        $entry = [
            'title' => $title,
            'icon'  => $icon,
            'url'   => "{$module}/{$url}",
            'permission' => $bottomBar['permission'] ?? 'view_' . Str::snake($modelName),
            'key' => $bottomBar['key'] ?? Str::snake($modelName),
        ];
        
        // Add itemType if specified
        if (isset($bottomBar['itemType'])) {
            $entry['itemType'] = $bottomBar['itemType'];
        }
        
        return $entry;
    }

    /**
     * Generate a Blade component for the bottom bar links.
     *
     * @param string $module
     * @param string $modelName
     * @param array $modelData
     * @return void
     */
    protected function generateBladeComponent($module, $modelName, $modelData)
    {
        $bottomBar = $modelData['bottomBar'] ?? [];
        
        // Only generate Blade component if explicitly requested
        /*if (!($bottomBar['generateBlade'] ?? false)) {
            return;
        }*/


        $sidebar = $modelData['sidebar'] ?? [];
        $context = $sidebar['context']?? '';
        $context = $context? $context."/": '';
        $fileName = $context."bottom-bar-links.blade.php";

        $bladePath = app_path("Modules/{$module}/Resources/views/components/layouts/navbars/auth/$fileName");
        

        if (!File::exists(dirname($bladePath))) {
            File::makeDirectory(dirname($bladePath), 0755, true);
        }
        
        $stub = $this->getBottomBarLinksStub($module, $modelName, $modelData);
        
        // Check if the file already exists
        if (File::exists($bladePath)) {
            $existingContent = File::get($bladePath);
            
            if (str_contains($existingContent, $stub)) {
                $this->command->info("Bottom bar link already exists in Blade component: {$bladePath}");
                return;
            }
            
            // Append to existing file
            File::append($bladePath, "\n" . $stub);
            $this->command->info("Bottom bar link appended to Blade component: {$bladePath}");
        } else {
            // Create new file with a proper structure
            $content = "{{-- Bottom Bar Links for {$module} --}}\n\n";
            $content .= $stub;
            File::put($bladePath, $content);
            $this->command->info("Bottom bar Blade component created: {$bladePath}");
        }
    }

    /**
     * Get the Blade stub for bottom bar links.
     *
     * @param string $module
     * @param string $modelName
     * @param array $modelData
     * @return string
     */
    protected function getBottomBarLinksStub($module, $modelName, $modelData)
    {
        $bottomBar = $modelData['bottomBar'] ?? [];
        $iconClasses = $bottomBar['iconClasses'] ?? $modelData['iconClasses'] ?? 'fas fa-user';
        
        $url = $bottomBar['url'] ?? Str::plural(str_replace('_', '-', Str::snake($modelName)));
        $title = $bottomBar['title'] ?? Str::title(str_replace('_', ' ', Str::plural(Str::snake($modelName))));
        $permission = $bottomBar['permission'] ?? 'view_' . Str::snake($modelName);
        $key = $bottomBar['key'] ?? Str::snake($modelName);
        

        return <<<BLADE
            <a href="{$module}/{$url}" class="btn btn-light flex-shrink-0 text-center" style="min-width:70px;" wire:navigate>
                <i class="fa {$iconClasses} }} d-block mb-1"></i>
                <small>{$title}</small>
            </a>
BLADE;

        /*return <<<BLADE
@if(auth()->user()?->can('{$permission}'))
    <a href="{$module}/{$url}" 
       class="btn btn-light flex-shrink-0 text-center" 
       style="min-width:70px;"
       wire:navigate>
        <i class="{$iconClasses} d-block mb-1"></i>
        <small>{$title}</small>
    </a>
@endif
BLADE;*/
    }
}