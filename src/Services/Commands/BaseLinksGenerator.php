<?php

namespace QuickerFaster\CodeGen\Services\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

abstract class BaseLinksGenerator extends Command
{
    protected $command;

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
     * Read the configuration from a file.
     *
     * @param string $configPath
     * @return array
     */
    protected function readConfig($configPath)
    {
        if (File::exists($configPath)) {
            return include $configPath;
        }
        
        return [];
    }

    /**
     * Check if an entry already exists in the configuration.
     *
     * @param array $existingConfig
     * @param array $newEntry
     * @return bool
     */
    protected function isDuplicateEntry($existingConfig, $newEntry)
    {
        return collect($existingConfig)->contains(function ($entry) use ($newEntry) {
            return ($entry['title'] === $newEntry['title'] && $entry['url'] === $newEntry['url']);
        });
    }

    /**
     * Write the configuration to a file.
     *
     * @param string $configPath
     * @param array $config
     * @return void
     */
    protected function writeConfig($configPath, $config)
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
}