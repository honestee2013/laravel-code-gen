<?php

namespace QuickerFaster\CodeGen\Services\Commands;

use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class BladeGenerator extends Command
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

    /**
     * Generate a blade file from the given schema data.
     *
     * @param string $module
     * @param string $modelName
     * @param array $modelData
     * @param Command $command
     * @return void
     * @throws Exception
     */
    public function generateBladeFile($module, $modelName, $modelData)
    {
        //$module = ucfirst($module);
        try {
            if (isset($modelData['tab'])) {
                $this->generateTabbedView($module, $modelName, $modelData);
            } else {
                $this->generateStandardView($module, $modelName, $modelData);
            }

            // Always add dashboard blade view
            $this->generateDashboardView($module, $modelName, $modelData);

        } catch (Exception $e) {
            $this->command->error("Failed to generate blade file: {$e->getMessage()}");
            throw $e;
        }
    }

    /**
     * Generate a tabbed view.
     *
     * @param string $module
     * @param string $modelName
     * @param array $modelData
     * @param Command $command
     * @return void
     */
    protected function generateTabbedView($module, $modelName, $modelData)
    {
        $modelData['tab'] = $this->initializeTabParameters($modelData['tab'], $modelName);
        
        // Generate tab bar links
        $this->generateTabBarLinks($module, $modelName, $modelData);
        
        $tab = $modelData['tab'];
        if (!$tab) return;
        
        $viewDir = app_path("Modules/{$module}/Resources/views/");
        $viewPath = $viewDir . $tab['view'] . '.blade.php';
        
        // Create directory if it doesn't exist
        if (!File::exists(dirname($viewPath))) {
            File::makeDirectory(dirname($viewPath), 0755, true);
        }
        
        $stub = $this->getBladeStub($module, $modelName, $modelData, $tab['id']);
        File::put($viewPath, $stub);
        
        $this->command->info("Blade view created: {$viewPath}");
    }

    /**
     * Generate a standard view (without tabs).
     *
     * @param string $module
     * @param string $modelName
     * @param array $modelData
     * @param Command $command
     * @return void
     */
    protected function generateStandardView($module, $modelName, $modelData)
    {
        $viewPath = app_path("Modules/{$module}/Resources/views/" . 
            Str::plural(strtolower(Str::kebab($modelName))) . '.blade.php');
        
        // Create directory if it doesn't exist
        if (!File::exists(dirname($viewPath))) {
            File::makeDirectory(dirname($viewPath), 0755, true);
        }
        
        $stub = $this->getBladeStub($module, $modelName, $modelData);
        File::put($viewPath, $stub);
        
        $this->command->info("Blade view created: {$viewPath}");
    }




    protected function generateDashboardView($module, $modelName, $modelData) {
        $viewPath = app_path("Modules/".ucfirst($module)."/Resources/views/dashboard.blade.php");
        
        // Create directory if it doesn't exist
        if (!File::exists(dirname($viewPath))) {
            File::makeDirectory(dirname($viewPath), 0755, true);
        }
        

    $stub = "<x-qf::livewire.bootstrap.layouts.app>
    <x-slot name=\"topNav\">
        <livewire:qf::layouts.navs.top-nav moduleName=\"$module\">
    </x-slot>



    <livewire:qf::dashboards.dashboard-manager moduleName=\"$module\" />

</x-qf::livewire.bootstrap.layouts.app>";
        File::put($viewPath, $stub);
        
        $this->command->info("Dashboard blade view created: {$viewPath}");
    }





    /**
     * Get the blade stub content with all replacements.
     *
     * @param string $module
     * @param string $modelName
     * @param array $modelData
     * @param string|null $activeTab
     * @return string
     */
    protected function getBladeStub($module, $modelName, $modelData, $activeTab = null)
    {
        $stubPath = __DIR__ . '/../../Stubs/new-view.blade.stub';
        
        if (!File::exists($stubPath)) {
            throw new Exception("Blade new stub view not found: {$stubPath}");
        }
        
        $stub = File::get($stubPath);
        
        // Prepare all replacements
        $replacements = [

            ////// New view layout essentials /////
            '{{sidebar}}' => $this->getSidebar($module, $modelData),
            '{{topNav}}' => $this->getTopNav($module, $modelData),
            '{{bottomBar}}' => '', //$this->getBottomBar($module, $modelData),
            '{{pageContext}}' => ucfirst($this->getPageContext($modelName, $modelData)),
            '{{livewireComponent}}' => $this->getLivewireComponent($module, $modelName, $modelData),


            // These may not be needed anymore
            '{{pageTitle}}' => $this->getPageTitle($modelName, $modelData),
            '{{hiddenFields}}' => $this->getHiddenFields($modelData),
            '{{queryFilters}}' => $this->getQueryFilters($modelData),
            '{{tabBarLinks}}' => $this->getTabBarLinks($module, $modelData),
            '{{header}}' => $this->getHeader($module, $modelData),
            '{{footer}}' => $this->getFooter($module, $modelData),
            
            '{{module}}' => strtolower($module),
            '{{modelName}}' => $modelName,
        ];
        
        return str_replace(array_keys($replacements), array_values($replacements), $stub);
    }

    /**
     * Get the page title.
     *
     * @param string $modelName
     * @param array $modelData
     * @return string
     */
    protected function getPageTitle($modelName, $modelData)
    {   
        $pageTitle = $modelData['pageTitle'] ?? null;
        if ($pageTitle) {
            return $pageTitle;
        }
        return Str::plural(Str::title(str_replace('_', ' ', Str::snake($modelName))));// . " Management";
    }



        /**
     * Get the page title.
     *
     * @param string $modelName
     * @param array $modelData
     * @return string
     */
    protected function getPageContext($modelName, $modelData)
    {   
        $pageContext = $modelData['context'] ?? null;
        
        if ($pageContext) {
            return $pageContext;
        }
        return Str::plural(Str::title(str_replace('_', ' ', Str::snake($modelName))));// . " Management";
    }



        /**
     * Get the page title.
     *
     * @param string $modelName
     * @param array $modelData
     * @return string
     */
    protected function getTabsPageTitle($modelName, $modelData)
    {
        if (isset($modelData['tab'])) {
            return $modelData['tab']['pageTitle'] ?? 
                Str::plural(Str::title(str_replace('_', ' ', Str::snake($modelName)))) . " Management";
        }
        
        return Str::plural(Str::title(str_replace('_', ' ', Str::snake($modelName)))) . " Management";
    }

    /**
     * Get the hidden fields as a string.
     *
     * @param array $modelData
     * @return string
     */
    protected function getHiddenFields($modelData)
    {
        $hiddenFields = $modelData['hiddenFields'] ?? [];
        return $this->arrayToBladeString($hiddenFields);
    }

    /**
     * Get the query filters as a string.
     *
     * @param array $modelData
     * @return string
     */
    protected function getQueryFilters($modelData)
    {
        $queryFilters = $modelData['queryFilters'] ?? [];
        return $this->arrayToBladeString($queryFilters);
    }

    /**
     * Convert an array to a Blade-friendly string.
     *
     * @param array $array
     * @return string
     */
    protected function arrayToBladeString($array)
    {
        if (empty($array)) {
            return '[]';
        }
        
        $string = "[\n";
        
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $string .= "    '{$key}' => " . $this->arrayToBladeString($value) . ",\n";
            } else {
                $string .= "    '{$key}' => '{$value}',\n";
            }
        }
        
        $string .= "]";
        
        return $string;
    }

    /**
     * Get the tab bar links.
     *
     * @param string $module
     * @param array $modelData
     * @return string
     */
    protected function getTabBarLinks($module, $modelData)
    {
        if (!isset($modelData['tab'])) {
            return '';
        }
        
        $tab = $this->initializeTabParameters($modelData['tab'], $modelData['modelName'] ?? '');
        
        return 
<<<HTML
<x-core.views::tab-bar>
        <x-{$module}.views::layouts.navbars.auth.{$tab['group']}-tab-bar-links active='{$tab['id']}' />
    </x-core.views::tab-bar>
HTML;

    }

    /**
     * Get the Livewire component.
     *
     * @param string $module
     * @param string $modelName
     * @param array $modelData
     * @return string
     */
    protected function getLivewireComponent($module, $modelName, $modelData)
    {
        $pageTitle = $this->getPageTitle($modelName, $modelData);
        $hiddenFields = $this->getHiddenFields($modelData);
        $queryFilters = $this->getQueryFilters($modelData);
       
        $module = ucfirst($module);

return 
<<<HTML
<livewire:qf::data-tables.data-table-manager :selectedItemId="\$id??null" model="App\\Modules\\{$module}\\Models\\{$modelName}"
            pageTitle="{$pageTitle}"
            queryFilters=[]
            :hiddenFields="{$hiddenFields}"
            :queryFilters="{$queryFilters}"
        />
HTML;
       

    }

    /**
     * Get the header slot.
     *
     * @param string $module
     * @param array $modelData
     * @return string
     */
    protected function getHeader($module, $modelData)
    {
        $includeHeader = $modelData['includeHeader'] ?? false;
        
        if (!$includeHeader) {
            return '';
        }
        
        $parentPageTitle = $modelData['tab']['parentPageTitle'] ?? 
            Str::plural(Str::title(str_replace('_', ' ', Str::snake($modelData['modelName'] ?? '')))) . " Management";
        
        return 
<<<HTML
<x-slot name="pageHeader">
        @include('core.views::components.layouts.navbars.auth.content-header', [ "pageTitile" => "{$parentPageTitle}"])
    </x-slot>
HTML;
    }

    /**
     * Get the footer slot.
     *
     * @param string $module
     * @param array $modelData
     * @return string
     */
    protected function getFooter($module, $modelData)
    {
        $includeFooter = $modelData['includeFooter'] ?? false;
        
        if (!$includeFooter) {
            return '';
        }
        
        return 
<<<HTML
<x-slot name="pageFooter">
        @include('core.views::components.layouts.navbars.auth.content-footer', [ ])
    </x-slot>
HTML;
    }

    /**
     * Get the sidebar slot.
     *
     * @param string $module
     * @param array $modelData
     * @return string
     */
    protected function getSidebar($module, $modelData)
    {
        $includeSidebar = $modelData['includeSidebar'] ?? true; // Include by default
    
        if (!$includeSidebar) {
            return '';
        }

        
        $sidebar = $modelData['sidebar'] ?? [];
        $context = strtolower($sidebar['context'])?? ''; // make context folder lowercase


return <<<HTML
<x-slot name="sidebar">
        <livewire:qf::layouts.navs.sidebar context="{$context}"  moduleName="{$module}">
    </x-slot>
HTML;
        
        /*return 
<<<HTML
<x-slot name="sidebar">
        <x-core.views::layouts.navbars.auth.sidebar moduleName="{$module}">
        </x-core.views::layouts.navbars.auth.sidebar>
    </x-slot>
HTML;*/
    }


    /**
     * Get the bottom bar slot.
     *
     * @param string $module
     * @param array $modelData
     * @return string
     */
    protected function getBottomBar($module, $modelData)
    {
        $includeSidebar = $modelData['includeSidebar'] ?? true; // Include by default if sidebar is present
    
        if (!$includeSidebar) {
            return '';
        }

        $sidebar = $modelData['sidebar'] ?? []; // Bottom bar context should match sidebar context
        $context = strtolower($sidebar['context'])?? ''; // make context folder lowercase




return <<<HTML
<x-slot name="bottomBar">
        <livewire:qf::layouts.navs.bottom-bar context="{$context}" moduleName="{$module}">
    </x-slot>
HTML;
    }


    /**
     * Get the top nav slot.
     *
     * @param string $module
     * @param array $modelData
     * @return string
     */
    protected function getTopNav($module, $modelData)
    {
        /*$includeSidebar = $modelData['includeSidebar'] ?? true; // Include by default if sidebar is present
    
        if (!$includeSidebar) {
            return '';
        }*/
            

            // Always include the top bar
return  <<<HTML
<x-slot name="topNav">
        <livewire:qf::layouts.navs.top-nav moduleName="{$module}">
    </x-slot>
HTML;
    }



    /**
     * Initialize tab parameters with defaults.
     *
     * @param array $tab
     * @param string $modelName
     * @return array
     */
    protected function initializeTabParameters($tab, $modelName)
    {
        $pageTitle = Str::plural(Str::title(str_replace('_', ' ', $modelName))) . " Management";
        
        return [
            'parentPageTitle' => $tab['parentPageTitle'] ?? $pageTitle,
            'pageTitle' => $tab['pageTitle'] ?? $tab['parentPageTitle'] ?? $pageTitle,
            'group' => $tab['group'] ?? strtolower(Str::plural(Str::kebab($modelName))),
            'view' => $tab['view'] ?? $tab['group'] ?? strtolower(Str::plural(Str::kebab($modelName))),
            'url' => $tab['url'] ?? $tab['view'] ?? strtolower(Str::plural(Str::kebab($modelName))),
            'id' => $tab['id'] ?? $tab['url'] ?? strtolower(Str::plural(Str::kebab($modelName))),
            'label' => $tab['label'] ?? $modelName,
        ];
    }

    /**
     * Generate tab bar links.
     *
     * @param string $module
     * @param string $modelName
     * @param array $modelData
     * @param Command $command
     * @return void
     */
    protected function generateTabBarLinks($module, $modelName, $modelData)
    {
        $tabGroup = strtolower(Str::kebab($modelData['tab']['group'] ?? $modelName));
        $tabBarLinksPath = app_path("Modules/{$module}/Resources/views/components/layouts/navbars/auth/" . 
            $tabGroup . '-tab-bar-links.blade.php');
        
        // Create directory if it doesn't exist
        if (!File::exists(dirname($tabBarLinksPath))) {
            File::makeDirectory(dirname($tabBarLinksPath), 0755, true);
        }
        
        $stub = $this->getTabBarLinksStub($modelData);
        
        // Check if the file already exists and contains our stub
        if (File::exists($tabBarLinksPath)) {
            $existingContent = File::get($tabBarLinksPath);
            
            if (str_contains($existingContent, $stub)) {
                $this->command->info("Tabbar link already exists in: {$tabBarLinksPath}. Skipping.");
                return;
            }
            
            // Append to existing file
            File::append($tabBarLinksPath, "\n" . $stub);
            $this->command->info("Tabbar link appended to: {$tabBarLinksPath}.");
        } else {
            // Create new file
            File::put($tabBarLinksPath, $stub);
            $this->command->info("Tab bar links file created: {$tabBarLinksPath}");
        }
    }

    /**
     * Get the tab bar links stub.
     *
     * @param array $modelData
     * @return string
     */
    protected function getTabBarLinksStub($modelData)
    {
        $tab = $modelData['tab'] ?? null;
        
        if (!$tab) {
            return '';
        }
        
        $iconClasses = $modelData['tab']['iconClasses'] ?? $modelData['iconClasses'] ?? 'fa-user';
        
        return 
<<<HTML
<x-core.views::layouts.navbars.sidebar-link-item
    iconClasses="{$iconClasses}"
    url="{$tab['url']}"
    title="{$tab['label']}"
    anchorClasses="{{ (\$active == '{$tab['id']}') ? 'active' : '' }}"
/>
HTML;

    }





}