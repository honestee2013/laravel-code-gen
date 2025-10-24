<?php

namespace QuickerFaster\CodeGen\Services\Commands;

use Illuminate\Support\Str;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Yaml\Yaml;

class WizardGenerator extends Command
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

public function generateWizards($schemaFile, $schema)
{
    if (empty($schema['wizards'])) {
        return;
    }

    foreach ($schema['wizards'] as $wizardId => $wizardData) {
        $targetModule = $this->resolveWizardModule($wizardId, $wizardData, $schema);
        
        if (!$targetModule) {
            $this->command->error("Could not determine module for wizard: {$wizardId}. Add 'module: your_module' to wizard config.");
            continue;
        }

        $configPath = app_path("Modules/{$targetModule}/Data/wizards");
        if (!File::exists($configPath)) {
            File::makeDirectory($configPath, 0755, true);
        }

        $this->generateWizardConfig($schema, $wizardId, $wizardData, $configPath);
        $this->generateWizardBladeView($wizardId, $wizardData, $targetModule);

        $this->command->info("Wizard '{$wizardId}' generated in module '{$targetModule}'");
    }
}





protected function generateWizardBladeView($wizardId, $wizardData, $targetModule)
{
    // Convert wizard_id to kebab-case path: employee_onboarding → employee-onboarding
    // $viewName = Str::kebab($wizardId); only works on camelCase
    $viewName = str_replace('_', '-', $wizardId);
    $viewPath = app_path("Modules/{$targetModule}/Resources/views/{$viewName}.blade.php");

    // Ensure directory exists
    if (!File::exists(dirname($viewPath))) {
        File::makeDirectory(dirname($viewPath), 0755, true);
    }

    // Determine context (for sidebar/topNav)
    $context = $wizardData["context"]?? "core"; 

    $stub = $this->getWizardBladeStub($wizardId, $targetModule, $context);

    File::put($viewPath, $stub);
    $this->command->info("Wizard view created: {$viewPath}");
}



protected function getWizardBladeStub($wizardId, $module, $context)
{
    $moduleName = ucfirst($module); // 'hr' → 'Hr'
    $camelModule = Str::camel($module); // 'hr' → 'hr'

    return <<<BLADE
<x-qf::livewire.bootstrap.layouts.app>
    <x-slot name="topNav">
        <livewire:qf::layouts.navs.top-nav moduleName="{$module}" />
    </x-slot>

    <x-slot name="sidebar">
        <livewire:qf::layouts.navs.sidebar context="{$context}" moduleName="{$module}" />
    </x-slot>

    <x-slot name="bottomBar">
        <livewire:qf::layouts.navs.bottom-bar context="{$context}" moduleName="{$module}" />
    </x-slot>

    <livewire:qf::wizards.wizard-manager wizard-id="{$wizardId}" module="{$module}" />
</x-qf::livewire.bootstrap.layouts.app>
BLADE;
}










protected function resolveWizardModule($wizardId, $wizardData, $schema)
{
    // Get all unique modules from steps
    $modules = [];
    
    foreach ($wizardData['steps'] ?? [] as $step) {
        if (!empty($step['model'])) {
            $module = $this->getModelModuleFromFqcn($step['model']);
            if ($module) {
                $modules[] = $module;
            }
        }
    }
    
    $uniqueModules = array_unique($modules);
    
    // Single module wizard
    if (count($uniqueModules) === 1) {
        return $uniqueModules[0];
    }
    
    // Multi-module wizard - use first module as primary
    // or require explicit module declaration for complex cases
    if (count($uniqueModules) > 1) {
        // Option 1: Use first module (simplest)
        // return $uniqueModules[0];
        
        // Option 2: Require explicit module declaration (safer)
        if (!empty($wizardData['module'])) {
            return $wizardData['module'];
        }
        return null; // error
    }
    
    return null;
}


protected function getModelModuleFromFqcn($fqcn)
{
    // Example: App\Modules\Hr\Models\Employee
    $parts = explode('\\', $fqcn);
    
    // Find the "Modules" segment
    $modulesIndex = array_search('Modules', $parts);
    if ($modulesIndex === false || !isset($parts[$modulesIndex + 1])) {
        return null;
    }
    
    // Module name is the segment after "Modules"
    $moduleName = $parts[$modulesIndex + 1];
    return Str::camel($moduleName); // "Hr" → "hr"
}


    protected function generateWizardConfig($schema, $wizardId, $wizardData, $configPath)
    {
        $filePath = "{$configPath}/{$wizardId}.php";
        
        // Build PHP array structure
        $configData = [
            'id' => $wizardId,
            'title' => $wizardData['title'] ?? Str::title(str_replace('_', ' ', $wizardId)),
            'description' => $wizardData['description'] ?? '',
            'steps' => $this->processWizardSteps($schema, $wizardData['steps'] ?? []),
        ];

        // Add completion config if exists
        if (!empty($wizardData['completion'])) {
            $configData['completion'] = $wizardData['completion'];
        }


        // Add linkField or linkFields config if exists
        if (!empty($wizardData['linkField'])) {
            $configData['linkField'] = $wizardData['linkField'];
        }
        else if (!empty($wizardData['linkFields'])) {
            $configData['linkFields'] = $wizardData['linkFields'];
        }




        $content = "<?php\n\nreturn " . var_export($configData, true) . ";\n";
        File::put($filePath, $content);
        
        $this->command->info("Wizard config created: {$filePath}");
    }

    protected function processWizardSteps($schema, $steps)
    {
        $processedSteps = [];
        
        foreach ($steps as $step) {
            $processedStep = [
                'title' => $step['title'] ?? 'Step',
            ];

            // Convert model name to FQCN
            if (!empty($step['model'])) {
                $processedStep['model'] = $this->normalizeModelName($step['model'], $schema);
            }

            // Handle groups (backward compatibility)
            if (!empty($step['groups'])) {
                $processedStep['groups'] = $step['groups'];
            }

            // Handle inline fields (new flexible approach)
            if (!empty($step['fields'])) {
                $processedStep['fields'] = $this->processWizardFields($step['fields']);
            }

            // Handle validation
            if (!empty($step['validation'])) {
                $processedStep['validation'] = $step['validation'];
            }

            // Handle conditions
            if (!empty($step['condition'])) {
                $processedStep['condition'] = $step['condition'];
            }

            // Handle requiredEntry (your existing logic)
            if (isset($step['requiredEntry'])) {
                $processedStep['requiredEntry'] = $step['requiredEntry'];
            }

            // Handle isLinkSource
            if (!empty($step['isLinkSource'])) {
                $processedStep['isLinkSource'] = $step['isLinkSource'];
            }

            // Handle requiresLink
            if (!empty($step['requiresLink'])) {
                $processedStep['requiresLink'] = $step['requiresLink'];
            }



            $processedSteps[] = $processedStep;
        }

        return $processedSteps;
    }


    protected function normalizeModelName($modelName, $schema)
    {
        // Already FQCN?
        if (strpos($modelName, '\\') !== false) {
            return $modelName;
        }
        
        // Resolve from schema (backward compatibility)
        if (isset($schema['models'][$modelName]['module'])) {
            $module = $schema['models'][$modelName]['module'];
            $ucModule = ucfirst($module);
            return "App\\Modules\\{$ucModule}\\Models\\{$modelName}";
        }
        
        // Fallback
        return "App\\Models\\{$modelName}";
    }

    protected function processWizardFields($fields)
    {
        $processed = [];
        
        foreach ($fields as $key => $field) {
            if (is_string($key)) {
                // Named field with definition (wizard-specific field)
                $processed[$key] = $this->processFieldDefinition($field);
            } else {
                // Simple field name (from model)
                $processed[] = $field;
            }
        }

        return $processed;
    }

    protected function processFieldDefinition($fieldDef)
    {
        // Handle simple string definitions
        if (is_string($fieldDef)) {
            return ['type' => $fieldDef];
        }
        
        // Handle array definitions
        return $fieldDef;
    }
}