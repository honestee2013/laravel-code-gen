<?php

namespace QuickerFaster\CodeGen\Providers;

use QuickerFaster\CodeGen\Services\DataTables\DataTableConfigService;
use QuickerFaster\CodeGen\Services\DataTables\DataTableOptionService;
use QuickerFaster\CodeGen\Services\DataTables\DataTableDataSourceService;

use QuickerFaster\CodeGen\Facades\UserActivities\UserActivityLoggerFacade;

use QuickerFaster\CodeGen\Facades\DataTables\DataTableDataSource;
use QuickerFaster\CodeGen\Facades\DataTables\DataTableConfig;
use QuickerFaster\CodeGen\Facades\DataTables\DataTableOption;

use QuickerFaster\CodeGen\Services\Log\UserActivityLogger;

use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;


use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;

use Illuminate\Foundation\AliasLoader;


use App\Modules\Core\Repositories\DataTables\FieldRepository;

use QuickerFaster\CodeGen\Commands\GenerateAllSchemas;
use QuickerFaster\CodeGen\Commands\GenerateFromSchema;
use Illuminate\Support\Facades\Route;

use Illuminate\Database\Eloquent\Factory as EloquentFactory; // only for Laravel < 8
use Illuminate\Database\Eloquent\Factories\Factory;






class QuickerFasterCodeGenServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */

    public function register()
    {
        $this->app->singleton('user-activity-logger', function () {
            return new UserActivityLogger();
        });

        $this->app->singleton("DataTableConfigService", function ($app) {
            return new DataTableConfigService();
        });


        $this->app->singleton("DataTableOptionService", function ($app) {
            return new DataTableOptionService();
        });

        $this->app->singleton("DataTableDataSourceService", fn() => new DataTableDataSourceService);


        // Register commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                GenerateAllSchemas::class,
                GenerateFromSchema::class
            ]);
        }


        // Merge configuration
        /*$this->mergeConfigFrom(__DIR__.'/../config/code-gen.php', 'code-gen');

*/

        // Register Livewire component (example - adjust namespace and class name if needed)
        //Livewire::component('crud-component', CrudComponent::class);



    }




    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        /*
        
        // Example: Loading routes
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');

        // Example: Loading migrations
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // Publishing common configuration
        $this->publishes([
            __DIR__.'/../config/code-gen.php' => config_path('code-gen.php'),
        ], 'code-gen-config');

        // Publishing views for Livewire CRUD with Creative Tim Bootstrap theme
        $this->publishes([
            __DIR__.'/../resources/views/livewire-crud-ct-bootstrap' => resource_path('views/vendor/code-gen/livewire-crud-ct-bootstrap'),
        ], 'code-gen-livewire-crud-ct-bootstrap-views');

        // Publishing assets for Livewire CRUD with Creative Tim Bootstrap theme
        $this->publishes([
            __DIR__.'/../public/livewire-crud-ct-bootstrap' => public_path('vendor/code-gen/livewire-crud-ct-bootstrap'),
        ], 'code-gen-livewire-crud-ct-bootstrap-assets');

        // Publishing views for Inertia CRUD with Tailwind (Example - adjust paths)
        $this->publishes([
            __DIR__.'/../resources/views/inertia-crud-tailwind' => resource_path('views/vendor/code-gen/inertia-crud-tailwind'),
        ], 'code-gen-inertia-crud-tailwind-views');

        // Publishing assets for Inertia CRUD with Tailwind (Example - adjust paths)
        $this->publishes([
            __DIR__.'/../public/inertia-crud-tailwind' => public_path('vendor/code-gen/inertia-crud-tailwind'),
        ], 'code-gen-inertia-crud-tailwind-assets');

        // Publishing app integration files (adjust paths as needed)
        $this->publishes([
            __DIR__.'/../app-integration/Models' => app_path('Models/CodeGen'),
            __DIR__.'/../app-integration/Controllers' => app_path('Http/Controllers/CodeGen'),
        ], 'code-gen-app-integration');
        
*/


        /*$this->registerLivewireComponents($livewirePath = __DIR__ .'/../Http/Livewire');

        // Publishing Creative Tim Bootstrap Soft UI Scaffolding
        $this->publishScaffold();

        // Load translations
        $this->setupModules();
        $this->loadAliases();
        $this->loadRoutes();*/




        



    }



    private function loadRoutes()
    {
        $this->loadRoutesFrom(file_exists(base_path('routes/quicker-faster-code-gen-web.php'))
            ? base_path('routes/quicker-faster-code-gen-web.php')
            : __DIR__ . '/../scaffold/ct/soft-ui/bootstrap/v2/routes/web.php');

        /*$this->loadRoutesFrom(file_exists(base_path('routes/quicker-faster-code-gen-api.php'))
            ? base_path('routes/quicker-faster-code-gen-api.php')
            : __DIR__ . '/../routes/quicker-faster/code-gen/api.php');*/
    }


    private function loadAliases()
    {
        $loader = AliasLoader::getInstance();

        if (class_exists(UserActivityLoggerFacade::class)) {
            $loader->alias('UserActivityLoggerFacade', UserActivityLoggerFacade::class);
        }
        
        if (class_exists(DataTableOption::class)) {
            $loader->alias('DataTableOption', DataTableOption::class);
        }
        if (class_exists(DataTableConfig::class)) {
            $loader->alias('DataTableConfig', DataTableConfig::class);
        }
        if (class_exists(DataTableDataSource::class)) {
            $loader->alias('DataTableDataSource', DataTableDataSource::class);
        }

    }



    private function setupModules()
    {
        // Get all module directories
        if (!file_exists(base_path('app/Modules'))) {
            return;
        }
            
            
        $modules = File::directories(base_path('app/Modules'));

        // Loop through each module to load views, routes, and config files dynamically
        foreach ($modules as $module) {
            $moduleName = basename($module); // Get the module name from the directory


            // Load Migrations
            /*
                Migrations for each module are loaded dynamically by checking
                for the presence of migration files in each module.
            */
            $migrationPath = $module . '/Database/Migrations';
            if (File::exists($migrationPath)) {
                $this->loadMigrationsFrom($migrationPath);
            }



            //Post::observe(PostObserver::class);
            // Load Onservers
            /*$observerPath = app_path("Modules/{$moduleName}/Observers");

            if (File::exists($observerPath)) {

                foreach (File::allFiles($observerPath) as $file) {
                    $class = $this->getClassFromFile($moduleName, "Observers", $file->getPathname());
                    // Ensure the class exists and can be reflected
                    if (class_exists($class)) {
                        $model = str_replace("Observer", "", $class);
                        //dd($model, $class);
                        \App\Modules\Inventory\Models\InventoryTransaction::observe(\App\Modules\Inventory\Observers\InventoryTransactionObserver::class);
                    }
                }
            }*/

            // Load Events
            $eventPath = app_path("Modules/{$moduleName}/Listeners");

            if (File::exists($eventPath)) {

                foreach (File::allFiles($eventPath) as $file) {
                    $class = $this->getClassFromFile($moduleName, "Listeners", $file->getPathname());
                    // Ensure the class exists and can be reflected
                    if (class_exists($class)) {
                        $eventType = $this->getEventFromListener($class);
                        if ($eventType) {
                            Event::listen($eventType, $class);
                            \Log::info("Registered event listener: {$class} for event: {$eventType}");
                        }
                    }
                }
            }


            // Load Views
            // This will allow you to reference views using module_name::view-name (e.g., inventory::view-name).
            $viewPath = $module . '/Resources/views'; // Construct the view path for the module
            if (File::exists($viewPath)) {
                view()->addNamespace(strtolower($moduleName) . '.views', $viewPath);
            }


            // Load Components directory namespace expected in the module's main directory eg. /app/Modules/Core/Components
            $componentPath = $module . '/Components';
            if (File::exists($componentPath)) {
                Blade::componentNamespace(strtolower($moduleName) . '.components', $componentPath);
            }


            // Register Livewire components
            $livewirePath = $module . '/Http/Livewire';
            $namespace = "App\\Modules\\$moduleName\\Http\\Livewire";

            if (File::exists($livewirePath)) {
                $this->registerModuleLivewireComponents($moduleName, $livewirePath);
            }



            // Load Routes
            // loads the module’s routes file (web.php), if it exists,
            //so you don’t have to manually include each route file for every module.
            $routePath = $module . '/Routes/web.php';
            if (File::exists($routePath)) {
                Route::middleware('web')
                    ->group($routePath);
            }


            // Load API Routes
            $apiRoutePath = $module . '/Routes/api.php';
            if (File::exists($apiRoutePath)) {
                Route::prefix('api')
                    ->middleware('api')
                    ->group($apiRoutePath);
            }



            // Load Assets as Views
            $assetPath = $module . '/Resources/assets';
            if (File::exists($assetPath)) {
                view()->addNamespace(strtolower($moduleName) . '.assets', $assetPath);
            }






            // Load All Configurations
            /*
                Method merges each module’s configuration file (in this case,
                field-definitions.php) with the application’s configuration,
                using a custom namespace like inventory.field-definitions.
            */
            $configPath = $module . '/Config';
            /*if (File::exists($configPath)) {
                // Get all files in the Config folder
                $configFiles = File::files($configPath);

                foreach ($configFiles as $configFile) {
                    $fileName = pathinfo($configFile, PATHINFO_FILENAME); // Get the file name without extension
                    // Merge each config file with the application config

                    // Check if the corresponding database table exists add the config file
                    //if (Schema::hasTable(strtolower(Str::plural($fileName)))
                       // || Schema::hasTable(strtolower(Str::singular($fileName))) // for pivot table singular names
                    //) {
                        $this->mergeConfigFrom($configFile, strtolower($moduleName) . '.' . $fileName);
                   // }
                }
            }*/








        }

    }


    private function publishScaffold()
    {
        $this->publishes([
            __DIR__ . '/../scaffold/ct/soft-ui/bootstrap/v2/app/Http/Controllers' => app_path('Http/Controllers'),
            __DIR__ . '/../Modules/User/Models/User.php' => app_path('Models/User.php'),
            __DIR__ . '/../Modules' => app_path('Modules'),

            __DIR__ . '/../scaffold/ct/soft-ui/bootstrap/v2/public' => public_path('/'),
            //__DIR__.'/../scaffold/ct/soft-ui/bootstrap/v2/public/assets' => public_path('assets'),
            //__DIR__.'/../scaffold/ct/soft-ui/bootstrap/v2/public/css' => public_path('css'),
            //__DIR__.'/../scaffold/ct/soft-ui/bootstrap/v2/public/js' => public_path('js'),


            // Database related
            __DIR__ . '/../scaffold/ct/soft-ui/bootstrap/v2/database/migrations' => database_path('migrations'),
            __DIR__ . '/../scaffold/ct/soft-ui/bootstrap/v2/database/seeders' => database_path('seeders'),


            __DIR__ . '/../scaffold/ct/soft-ui/bootstrap/v2/resources' => resource_path('/'),
            //__DIR__.'/../scaffold/ct/soft-ui/bootstrap/v2/resources/views/layouts' => resource_path('views/layouts'),
            //__DIR__.'/../scaffold/ct/soft-ui/bootstrap/v2/resources/views/session' => resource_path('views/session'),
            //__DIR__.'/../scaffold/ct/soft-ui/bootstrap/v2/resources/views/components' => resource_path('views/components'),


            //__DIR__.'/../scaffold/ct/soft-ui/bootstrap/v2/resources/views/dashboard.blade.php' => resource_path('views/dashboard.blade.php'),
            //__DIR__.'/../scaffold/ct/soft-ui/bootstrap/v2/resources/views/welcome.blade.php' => resource_path('views/welcome.blade.php'),
            //__DIR__.'/../scaffold/ct/soft-ui/bootstrap/v2/resources/views/profile.blade.php' => resource_path('views/profile.blade.php'),

            // Routes related
            __DIR__ . '/../scaffold/ct/soft-ui/bootstrap/v2/routes/web.php' => base_path('routes/quicker-faster-code-gen-web.php'), // Be cautious with this!

        ], 'code-gen-ct-soft-ui-bootstrap-v2');
    }




    /**
     * Extract the fully-qualified class name from a file.
     */
    protected function getClassFromFile($moduleName, $directory, $filePath)
    {
        $className = str_replace('.php', '', basename($filePath));
        return "App\\Modules\\{$moduleName}\\{$directory}\\{$className}";
    }

    /**
     * Detect the event from a listener.
     */
    protected function getEventFromListener($class)
    {
        try {
            $reflection = new \ReflectionClass($class);

            // Ensure the class has a handle method
            if ($reflection->hasMethod('handle')) {
                $method = $reflection->getMethod('handle');
                $parameters = $method->getParameters();

                // Extract the event type from the first argument
                if (!empty($parameters)) {
                    $parameterType = $parameters[0]->getType();
                    return $parameterType ? $parameterType->getName() : null;
                }
            }
        } catch (\ReflectionException $e) {
            //dd("Error");
            //\Log::error("Reflection error for class {$class}: " . $e->getMessage());
        }

        return null;
    }



    protected function registerLivewireComponents(string $livewirePath)
    {
        $namespace = "QuickerFaster\\CodeGen\\Http\\Livewire";
        $components = $this->scanLivewireComponents($livewirePath, $namespace);

        foreach ($components as $className => $alias) {
            // Directly register the component with the dynamically generated alias
            $alias = strtolower($alias);
            //dd($alias);
            Livewire::component($alias, $className);
        }
    }


    protected function registerModuleLivewireComponents(string $moduleName, string $livewirePath)
    {
        $namespace = "App\\Modules\\$moduleName\\Http\\Livewire";
        $components = $this->scanLivewireComponents($livewirePath, $namespace);

        foreach ($components as $className => $alias) {
            // Directly register the component with the dynamically generated alias
            $alias = strtolower("$moduleName.$alias");
            Livewire::component($alias, $className);
        }
    }

    protected function scanLivewireComponents(string $path, string $namespace)
    {
        $components = [];
        $files = File::allFiles($path);

        foreach ($files as $file) {
            $relativePath = $file->getRelativePathname();

            // Generate class name
            $className = $namespace . '\\' . str_replace(['/', '.php'], ['\\', ''], $relativePath);

            // Generate alias manually
            $alias = $this->generateAlias($relativePath);

            $components[$className] = $alias;
        }

        return $components;
    }

    protected function generateAlias(string $relativePath): string
    {
        // Replace directory separators with dots
        $alias = str_replace(['/', '\\'], '.', $relativePath);

        // Convert PascalCase or camelCase segments to kebab-case
        $alias = preg_replace('/([a-z])([A-Z])/', '$1-$2', $alias);

        // Convert to lowercase and remove the ".php" extension
        $alias = strtolower(str_replace('.php', '', $alias));

        return $alias;
    }









}


