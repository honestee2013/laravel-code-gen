<?php

namespace QuickerFaster\CodeGen\Services\Commands;

use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ConfigGenerator extends Command
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
     * Generate a config file from the given schema data.
     *
     * @param string $module
     * @param string $modelName
     * @param array $modelData
     * @param Command $command
     * @return void
     * @throws Exception
     */
    public function generateConfigFile($module, $modelName, $modelData)
    {
        $configPath = app_path("Modules/{$module}/Data/" . strtolower(Str::snake($modelName)) . '.php');

        // Create directory if it doesn't exist
        if (!File::exists(dirname($configPath))) {
            File::makeDirectory(dirname($configPath), 0755, true);
        }

        // Check if config already exists and should be preserved
        if (File::exists($configPath) && !($modelData['override'] ?? false)) {
            $this->command->warn("Config already exists: {$configPath}. Use override option to regenerate.");
            return;
        }

        try {
            $configData = $this->buildConfigData($module, $modelName, $modelData);
            $configContent = $this->generateConfigContent($configData);
            
            File::put($configPath, $configContent);
            $this->command->info("Config file created: {$configPath}");
        } catch (Exception $e) {
            $this->command->error("Failed to generate config: {$e->getMessage()}");
            throw $e;
        }
    }

    /**
     * Build the configuration data array.
     *
     * @param string $module
     * @param string $modelName
     * @param array $modelData
     * @param Command $command
     * @return array
     */
    protected function buildConfigData($module, $modelName, $modelData)
    {
        $ucModule = ucfirst($module);
        
        // Load included configs
        $includedConfig = $this->loadIncludedConfigs($module, $modelData);
        
        // Build field definitions
        $fieldDefinitions = $this->buildFieldDefinitions($module, $modelName, $modelData);
        
        // Build base config
        $configData = [
            "model" => "App\\Modules\\{$ucModule}\\Models\\{$modelName}",
            "fieldDefinitions" => $fieldDefinitions,
            "hiddenFields" => $modelData['hiddenFields'] ?? [],
            "simpleActions" => $modelData['simpleActions'] ?? [],
            "isTransaction" => $modelData['isTransaction'] ?? false,
            "dispatchEvents" => $modelData['dispatchEvents'] ?? false,
            "controls" => $modelData['controls'] ?? [],
            "fieldGroups" => $modelData['fieldGroups'] ?? [],
            "moreActions" => $modelData['moreActions'] ?? [],
            "switchViews" => $modelData['switchViews'] ?? [],
            "relations" => $modelData['relations'] ?? [],
            "report" => $this->buildReportConfig($module, $modelData['report'] ?? []),
        ];
        
        // Merge with included configs (included configs have lower priority)
        return array_merge($includedConfig, $configData);
    }

    /**
     * Load included configuration files.
     *
     * @param string $module
     * @param array $modelData
     * @param Command $command
     * @return array
     */
    protected function loadIncludedConfigs($module, $modelData)
    {
        $includedConfig = [];
        $includes = $modelData['includes'] ?? [];
        
        foreach ($includes as $includeFile) {
            $includePath = app_path("Modules/{$module}/Data/{$includeFile}");
            
            if (File::exists($includePath)) {
                $includedConfig = array_merge($includedConfig, include $includePath);
            } else {
                $this->command->warn("Included file not found: {$includePath}");
            }
        }
        
        return $includedConfig;
    }

    /**
     * Build field definitions from model data.
     *
     * @param string $module
     * @param string $modelName
     * @param array $modelData
     * @param Command $command
     * @return array
     */
    protected function buildFieldDefinitions($module, $modelName, $modelData)
    {
        $fieldDefinitions = [];
        $fields = $modelData['fields'] ?? [];
        $relations = $modelData['relations'] ?? [];
        
        // Process regular fields
        foreach ($fields as $fieldName => $field) {
            if (isset($field['partial'])) {
                // Handle partial field definitions
                $partialDefinitions = $this->loadPartialFieldDefinitions($module, $field['partial']);
                $fieldDefinitions = array_merge($fieldDefinitions, $partialDefinitions);
            } else {
                // Handle regular field definition
                $fieldDefinitions[$fieldName] = $this->buildFieldDefinition($modelData, $fieldName, $field, $relations);
            }
        }
        
        // Process relationship fields
        foreach ($relations as $relationName => $relationData) {
            $fieldDefinitions = array_merge(
                $fieldDefinitions, 
                $this->buildRelationshipFieldDefinition($relationName, $relationData)
            );
        }
        
        return $fieldDefinitions;
    }

    /**
     * Load partial field definitions from a file.
     *
     * @param string $module
     * @param string $partialPath
     * @param Command $command
     * @return array
     */
    protected function loadPartialFieldDefinitions($module, $partialPath)
    {
        $fullPath = app_path("Modules/{$module}/Data/{$partialPath}");
        
        if (File::exists($fullPath)) {
            return include $fullPath;
        }
        
        $this->command->warn("Partial field definition file not found: {$fullPath}");
        return [];
    }

    /**
     * Build a single field definition.
     *
     * @param string $fieldName
     * @param array $field
     * @param array $relations
     * @return array
     */
    protected function buildFieldDefinition($modelData, $fieldName, $field, $relations)
    {
        $definition = [
            'display' => $field['display'] ?? 'inline',
            'field_type' => $this->getFieldType($field['type'] ?? 'string'),
            'label' => $field['label'] ?? $this->generateLabel($fieldName),
        ];
        
        // Add validation if present
        if (isset($field['validation'])) {
            $definition['validation'] = implode('|', $field['validation']);

        // If field_type is file 
        } else {
            $this->handleFileFieldType($modelData, $definition, $fieldName);
        }
        
        // Add options if present
        if (isset($field['options'])) {
            $definition['options'] = $this->processOptions($field['options']);
        }
        
        // Add autoGenerate if present
        if (isset($field['autoGenerate']) && $field['autoGenerate']) {
            $definition['autoGenerate'] = true;
        }
        
        // Add multiSelect if present
        if (isset($field['multiSelect']) && $field['multiSelect']) {
            $definition['multiSelect'] = true;
        }
        
        // Add reactivity if present
        if (isset($field['reactivity'])) {
            $definition['reactivity'] = $field['reactivity'];
        }
        
        // Handle foreign key relationships
        if (isset($field['foreign'])) {
            $relationshipData = $this->findRelationshipByForeignKey($fieldName, $relations);
            
            if ($relationshipData) {
                $definition = array_merge($definition, $this->buildRelationshipDefinition($relationshipData, $fieldName));
            }
        }
        
        return $definition;
    }


    protected function handleFileFieldType($modelData, &$definition, $fieldName) {
        $allowedDocumentTypes = ['pdf', 'doc', 'docx'];//, 'xls', 'xlsx', 'ppt', 'pptx', 'txt'];
        $allowedImageTypes = ['jpg', 'jpeg', 'png', 'bmp'];// 'gif', 'svg'];
        $allowedFileTypes = array_merge($allowedDocumentTypes, $allowedImageTypes);
        $allowedFileSize = $modelData['fields'][$fieldName]['maxSizeMB'] ?? 1; // default to 1MB if not specified


        $definition['maxSizeMB'] = $allowedFileSize;
        $allowedFileSize = $allowedFileSize * 1024; // convert to KB


        if (($modelData['fields'][$fieldName]['type'] ?? '') === 'file') {
            // Add default file-specific settings
            if (isset($modelData['fields'][$fieldName]['fileTypes']) && $modelData['fields'][$fieldName]['fileTypes'] === 'document') {
                $definition['fileTypes'] = $allowedDocumentTypes;
                $definition['validation'] = 'mimes:' . implode(',', $allowedDocumentTypes) . '|max:' . $allowedFileSize;

            } else if (isset($modelData['fields'][$fieldName]['fileTypes']) && $modelData['fields'][$fieldName]['fileTypes'] === 'image') {
                $definition['fileTypes'] = $allowedImageTypes;
                $definition['validation'] = 'mimes:' . implode(',', $allowedImageTypes) . '|max:' . $allowedFileSize;

            } else if (isset($modelData['fields'][$fieldName]['fileTypes']) ) {
                if (is_array($modelData['fields'][$fieldName]['fileTypes'])) {
                    $definition['validation'] = 'mimes:' . implode(',', $modelData['fields'][$fieldName]['fileTypes']) . '|max:' . $allowedFileSize;
                    $definition['fileTypes'] = $modelData['fields'][$fieldName]['fileTypes'];
                } else {
                    // Assume it's a comma-separated string & remove spaces
                    $mimes = str_replace(' ', '', $modelData['fields'][$fieldName]['fileTypes']);
                    $definition['validation'] = 'mimes:' . $mimes . '|max:' . $allowedFileSize;
                    $definition['fileTypes'] = array_map('trim', explode(',', $modelData['fields'][$fieldName]['fileTypes']));
                }
            } else {
                // Default to allowing all file types with a size limit
                $definition['fileTypes'] = $allowedFileTypes;
                $definition['validation'] = 'mimes:' . implode(',', $allowedFileTypes) . '|max:' . $allowedFileSize;
            }


        }

        // Add size definition if specified
        $definition['maxSizeMB'] = $modelData['fields'][$fieldName]['maxSizeMB'] ?? $allowedFileSize / 1024; // default to 2MB if not specified


    }




    /**
     * Get the proper field type for the config.
     *
     * @param string $type
     * @return string
     */
    protected function getFieldType($type)
    {
        // Map YAML types to appropriate HTML input types
        $typeMappings = [
            'decimal' => 'number',
            'float' => 'number',
            'int' => 'number',
            'integer' => 'number',
            'timepicker' => 'timepicker',
            'datepicker' => 'datepicker',//'date',
            'datetimepicker' => 'datetimepicker',//'datetime-local',
        ];
        
        return $typeMappings[$type] ?? $type;
    }

    /**
     * Generate a label from a field name.
     *
     * @param string $fieldName
     * @return string
     */
    protected function generateLabel($fieldName)
    {
        $label = str_replace("_id", "", $fieldName);
        $label = str_replace("_", " ", $label);
        return Str::title($label);
    }

    /**
     * Process options for select fields.
     *
     * @param mixed $options
     * @return array
     */
    protected function processOptions($options)
    {
        if (is_array($options)) {
            return $options;
        }
        
        // Convert comma-separated string to array
        $optionsArray = array_map('trim', explode(',', $options));
        return array_combine($optionsArray, $optionsArray);
    }

    /**
     * Find relationship data by foreign key.
     *
     * @param string $foreignKey
     * @param array $relations
     * @return array|null
     */
    protected function findRelationshipByForeignKey($foreignKey, $relations)
    {
        foreach ($relations as $relationData) {
            if (($relationData['foreignKey'] ?? null) === $foreignKey) {
                return $relationData;
            }
        }
        
        return null;
    }

    /**
     * Build relationship definition for a field.
     *
     * @param array $relationData
     * @param string $fieldName
     * @return array
     */
    protected function buildRelationshipDefinition($relationData, $fieldName)
    {
        $relationshipType = $relationData['type'] ?? 'belongsTo';
        $relatedModel = $relationData['model'] ?? '';
        $displayField = $relationData['displayField'] ?? 'name';
        $hintField = $relationData['hintField'] ?? null;
        $inlineAdd = $relationData['inlineAdd'] ?? false;
        $dynamicProperty = $this->getDynamicProperty($relationshipType, $fieldName);
        
        return [
            'relationship' => [
                'model' => $relatedModel,
                'type' => $relationshipType,
                'display_field' => $displayField,
                'dynamic_property' => $dynamicProperty,
                'foreign_key' => $fieldName,
                'inlineAdd' => $inlineAdd,
            ],
            'options' => [
                'model' => $relatedModel,
                'column' => $displayField,
                'hintField' => $hintField,
            ]
        ];
    }

    /**
     * Build relationship field definitions.
     *
     * @param string $relationName
     * @param array $relationData
     * @param Command $command
     * @return array
     */
    protected function buildRelationshipFieldDefinition($relationName, $relationData)
    {
        $relationshipType = $relationData['type'] ?? 'belongsTo';
        $relatedModel = $relationData['model'] ?? '';
        $displayField = $relationData['displayField'] ?? 'name';
        $hintField = $relationData['hintField'] ?? null;
        $inlineAdd = $relationData['inlineAdd'] ?? false;
        $foreignKey = $relationData['foreignKey'] ?? null;
        $display = $relationData['display'] ?? 'inline';
        
        $definition = [];
        
        switch ($relationshipType) {
            case 'hasMany':
            case 'belongsToMany':
                $definition[$relationName] = [
                    'field_type' => 'checkbox',
                    'relationship' => [
                        'model' => $relatedModel,
                        'type' => $relationshipType,
                        'display_field' => $displayField,
                        'hintField' => $hintField,
                        'dynamic_property' => $relationName,
                        'foreign_key' => $foreignKey,
                        'local_key' => 'id',
                        'inlineAdd' => $inlineAdd,
                    ],
                    'options' => [
                        'model' => $relatedModel,
                        'column' => $displayField,
                        'hintField' => $hintField,
                    ],
                    'label' => Str::title($relationName),
                    'multiSelect' => true,
                    'display' => $display,
                ];
                break;
                
            case 'morphTo':
                $definition[$relationName] = [
                    'field_type' => 'morphTo',
                    'relationship' => [
                        'model' => $relatedModel,
                        'type' => $relationshipType,
                        'dynamic_property' => $relationName,
                    ],
                    'label' => Str::title($relationName),
                    'display' => $display,
                ];
                break;
                
            case 'morphToMany':
                $pivotTable = $relationData['pivotTable'] ?? '';
                $relatedPivotKey = $relationData['relatedPivotKey'] ?? '';
                $morphType = $relationData['morphType'] ?? '';
                
                $definition[$relationName] = [
                    'field_type' => 'morphToMany',
                    'relationship' => [
                        'model' => $relatedModel,
                        'type' => $relationshipType,
                        'display_field' => $displayField,
                        'dynamic_property' => $relationName,
                        'foreign_key' => $foreignKey,
                        'related_pivot_key' => $relatedPivotKey,
                        'morph_type' => $morphType,
                        'pivot_table' => $pivotTable,
                        'inlineAdd' => $inlineAdd,
                    ],
                    'options' => [
                        'model' => $relatedModel,
                        'column' => $displayField,
                        'hintField' => $hintField,
                    ],
                    'label' => Str::title($relationName),
                    'multiSelect' => true,
                    'display' => $display,
                ];
                break;
        }
        
        return $definition;
    }

    /**
     * Build report configuration.
     *
     * @param string $module
     * @param array $reportData
     * @return array
     */
    protected function buildReportConfig($module, $reportData)
    {
        if (empty($reportData)) {
            return [];
        }
        
        // Ensure model paths are properly formatted
        if (isset($reportData['model'])) {
            $reportData['model'] = "App\\Modules\\{$module}\\Models\\{$reportData['model']}";
        }
        
        if (isset($reportData['itemsModel'])) {
            $reportData['itemsModel'] = "App\\Modules\\{$module}\\Models\\{$reportData['itemsModel']}";
        }
        
        if (isset($reportData['recordModel'])) {
            $reportData['recordModel'] = "App\\Modules\\{$module}\\Models\\{$reportData['recordModel']}";
        }
        
        return $reportData;
    }

    /**
     * Generate the PHP configuration content.
     *
     * @param array $configData
     * @return string
     */
    protected function generateConfigContent($configData)
    {
        $content = "<?php\n\nreturn [\n";
        $content .= $this->arrayToPhpString($configData, 1);
        $content .= "];\n";
        
        return $content;
    }

    /**
     * Convert an array to a formatted PHP string.
     *
     * @param array $array
     * @param int $indentLevel
     * @return string
     */
    protected function arrayToPhpString($array, $indentLevel = 0)
    {
        $indent = str_repeat('  ', $indentLevel);
        $lines = [];
        
        foreach ($array as $key => $value) {
            $line = "{$indent}'{$key}' => ";
            
            if (is_array($value)) {
                if (empty($value)) {
                    $line .= '[],';
                } else {
                    $line .= "[\n";
                    $line .= $this->arrayToPhpString($value, $indentLevel + 1);
                    $line .= "{$indent}],";
                }
            } elseif (is_bool($value)) {
                $line .= $value ? 'true,' : 'false,';
            } elseif (is_numeric($value)) {
                $line .= "{$value},";
            } else {
                $line .= "'{$value}',";
            }
            
            $lines[] = $line;
        }
        
        return implode("\n", $lines) . "\n";
    }

    /**
     * Get the dynamic property name for a relationship.
     *
     * @param string $relationshipType
     * @param string $fieldName
     * @return string
     */
    private function getDynamicProperty($relationshipType, $fieldName)
    {
        $fieldName = str_replace("_id", "", $fieldName);
        $fieldName = Str::camel($fieldName);
        
        if ($relationshipType === "hasMany" || $relationshipType === "belongsToMany") {
            $fieldName = Str::plural($fieldName);
        }
        
        return $fieldName;
    }
}