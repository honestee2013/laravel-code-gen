<?php

namespace QuickerFaster\CodeGen\Services\Commands;

use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ModelGenerator extends Command
{

    public function __construct($command = null)
    {
        parent::__construct();
        if ($command) {
            $this->setLaravel($command->getLaravel());
            $this->output = $command->getOutput();
        }
    }


    /**
     * Generate a model from the given schema data.
     *
     * @param string $module
     * @param string $modelName
     * @param array $modelData
     * @return void
     * @throws Exception
     */
    public function generateModel($module, $modelName, $modelData)
    {
        $modelPath = app_path("Modules/{$module}/Models/{$modelName}.php");

        // Create directory if it doesn't exist
        if (!File::exists(dirname($modelPath))) {
            File::makeDirectory(dirname($modelPath), 0755, true);
        }

        // Check if model already exists and should be preserved
        if (File::exists($modelPath) && !($modelData['override'] ?? false)) {
            $this->warn("Model already exists: {$modelPath}. Use override option to regenerate.");
            return;
        }

        try {
            $stub = $this->getModelStub($module, $modelName, $modelData);
            File::put($modelPath, $stub);
            $this->output->info("Model created: {$modelPath}");
        } catch (Exception $e) {
            $this->error("Failed to generate model: {$e->getMessage()}");
            throw $e;
        }
    }

    /**
     * Get the model stub content with all replacements.
     *
     * @param string $module
     * @param string $modelName
     * @param array $modelData
     * @return string
     * @throws Exception
     */
    protected function getModelStub($module, $modelName, $modelData)
    {
        $stub = $this->getStubContent($module, $modelName, $modelData);
        
        // Perform all replacements
        $replacements = [
            '{{namespace}}' => $this->getNamespace($module),
            '{{module}}' => $module,
            '{{modelName}}' => $modelName,
            '{{tableName}}' => $this->getTableName($modelName, $modelData),
            '{{fillable}}' => $this->getFillableProperties($modelData),
            '{{guarded}}' => $this->getGuardedProperties($modelData),
            '{{casts}}' => $this->getCasts($modelData),
            '{{events}}' => $this->getEvents($modelData),
            '{{rules}}' => $this->getRules($modelData),
            '{{messages}}' => $this->getMessages($modelData),
            '{{bootMethods}}' => $this->getBootMethods($modelData),
            '{{relations}}' => $this->getRelationMethods($modelData),
            '{{imports}}' => $this->getImports($modelData),
            '{{traitImports}}' => $this->getTraitImports($modelData),
            '{{traitUses}}' => $this->getTraitUses($modelData),
            '{{softDeletes}}' => $this->getSoftDeletes($modelData),
            '{{displayFields}}' => $this->getDisplayFields($modelData),
            '{{primaryKey}}' => $this->getPrimaryKey($modelData),
            '{{incrementing}}' => $this->getIncrementing($modelData),
            '{{keyType}}' => $this->getKeyType($modelData),
            '{{timestamps}}' => $this->getTimestamps($modelData),
            '{{dateFormat}}' => $this->getDateFormat($modelData),
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $stub);
    }

    /**
     * Get the appropriate stub content.
     *
     * @param string $module
     * @param string $modelName
     * @param array $modelData
     * @return string
     * @throws Exception
     */
    protected function getStubContent($module, $modelName, $modelData)
    {
        // Check for module-specific stub
        $moduleStubPath = app_path("Modules/{$module}/Stubs/Models/{$modelName}.stub");
        
        if (File::exists($moduleStubPath)) {
            return File::get($moduleStubPath);
        }
        
        // Check for module-specific default stub
        $moduleDefaultStub = app_path("Modules/{$module}/Stubs/Models/model.stub");
        
        if (File::exists($moduleDefaultStub)) {
            return File::get($moduleDefaultStub);
        }
        
        // Use core stub
        $coreStubPath = __DIR__ . '/../../Stubs/model.stub';
        
        if (!File::exists($coreStubPath)) {
            throw new Exception("Model stub not found: {$coreStubPath}");
        }
        
        return File::get($coreStubPath);
    }

    /**
     * Get the namespace for the model.
     *
     * @param string $module
     * @return string
     */
    protected function getNamespace($module)
    {
        return "App\\Modules\\" . ucfirst($module) . "\\Models";
    }

    /**
     * Get the table name for the model.
     *
     * @param string $modelName
     * @param array $modelData
     * @return string
     */
    protected function getTableName($modelName, $modelData)
    {
        if (isset($modelData['table'])) {
            return "protected \$table = '{$modelData['table']}';";
        }
        
        $isPivot = $modelData['isPivot'] ?? false;
        $tableName = $isPivot ? 
            Str::snake(Str::singular($modelName)) : 
            Str::snake(Str::plural($modelName));
            
        return "protected \$table = '{$tableName}';";
    }

    /**
     * Get the fillable properties for the model.
     *
     * @param array $modelData
     * @return string
     */
    protected function getFillableProperties($modelData)
    {
        $fillable = [];
        $fields = $modelData['fields'] ?? [];
        $guarded = $modelData['guarded'] ?? [];
        
        // Add all fields that aren't guarded
        foreach ($fields as $fieldName => $fieldData) {
            // Check if this field is marked as a foreign key
            $isForeign = isset($fieldData['foreign']);

            // By default, fields are fillable unless explicitly set to false
            $isFillable = $fieldData['fillable'] ?? true;

            // Only add to fillable if:
            // - the field is not in the guarded list
            // - AND (it is fillable, OR it is a foreign key explicitly set as fillable)
            if (!in_array($fieldName, $guarded) && ($isFillable || ($isForeign && $isFillable))) {
                $fillable[] = "'{$fieldName}'";
            }
        }
  
        // Add explicitly defined fillable fields
        if (isset($modelData['fillable'])) {
            foreach ($modelData['fillable'] as $field) {
                $fillable[] = "'{$field}'";
            }
        }
        
        return implode(', ', array_unique($fillable));
    }

    /**
     * Get the guarded properties for the model.
     *
     * @param array $modelData
     * @return string
     */
    protected function getGuardedProperties($modelData)
    {
        $guarded = $modelData['guarded'] ?? [];
        
        // Add explicitly defined guarded fields
        if (isset($modelData['guarded'])) {
            foreach ($modelData['guarded'] as $field) {
                $guarded[] = "'{$field}'";
            }
        }
        
        return implode(', ', array_unique($guarded));
    }

    /**
     * Get the casts for the model.
     *
     * @param array $modelData
     * @return string
     */
    protected function getCasts($modelData)
    {
        $casts = [];
        $fields = $modelData['fields'] ?? [];
        
        foreach ($fields as $fieldName => $fieldData) {
            $type = $fieldData['type'] ?? 'string';
            
            // Map YAML types to Laravel cast types
            $castType = $this->getCastType($type, $fieldData);
            
            if ($castType) {
                $casts[] = "'{$fieldName}' => '{$castType}'";
            }
        }
        
        if (empty($casts)) {
            return '';
        }
        
        return implode(",\n        ", $casts);
    }

    /**
     * Map YAML field type to Laravel cast type.
     *
     * @param string $type
     * @param array $fieldData
     * @return string|null
     */
    protected function getCastType($type, $fieldData)
    {
        switch ($type) {
            case 'boolean':
            case 'bool':
            case 'checkbox':
                return 'boolean';
                
            case 'integer':
            case 'int':
                return 'integer';
                
            case 'decimal':
            case 'float':
            case 'double':
                $precision = $fieldData['modifiers']['precision'] ?? '8,2';
                // precision might be "8,2" or just "2"
                $parts = explode(',', $precision);
                $scale = $parts[1] ?? $parts[0]; // use second part if available, else first
                return 'decimal:' . $scale;
                
            case 'array':
                return 'array';
                
            case 'json':
                return 'json';
                
            case 'date':
            case 'datepicker':
                return 'date';
                
            case 'datetime':
            case 'timestamp':
                return 'datetime';
                
            default:
                return null;
        }
    }

    /**
     * Get the events for the model.
     *
     * @param array $modelData
     * @return string
     */
    protected function getEvents($modelData)
    {
        $events = $modelData['events'] ?? [];
        $lines = [];
        
        foreach ($events as $event => $handler) {
            $lines[] = "'{$event}' => {$handler},";
        }
        
        return implode("\n        ", $lines);
    }

    /**
     * Get the validation rules for the model.
     *
     * @param array $modelData
     * @return string
     */
    protected function getRules($modelData)
    {
        $rules = $modelData['rules'] ?? [];
        $lines = [];
        
        foreach ($rules as $field => $rule) {
            $lines[] = "'{$field}' => '{$rule}',";
        }
        
        return implode("\n        ", $lines);
    }

    /**
     * Get the validation messages for the model.
     *
     * @param array $modelData
     * @return string
     */
    protected function getMessages($modelData)
    {
        $messages = $modelData['messages'] ?? [];
        $lines = [];
        
        foreach ($messages as $key => $message) {
            $lines[] = "'{$key}' => '{$message}',";
        }
        
        return implode("\n        ", $lines);
    }

    /**
     * Get the boot methods for the model.
     *
     * @param array $modelData
     * @return string
     */
    protected function getBootMethods($modelData)
    {
        $bootMethods = $modelData['bootMethods'] ?? [];
        return implode("\n        ", $bootMethods);
    }

    /**
     * Get the relationship methods for the model.
     *
     * @param array $modelData
     * @return string
     */
    protected function getRelationMethods($modelData)
    {
        $relations = $modelData['relations'] ?? [];
        $methods = '';
        
        foreach ($relations as $relationName => $relationData) {
            $methods .= $this->generateRelationMethod($relationName, $relationData) . "\n\n";
        }
        
        return trim($methods);
    }

    /**
     * Generate a relationship method.
     *
     * @param string $relationName
     * @param array $relationData
     * @return string
     */
    protected function generateRelationMethod($relationName, $relationData)
    {
        $type = $relationData['type'];
        $method = '';
        
        switch ($type) {
            case 'belongsTo':
                $method = $this->generateBelongsToMethod($relationName, $relationData);
                break;
                
            case 'hasOne':
                $method = $this->generateHasOneMethod($relationName, $relationData);
                break;
                
            case 'hasMany':
                $method = $this->generateHasManyMethod($relationName, $relationData);
                break;
                
            case 'belongsToMany':
                $method = $this->generateBelongsToManyMethod($relationName, $relationData);
                break;
                
            case 'morphTo':
                $method = $this->generateMorphToMethod($relationName, $relationData);
                break;
                
            case 'morphOne':
                $method = $this->generateMorphOneMethod($relationName, $relationData);
                break;
                
            case 'morphMany':
                $method = $this->generateMorphManyMethod($relationName, $relationData);
                break;
                
            case 'morphToMany':
                $method = $this->generateMorphToManyMethod($relationName, $relationData);
                break;
                
            case 'morphedByMany':
                $method = $this->generateMorphedByManyMethod($relationName, $relationData);
                break;
        }
        
        return $method;
    }

    /**
     * Generate a belongsTo relationship method.
     *
     * @param string $relationName
     * @param array $relationData
     * @return string
     */
    protected function generateBelongsToMethod($relationName, $relationData)
    {
        $relatedModel = $relationData['model'];
        $foreignKey = $relationData['foreignKey'] ?? Str::snake($relationName) . '_id';
        $ownerKey = $relationData['ownerKey'] ?? 'id';
        
        return <<<METHOD
    public function $relationName()
    {
        return \$this->belongsTo(\\$relatedModel::class, '$foreignKey', '$ownerKey');
    }
METHOD;
    }





    // Other relationship methods would be implemented similarly


    /**
     * Generate a hasOne relationship method.
     *
     * @param string $relationName
     * @param array $relationData
     * @return string
     */
    protected function generateHasOneMethod($relationName, $relationData)
    {
        $relatedModel = $relationData['model'];
        $foreignKey = $relationData['foreignKey'] ?? Str::snake(class_basename($this)) . '_id';
        $localKey = $relationData['localKey'] ?? 'id';
        
        return <<<METHOD
    public function $relationName()
    {
        return \$this->hasOne(\\$relatedModel::class, '$foreignKey', '$localKey');
    }
METHOD;
    }

    /**
     * Generate a hasMany relationship method.
     *
     * @param string $relationName
     * @param array $relationData
     * @return string
     */
    protected function generateHasManyMethod($relationName, $relationData)
    {
        $relatedModel = $relationData['model'];
        $foreignKey = $relationData['foreignKey'] ?? Str::snake(class_basename($this)) . '_id';
        $localKey = $relationData['localKey'] ?? 'id';
        
        return <<<METHOD
    public function $relationName()
    {
        return \$this->hasMany(\\$relatedModel::class, '$foreignKey', '$localKey');
    }
METHOD;
    }

    /**
     * Generate a belongsToMany relationship method.
     *
     * @param string $relationName
     * @param array $relationData
     * @return string
     */
    protected function generateBelongsToManyMethod($relationName, $relationData)
    {
        $relatedModel = $relationData['model'];
        $table = $relationData['pivotTable'] ?? null;
        $foreignPivotKey = $relationData['foreignPivotKey'] ?? Str::snake(class_basename($this)) . '_id';
        $relatedPivotKey = $relationData['relatedPivotKey'] ?? Str::snake(class_basename($relatedModel)) . '_id';
        $parentKey = $relationData['parentKey'] ?? 'id';
        $relatedKey = $relationData['relatedKey'] ?? 'id';
        
        $method = "    public function $relationName()\n    {\n";
        $method .= "        return \$this->belongsToMany(\\$relatedModel::class";
        
        if ($table) {
            $method .= ", '$table'";
        }
        
        $method .= ", '$foreignPivotKey', '$relatedPivotKey', '$parentKey', '$relatedKey');\n    }";
        
        return $method;
    }

    /**
     * Generate a morphTo relationship method.
     *
     * @param string $relationName
     * @param array $relationData
     * @return string
     */
    protected function generateMorphToMethod($relationName, $relationData)
    {
        $name = $relationData['name'] ?? $relationName;
        $type = $relationData['type'] ?? $name . '_type';
        $id = $relationData['id'] ?? $name . '_id';
        $ownerKey = $relationData['ownerKey'] ?? 'id';
        
        return <<<METHOD
    public function $relationName()
    {
        return \$this->morphTo('$name', '$type', '$id', '$ownerKey');
    }
METHOD;
    }


    // Additional relationship methods (morphOne, morphMany, morphToMany, morphedByMany) would follow similar patterns
    // THIS IS GENERATED CHATGPT. IT MAY NEED ADJUSTMENTS. 
    /**
     * Generate a morphOne relationship method.
     *
     * @param string $relationName
     * @param array $relationData
     * @return string
     */
    protected function generateMorphOneMethod($relationName, $relationData)
    {
        $relatedModel = ltrim($relationData['model'], '\\');
        $name = $relationData['name'] ?? $relationName;
        $type = $relationData['type'] ?? $name . '_type';
        $id = $relationData['id'] ?? $name . '_id';
        $localKey = $relationData['localKey'] ?? 'id';

        return <<<METHOD
    public function $relationName()
    {
        return \$this->morphOne(\\$relatedModel::class, '$name', '$type', '$id', '$localKey');
    }
METHOD;
    }

    /**
     * Generate a morphMany relationship method.
     *
     * @param string $relationName
     * @param array $relationData
     * @return string
     */
    protected function generateMorphManyMethod($relationName, $relationData)
    {
        $relatedModel = ltrim($relationData['model'], '\\');
        $name = $relationData['name'] ?? $relationName;
        $type = $relationData['type'] ?? $name . '_type';
        $id = $relationData['id'] ?? $name . '_id';
        $localKey = $relationData['localKey'] ?? 'id';

        return <<<METHOD
    public function $relationName()
    {
        return \$this->morphMany(\\$relatedModel::class, '$name', '$type', '$id', '$localKey');
    }
METHOD;
    }

    /**
     * Generate a morphToMany relationship method.
     *
     * @param string $relationName
     * @param array $relationData
     * @return string
     */
    protected function generateMorphToManyMethod($relationName, $relationData)
    {
        $relatedModel = ltrim($relationData['model'], '\\');
        $name = $relationData['name'] ?? $relationName;
        $table = $relationData['pivotTable'] ?? null;
        $foreignPivotKey = $relationData['foreignPivotKey'] ?? Str::snake(class_basename($this)) . '_id';
        $relatedPivotKey = $relationData['relatedPivotKey'] ?? Str::snake(class_basename($relatedModel)) . '_id';
        $parentKey = $relationData['parentKey'] ?? 'id';
        $relatedKey = $relationData['relatedKey'] ?? 'id';

        $method = "    public function $relationName()\n    {\n";
        $method .= "        return \$this->morphToMany(\\$relatedModel::class, '$name'";

        if ($table) {
            $method .= ", '$table'";
        }

        $method .= ", '$foreignPivotKey', '$relatedPivotKey', '$parentKey', '$relatedKey');\n    }";

        return $method;
    }

    /**
     * Generate a morphedByMany relationship method.
     *
     * @param string $relationName
     * @param array $relationData
     * @return string
     */
    protected function generateMorphedByManyMethod($relationName, $relationData)
    {
        $relatedModel = ltrim($relationData['model'], '\\');
        $name = $relationData['name'] ?? $relationName;
        $table = $relationData['pivotTable'] ?? null;
        $foreignPivotKey = $relationData['foreignPivotKey'] ?? Str::snake(class_basename($relatedModel)) . '_id';
        $relatedPivotKey = $relationData['relatedPivotKey'] ?? Str::snake(class_basename($this)) . '_id';
        $parentKey = $relationData['parentKey'] ?? 'id';
        $relatedKey = $relationData['relatedKey'] ?? 'id';

        $method = "    public function $relationName()\n    {\n";
        $method .= "        return \$this->morphedByMany(\\$relatedModel::class, '$name'";

        if ($table) {
            $method .= ", '$table'";
        }

        $method .= ", '$foreignPivotKey', '$relatedPivotKey', '$parentKey', '$relatedKey');\n    }";

        return $method;
    }



    /**
     * Generate a hasOneThrough relationship method.
     *
     * @param string $relationName
     * @param array $relationData
     * @return string
     */
    protected function generateHasOneThroughMethod($relationName, $relationData)
    {
        $relatedModel = ltrim($relationData['model'], '\\');
        $throughModel = ltrim($relationData['through'], '\\');

        $firstKey = $relationData['firstKey'] ?? Str::snake(class_basename($throughModel)) . '_id';
        $secondKey = $relationData['secondKey'] ?? Str::snake(class_basename($relatedModel)) . '_id';
        $localKey = $relationData['localKey'] ?? 'id';
        $secondLocalKey = $relationData['secondLocalKey'] ?? 'id';

        return <<<METHOD
    public function $relationName()
    {
        return \$this->hasOneThrough(
            \\$relatedModel::class,
            \\$throughModel::class,
            '$firstKey',
            '$secondKey',
            '$localKey',
            '$secondLocalKey'
        );
    }
METHOD;
    }

    /**
     * Generate a hasManyThrough relationship method.
     *
     * @param string $relationName
     * @param array $relationData
     * @return string
     */
    protected function generateHasManyThroughMethod($relationName, $relationData)
    {
        $relatedModel = ltrim($relationData['model'], '\\');
        $throughModel = ltrim($relationData['through'], '\\');

        $firstKey = $relationData['firstKey'] ?? Str::snake(class_basename($throughModel)) . '_id';
        $secondKey = $relationData['secondKey'] ?? Str::snake(class_basename($relatedModel)) . '_id';
        $localKey = $relationData['localKey'] ?? 'id';
        $secondLocalKey = $relationData['secondLocalKey'] ?? 'id';

        return <<<METHOD
    public function $relationName()
    {
        return \$this->hasManyThrough(
            \\$relatedModel::class,
            \\$throughModel::class,
            '$firstKey',
            '$secondKey',
            '$localKey',
            '$secondLocalKey'
        );
    }
METHOD;
    }









    /**
     * Get the imports for the model.
     *
     * @param array $modelData
     * @return string
     */
    protected function getImports($modelData)
    {
        $imports = [];
        
        // Add model-specific imports
        if (isset($modelData['imports']) && is_array($modelData['imports'])) {
            foreach ($modelData['imports'] as $import) {
                $imports[] = "use {$import};";
            }
        }
        
        // Add relationship imports
        $relations = $modelData['relations'] ?? [];
        foreach ($relations as $relationData) {
            if (isset($relationData['model'])) {
                $modelClass = $relationData['model'];
                if (!str_contains($modelClass, '\\')) {
                    $modelClass = "App\\Modules\\" . ucfirst($relationData['module'] ?? 'Core') . "\\Models\\{$modelClass}";
                }
                $imports[] = "use {$modelClass};";
            }
        }
        
        return implode("\n", array_unique($imports));
    }

    /**
     * Get the trait imports for the model.
     *
     * @param array $modelData
     * @return string
     */
    protected function getTraitImports($modelData)
    {
        if (!isset($modelData['traits']) || !is_array($modelData['traits'])) {
            return '';
        }
        
        $imports = [];
        foreach ($modelData['traits'] as $trait) {
            $imports[] = "use {$trait};";
        }
        
        return implode("\n", $imports);
    }

    /**
     * Get the trait uses for the model.
     *
     * @param array $modelData
     * @return string
     */
    protected function getTraitUses($modelData)
    {
        if (!isset($modelData['traits']) || !is_array($modelData['traits'])) {
            return '';
        }
        
        $uses = [];
        foreach ($modelData['traits'] as $trait) {
            $uses[] = class_basename($trait);
        }
        
        return 'use ' . implode(', ', $uses) . ';';
    }

    /**
     * Get the soft deletes declaration for the model.
     *
     * @param array $modelData
     * @return string
     */
    protected function getSoftDeletes($modelData)
    {
        if (!($modelData['softDeletes'] ?? false)) {
            return '';
        }
        
        return 'use SoftDeletes;';
    }

    /**
     * Get the display fields for the model.
     *
     * @param array $modelData
     * @return string
     */
    protected function getDisplayFields($modelData)
    {
        if (!isset($modelData['displayFields']) || !is_array($modelData['displayFields'])) {
            return '';
        }
        
        $fields = array_map(function ($field) {
            return "'{$field}'";
        }, $modelData['displayFields']);
        
        return "protected \$displayFields = [" . implode(', ', $fields) . "];";
    }

    /**
     * Get the primary key for the model.
     *
     * @param array $modelData
     * @return string
     */
    protected function getPrimaryKey($modelData)
    {
        if (!isset($modelData['primaryKey'])) {
            return '';
        }
        
        return "protected \$primaryKey = '{$modelData['primaryKey']}';";
    }

    /**
     * Get the incrementing setting for the model.
     *
     * @param array $modelData
     * @return string
     */
    protected function getIncrementing($modelData)
    {
        if (!isset($modelData['incrementing'])) {
            return '';
        }
        
        $value = $modelData['incrementing'] ? 'true' : 'false';
        return "public \$incrementing = {$value};";
    }

    /**
     * Get the key type for the model.
     *
     * @param array $modelData
     * @return string
     */
    protected function getKeyType($modelData)
    {
        if (!isset($modelData['keyType'])) {
            return '';
        }
        
        return "protected \$keyType = '{$modelData['keyType']}';";
    }

    /**
     * Get the timestamps setting for the model.
     *
     * @param array $modelData
     * @return string
     */
    protected function getTimestamps($modelData)
    {
        if (!isset($modelData['timestamps'])) {
            return '';
        }
        
        $value = $modelData['timestamps'] ? 'true' : 'false';
        return "public \$timestamps = {$value};";
    }

    /**
     * Get the date format for the model.
     *
     * @param array $modelData
     * @return string
     */
    protected function getDateFormat($modelData)
    {
        if (!isset($modelData['dateFormat'])) {
            return '';
        }
        
        return "protected \$dateFormat = '{$modelData['dateFormat']}';";
    }
}