<?php

namespace QuickerFaster\CodeGen\Commands;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Console\Command;
use Symfony\Component\Yaml\Yaml;
use Illuminate\Support\Facades\File;

use QuickerFaster\CodeGen\Services\Commands\BladeGenerator;
use QuickerFaster\CodeGen\Services\Commands\ModelGenerator;
use QuickerFaster\CodeGen\Services\Commands\ConfigGenerator;
use QuickerFaster\CodeGen\Services\Commands\MigrationGenerator;
use QuickerFaster\CodeGen\Services\Commands\SidebarLinksGenerator;
use QuickerFaster\CodeGen\Services\Commands\TopBarLinksGenerator;
use QuickerFaster\CodeGen\Services\Commands\BottomBarLinksGenerator;
use QuickerFaster\CodeGen\Services\Commands\WizardGenerator;


class GenerateFromSchema extends Command
{
    protected $signature = 'app:generate-from-yaml {yaml_file}';
    protected $description = 'Generate migrations, models, and other files from a schema definition.';

    // app/Console/Commands/GenerateFromSchema.php
    public function handle()
    {
        $schemaFile = $this->argument('yaml_file');

        if (!File::exists($schemaFile)) {
            $this->error("Schema file not found: {$schemaFile}");
            return Command::FAILURE;
        }

        $schema = Yaml::parseFile($schemaFile);

        // Generate model-related files
        foreach ($schema['models'] as $modelName => $modelData) {
            $module = $modelData['module'];

            (new MigrationGenerator($this))->generateMigration($module, $modelName, $modelData);
            (new ModelGenerator($this))->generateModel($module, $modelName, $modelData);
            (new ConfigGenerator($this))->generateConfigFile($module, $modelName, $modelData);
            (new BladeGenerator($this))->generateBladeFile($module, $modelName, $modelData);
            (new SidebarLinksGenerator($this))->generateSidebarLinks($module, $modelName, $modelData);
            (new TopBarLinksGenerator($this))->generateTopBarLinks($module, $modelName, $modelData);
            (new BottomBarLinksGenerator($this))->generateBottomBarLinks($module, $modelName, $modelData);
        }

        // Generate wizards (NEW)
        (new WizardGenerator($this))->generateWizards($schemaFile, $schema);

        $this->info('Files generated successfully!');
        return Command::SUCCESS;
    }


}





