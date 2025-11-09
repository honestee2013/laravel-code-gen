<?php

namespace QuickerFaster\CodeGen\Services\Commands;

use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Yaml\Yaml;

class MigrationGenerator extends Command
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
     * Generate migration for a model.
     *
     * @param string $module
     * @param string $modelName
     * @param array $modelData
     * @return void
     * @throws Exception
     */
    public function generateMigration($module, $modelName, $modelData)
    {

        // Generate the main migration only once
        $this->generateMainMigration($module, $modelName, $modelData);

        // Generate pivot migrations for relationships Last
        $this->generatePivotMigrations($module, $modelName, $modelData);

    }

    /**
     * Generate pivot migrations based on relationships.
     *
     * @param string $module
     * @param string $modelName
     * @param array $modelData
     * @return void
     */
    protected function generatePivotMigrations($module, $modelName, $modelData)
    {
        if (!isset($modelData['relations'])) {
            return;
        }

        foreach ($modelData['relations'] as $relationName => $relationData) {
            switch ($relationData['type']) {
                case 'belongsToMany':
                    $model1 = Str::snake($modelName);
                    $model2 = Str::snake(class_basename($relationData['model']));

                    $pivotTableName = $relationData['pivotTable'] ?? Str::singular(Str::lower($modelName)) . '_' . Str::singular(Str::lower(class_basename($relationData['model'])));
                    $foreignKey1 = $relationData['relatedPivotKey'] ?? $model1.'_id';
                    $foreignKey2 = $relationData['foreignPivotKey'] ?? $model2.'_id';

                    $this->generatePivotMigration($module, $pivotTableName, $model1, $model2, $foreignKey1, $foreignKey2);
                    break;

                case 'morphToMany':
                    $pivotTableName = $relationData['pivotTable'] ?? null;
                    if (!$pivotTableName) {
                        $this->error("Pivot table name is required for morphToMany relation.");
                        break;
                    }
                    $foreignKey = $relationData['foreignKey'] ?? 'id';
                    $relatedPivotKey = $relationData['relatedPivotKey'] ?? 'id';
                    $morphType = $relationData['morphType'] ?? 'model_type';
                    $this->generatePolymorphicPivotMigration($module, $pivotTableName, $modelName, $foreignKey, $relatedPivotKey, $morphType);
                    break;
            }
        }
    }

    /**
     * Generate the main migration for the model.
     *
     * @param string $module
     * @param string $modelName
     * @param array $modelData
     * @return void
     * @throws Exception
     */
    public function generateMainMigration($module, $modelName, $modelData)
    {
        $isPivot = $modelData['isPivot'] ?? false;
        $migrationFullPath = $this->getMigrationPath($module, $modelName, $isPivot);

        $stub = $this->getMigrationStub($module, $modelName, $modelData);
        if (empty($stub)) {
            throw new Exception("Migration stub is empty for model: $modelName");
        }

        File::put($migrationFullPath, $stub);
        $this->output->info("Migration created: $migrationFullPath");
    }

    /**
     * Get the migration path, ensuring no duplicate timestamps.
     *
     * @param string $module
     * @param string $modelName
     * @param bool $isPivot
     * @return string
     */
protected function getMigrationPath($module, $modelName, $isPivot = false)
{
    $tableName = strtolower(Str::plural(Str::snake($modelName)));
    $migrationName = 'create_' . $tableName . '_table';

    if ($isPivot) {
        $migrationName = 'create_' . strtolower(Str::singular(Str::snake($modelName))) . '_table';
    }

    $migrationPath = app_path("Modules/" . ucfirst($module) . "/Database/Migrations/");

    if (!File::exists($migrationPath)) {
        File::makeDirectory($migrationPath, 0755, true);
    }

    // Check for existing migration with the same name
    $existingFiles = File::files($migrationPath);
    foreach ($existingFiles as $file) {
        if (str_contains($file->getFilename(), $migrationName)) {
            return $file->getRealPath();
        }
    }

    // 🔑 Self-contained timestamp sequencing
    static $nextTimestamp = null;

    if ($nextTimestamp === null) {
        // Initialize to current time on first call
        $nextTimestamp = time();
    } else {
        // Increment by 1 second for each subsequent call
        $nextTimestamp++;
    }

    $timestamp = date('Y_m_d_His', $nextTimestamp);
    $migrationFullPath = $migrationPath . $timestamp . '_' . $migrationName . '.php';

    // Safety fallback (should rarely trigger)
    $counter = 0;
    while (File::exists($migrationFullPath)) {
        $counter++;
        $migrationFullPath = $migrationPath . $timestamp . '_' . $counter . '_' . $migrationName . '.php';
    }

    return $migrationFullPath;
}

    /**
     * Get the migration stub content.
     *
     * @param string $module
     * @param string $modelName
     * @param array $modelData
     * @return string
     */
    protected function getMigrationStub($module, $modelName, $modelData)
    {
        $fields = $modelData['fields'] ?? [];
        $moduleStubPath = app_path("Modules/{$module}/Stubs/Database/Migrations/{$modelName}.stub");
        $coreStubPath = __DIR__ . '/../../Stubs/migration.stub';

        if (File::exists($moduleStubPath)) {
            $stub = File::get($moduleStubPath);
        } elseif (File::exists($coreStubPath)) {
            $stub = File::get($coreStubPath);
        } else {
            $this->error("Migration stub not found for $modelName in module $module.");
            return "";
        }

        $columns = $this->generateColumns($fields);
        $indexes = $this->generateIndexes($modelData);

        $stub = str_replace('{{modelName}}', $modelName, $stub);
        $stub = str_replace('{{tableName}}', strtolower(Str::plural(Str::snake($modelName))), $stub);
        $stub = str_replace('{{columns}}', $columns, $stub);
        $stub = str_replace('{{indexes}}', $indexes, $stub);

        return $stub;
    }

    /**
     * Generate column definitions for the migration.
     *
     * @param array $fields
     * @return string
     */
    protected function generateColumns($fields)
    {
        $columns = '';
        
        foreach ($fields as $fieldName => $fieldData) {
            if (str_contains($fieldName, '_confirmation')) {
                continue; // Skip confirmation fields
            }

            // buildColumnDefinition now returns an ARRAY of strings/lines of code
            $linesArray = $this->buildColumnDefinition($fieldName, $fieldData);
            
            // Join the array elements with a newline character and the correct indentation (tabs or 4 spaces x 3 = 12 spaces)
            // Adjust the indentation ('            ' or '\t\t\t') to match your coding style.
            $columnDefinitionString = implode("\n            ", $linesArray);
            
            // Append the generated line(s) to the total columns string
            $columns .= $columnDefinitionString . (isset($fieldData['foreign'])? ";\n            ": "\n            "); 
            // The "\n            " adds a new line and correct indentation for the NEXT field iteration.
        }
        
        return $columns;
    }


    /**
     * Build a single column definition.
     *
     * @param string $fieldName
     * @param array $fieldData
     * @return string
     */
    protected function buildColumnDefinition($fieldName, $fieldData)
    {
        $type = $this->getFieldType($fieldData);
        $modifiers = $fieldData['modifiers'] ?? [];
        $foreign = $fieldData['foreign'] ?? null;
        $isExplicitConstraint = $fieldData['isExplicitConstraint'] ?? false;

        // If it's an implicit foreignId (modern Laravel)
        if ($foreign && !$isExplicitConstraint) {
            // This returns a single line of code
            return [$this->buildForeignKeyColumn($fieldName, $modifiers, $foreign)];
        } 
        
        // If it's an explicit foreign constraint (traditional Laravel)
        if ($foreign && $isExplicitConstraint) {
            // We need TWO lines: the column definition AND the constraint definition.

            // 1. Build the raw column definition line first (e.g., $table->unsignedBigInteger('user_id');)
            $columnDefinition = "\$table->{$type}('{$fieldName}')";
            $columnDefinition = $this->addModifiers($columnDefinition, $modifiers);
            $columnDefinition .= ";"; // Add semicolon here

            // 2. Build the explicit constraint line (e.g., $table->foreign(...)->on(...);)
            // Note: The explicit constraint method provided previously assumed modifiers 
            // were not passed to it, so I updated the call signature below.
            $constraintLine = $this->buildForeignKeyColumnExplicitConstraint($fieldName, $foreign);

            // Return both lines as an array
            return [$columnDefinition, $constraintLine];
        }

        // --- Standard Column Definition (no foreign key) ---

        $columnDefinition = "\$table->{$type}('{$fieldName}'";

        // Handle type-specific parameters
        if (in_array($type, ['string', 'varchar', 'char']) && isset($modifiers['length'])) {
            $columnDefinition .= ", {$modifiers['length']}";
        } elseif ($type === 'decimal' && isset($modifiers['precision'])) {
            $parts = explode(',', $modifiers['precision']);
            $precision = $parts[0] ?? 8;
            $scale = $parts[1] ?? 2;
            $columnDefinition .= ", {$precision}, {$scale}";
        }

        $columnDefinition .= ")";
        $columnDefinition = $this->addModifiers($columnDefinition, $modifiers);
        $columnDefinition .= ";"; // Add semicolon for standard line

        // Return a single line wrapped in an array for consistency
        return [$columnDefinition];
    }


    /**
     * Build a foreign key column definition.
     *
     * @param string $fieldName
     * @param array $modifiers
     * @param array $foreign
     * @return string
     */
    protected function buildForeignKeyColumn($fieldName, $modifiers, $foreign)
    {
        $columnDefinition = "\$table->foreignId('{$fieldName}')";
        $columnDefinition = $this->addModifiers($columnDefinition, $modifiers);

        $columnDefinition .= "->constrained('{$foreign['table']}', '{$foreign['column']}')";

        if (isset($foreign['onDelete'])) {
            $onDelete = strtolower($foreign['onDelete']);
            if (in_array($onDelete, ['cascade', 'restrict', 'set null', 'no action'])) {
                $columnDefinition .= "->onDelete('{$onDelete}')";
            }
        }

        if (isset($foreign['onUpdate'])) {
            $onUpdate = strtolower($foreign['onUpdate']);
            if (in_array($onUpdate, ['cascade', 'restrict', 'set null', 'no action'])) {
                $columnDefinition .= "->onUpdate('{$onUpdate}')";
            }
        }

        return $columnDefinition;
    }




    /**
     * Build the code for an explicit foreign key constraint.
     * Assumes the column (e.g., user_id) has already been created (e.g., $table->unsignedBigInteger('user_id')).
     *
     * @param string $fieldName The name of the local column (e.g., 'user_id')
     * @param array $foreign Foreign key details (table, column, onDelete, onUpdate)
     * @return string The generated PHP code line for the constraint
     */
    protected function buildForeignKeyColumnExplicitConstraint($fieldName, $foreign)
    {
        $columnDefinition = "\$table->foreign('{$fieldName}')";
        $columnDefinition .= "->references('{$foreign['column']}')";
        $columnDefinition .= "->on('{$foreign['table']}')";

        if (isset($foreign['onDelete'])) {
            $onDelete = strtolower($foreign['onDelete']);
            if (in_array($onDelete, ['cascade', 'restrict', 'set null', 'no action'])) {
                $columnDefinition .= "->onDelete('{$onDelete}')";
            }
        }

        if (isset($foreign['onUpdate'])) {
            $onUpdate = strtolower($foreign['onUpdate']);
            if (in_array($onUpdate, ['cascade', 'restrict', 'set null', 'no action'])) {
                $columnDefinition .= "->onUpdate('{$onUpdate}')";
            }
        }
        
        $columnDefinition .= ";"; // Add semicolon at the end

        return $columnDefinition;
    }





    /**
     * Generate indexes for the migration.
     *
     * @param array $modelData
     * @return string
     */
    protected function generateIndexes($modelData)
    {
        $indexLines = [];

        // Add indexes from YAML if defined
        if (isset($modelData['indexes'])) {
            foreach ($modelData['indexes'] as $index) {
                // $col = is_array($index) ? $index['column'] : $index;
                $indexLines[] = "\t\t\t\$table->index('{$index}');";
            }
        }

        if (isset($modelData['uniqueIndexes'])) {
            foreach ($modelData['uniqueIndexes'] as $index) {
                // $col = is_array($index) ? $index['column'] : $index;
                $indexLines[] = "\t\t\t\$table->unique('{$index}');";
            }
        }

        if (isset($modelData['compoundIndexes'])) {
            foreach ($modelData['compoundIndexes'] as $cols) {
                $colList = implode("', '", $cols);
                $indexLines[] = "\t\t\t\$table->index(['{$colList}']);";
            }
        }


        if (isset($modelData['compoundUniqueIndexes'])) {
            foreach ($modelData['compoundUniqueIndexes'] as $cols) {
                $colList = implode("', '", $cols);
                $indexLines[] = "\t\t\t\$table->unique(['{$colList}']);";
            }
        }
        

        // Add unique indexes from validation rules
        if (isset($modelData['fields'])) {
            foreach ($modelData['fields'] as $fieldName => $fieldData) {
                if (isset($fieldData['validation'])) {
                    foreach ($fieldData['validation'] as $rule) {
                        if (Str::contains($rule, 'unique:')) {
                            $indexLines[] = "\t\t\t\$table->unique('{$fieldName}');";
                            break;
                        }
                    }
                }
            }
        }

        return implode("\n", $indexLines);
    }

    /**
     * Map YAML field type to database column type.
     *
     * @param array $fieldData
     * @return string
     */
    private function getFieldType($fieldData)
    {
        $type = $fieldData['type'] ?? 'string';
        $type = strtolower($type);

        switch ($type) {
            case 'string':
            case 'varchar':
            case 'char':
            case 'email':
            case 'password':
            case 'select':
            case 'file':
            case 'checkbox':
            case 'radio':
                return 'string';
            case 'text':
            case 'textarea':
            case 'encrypted_string':    
                return 'text';
            case 'integer':
            case 'int':
                return 'integer';
            case 'decimal':
            case 'float':
            case 'double':
                return 'decimal';
            case 'boolean':
            case 'bool':
            case 'boolcheckbox':
            case 'boolradio':
                return 'boolean';
            case 'date':
            case 'datepicker':
                return 'date';
            case 'datetime':
            case 'timestamp':
            case 'datetimepicker':    
                return 'datetime';
            case 'timepicker':
            case 'time':
                return 'time';
            default:
                return $type;
        }
    }

    /**
     * Add modifiers to the column definition.
     *
     * @param string $columnDefinition
     * @param array $modifiers
     * @return string
     */
    private function addModifiers($columnDefinition, $modifiers)
    {
        foreach ($modifiers as $modifierName => $modifierValue) {
            switch ($modifierName) {
                case 'nullable':
                    if ($modifierValue === true) {
                        $columnDefinition .= "->nullable()";
                    }
                    break;
                case 'unique':
                    if ($modifierValue === true) {
                        $columnDefinition .= "->unique()";
                    }
                    break;
                case 'default':
                    if (is_bool($modifierValue)) {
                        $columnDefinition .= "->default(" . ($modifierValue ? 'true' : 'false') . ")";
                    } elseif (is_numeric($modifierValue)) {
                        $columnDefinition .= "->default($modifierValue)";
                    } elseif (is_string($modifierValue)) {
                        $columnDefinition .= "->default('" . addslashes($modifierValue) . "')";
                    }
                    break;
                case 'comment':
                    $columnDefinition .= "->comment('" . addslashes($modifierValue) . "')";
                    break;
            }
        }

        return $columnDefinition;
    }

    // The following methods (generatePivotMigration, generatePolymorphicPivotMigration, getPivotMigrationStub) remain largely the same but with error handling.
    // For brevity, I haven't included them here, but they should be updated similarly.

    protected function generatePivotMigration($module, $pivotTableName, $model1, $model2, $foreignKey1, $foreignKey2)
    {
        $migrationPath = $this->getMigrationPath($module, $pivotTableName, true);
        $stub = $this->getPivotMigrationStub($pivotTableName, $model1, $model2, $foreignKey1, $foreignKey2);
        File::put($migrationPath, $stub);
        $this->info("Pivot migration created: {$migrationPath}");
    }

    protected function generatePolymorphicPivotMigration($module, $pivotTableName, $modelName, $foreignKey, $relatedPivotKey, $morphType)
    {
        $migrationPath = $this->getMigrationPath($module, $pivotTableName, true);
        $stubPath = __DIR__ . '/../../Stubs/polymorphic_pivot_migration.stub';
        if (!File::exists($stubPath)) {
            $this->error("Stub not found: $stubPath");
            return;
        }
        $stub = File::get($stubPath);
        $stub = str_replace('{{pivotTableName}}', $pivotTableName, $stub);
        $stub = str_replace('{{modelName}}', strtolower($modelName), $stub);
        $stub = str_replace('{{foreignKey}}', $foreignKey, $stub);
        $stub = str_replace('{{relatedPivotKey}}', $relatedPivotKey, $stub);
        $stub = str_replace('{{morphType}}', $morphType, $stub);
        File::put($migrationPath, $stub);
        $this->info("Polymorphic pivot migration created: {$migrationPath}");
    }

    protected function getPivotMigrationStub($pivotTableName, $model1, $model2, $foreignKey1, $foreignKey2)
    {
        $stubPath = __DIR__ . '/../../Stubs/pivot_migration.stub';
        if (!File::exists($stubPath)) {
            $this->error("Stub not found: $stubPath");
            return "";
        }
        $stub = File::get($stubPath);
        $stub = str_replace('{{pivotTableName}}', $pivotTableName, $stub);
        $stub = str_replace('{{model1}}', strtolower(Str::snake(Str::plural($model1))), $stub);
        $stub = str_replace('{{model2}}', strtolower(Str::snake(Str::plural($model2))), $stub);
        $stub = str_replace('{{foreignKey1}}', $foreignKey1, $stub);
        $stub = str_replace('{{foreignKey2}}', $foreignKey2, $stub);
        return $stub;
    }
}