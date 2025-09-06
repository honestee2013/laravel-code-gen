<?php

namespace QuickerFaster\CodeGen\Services\Commands;

use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class SidebarLinksGenerator extends Command
{


    public function __construct($command = null)
    {
        $this->command = $command;
        parent::__construct();
        if ($command) {
            $this->setLaravel($command->getLaravel());
            $this->output = $command->getOutput();
        }
    }


    /**
     * Generate sidebar links from the given schema data.
     *
     * @param string $module
     * @param string $modelName
     * @param array $modelData
     * @param Command $command
     * @return void
     * @throws Exception
     */
    public function generateSidebarLinks($module, $modelName, $modelData)
    {
        $sidebar = $modelData['sidebar'] ?? [];

        // Check if sidebar should be added
        if (isset($sidebar['add']) && !$sidebar['add']) {
            return;
        }

        try {
            $sidebarConfigPath = base_path("app/Modules/{$module}/Config/sidebar_menu.php");
            $newEntry = $this->getSidebarEntryArray($module, $modelName, $modelData);
            
            // Read existing configuration
            $existing = $this->readSidebarConfig($sidebarConfigPath);
            
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
            $this->writeSidebarConfig($sidebarConfigPath, $existing);
            
            $this->command->info("Sidebar menu updated: {$sidebarConfigPath}");
            
            // Generate Blade component if needed
            $this->generateBladeComponent($module, $modelName, $modelData);
            
        } catch (Exception $e) {
            $this->command->error("Failed to generate sidebar links: {$e->getMessage()}");
            throw $e;
        }
    }

    /**
     * Read the sidebar configuration from a file.
     *
     * @param string $configPath
     * @return array
     */
    protected function readSidebarConfig($configPath)
    {
        if (File::exists($configPath)) {
            return include $configPath;
        }
        
        return [];
    }

    /**
     * Check if an entry already exists in the sidebar configuration.
     *
     * @param array $existingConfig
     * @param array $newEntry
     * @return bool
     */
    protected function isDuplicateEntry($existingConfig, $newEntry)
    {
        return collect($existingConfig)->contains(function ($entry) use ($newEntry) {
            return ($entry['title'] === $newEntry['title'] && $entry['url'] === $newEntry['url']) ||
                   (isset($entry['groupTitle']) && isset($newEntry['groupTitle']) && 
                    $entry['groupTitle'] === $newEntry['groupTitle']);
        });
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
     * Write the sidebar configuration to a file.
     *
     * @param string $configPath
     * @param array $config
     * @return void
     */
    protected function writeSidebarConfig($configPath, $config)
    {
        File::ensureDirectoryExists(dirname($configPath));
        
        $content = "<?php\n\nreturn [\n";
        
        foreach ($config as $item) {
            $content .= "    " . $this->arrayToPhpString($item) . ",\n";
        }
        
        $content .= "];\n";
        
        File::put($configPath, $content);
    }

    /**
     * Convert an array to a formatted PHP string.
     *
     * @param array $array
     * @param int $indentLevel
     * @return string
     */
    protected function arrayToPhpString($array, $indentLevel = 1)
    {
        $indent = str_repeat('    ', $indentLevel);
        $lines = [];
        
        $lines[] = '[';
        
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $lines[] = "{$indent}'{$key}' => " . $this->arrayToPhpString($value, $indentLevel + 1) . ",";
            } elseif (is_bool($value)) {
                $lines[] = "{$indent}'{$key}' => " . ($value ? 'true' : 'false') . ",";
            } elseif (is_numeric($value)) {
                $lines[] = "{$indent}'{$key}' => {$value},";
            } elseif (is_null($value)) {
                $lines[] = "{$indent}'{$key}' => null,";
            } else {
                $lines[] = "{$indent}'{$key}' => '{$value}',";
            }
        }
        
        $lines[] = str_repeat('    ', $indentLevel - 1) . ']';
        
        return implode("\n", $lines);
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
     * @param Command $command
     * @return void
     */
    protected function generateBladeComponent($module, $modelName, $modelData)
    {
        $sidebar = $modelData['sidebar'] ?? [];
        
        // Only generate Blade component if explicitly requested
        if (!($sidebar['generateBlade'] ?? false)) {
            return;
        }
        
        $bladePath = app_path("Modules/{$module}/Resources/views/components/layouts/navbars/auth/sidebar-links.blade.php");
        
        if (!File::exists(dirname($bladePath))) {
            File::makeDirectory(dirname($bladePath), 0755, true);
        }
        
        $stub = $this->getSidebarLinksStub($module, $modelName, $modelData);
        
        // Check if the file already exists
        if (File::exists($bladePath)) {
            $existingContent = File::get($bladePath);
            
            if (str_contains($existingContent, $stub)) {
                $this->command->info("Sidebar link already exists in Blade component: {$bladePath}");
                return;
            }
            
            // Append to existing file
            File::append($bladePath, "\n" . $stub);
            $this->command->info("Sidebar link appended to Blade component: {$bladePath}");
        } else {
            // Create new file with a proper structure
            $content = "{{-- Sidebar Links for {$module} --}}\n\n";
            $content .= $stub;
            File::put($bladePath, $content);
            $this->command->info("Sidebar Blade component created: {$bladePath}");
        }
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
@if(auth()->user()?->can('{$permission}'))
    <x-core.views::layouts.navbars.sidebar-link-item
        iconClasses="{$iconClasses} sidebar-icon"
        url="{$module}/{$url}"
        title="{$title}"
    />
@endif
BLADE;
    }
}