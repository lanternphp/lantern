<?php

namespace Lantern\Commands;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Support\Str;
use Symfony\Component\Console\Input\InputArgument;

class MakeActionCommand extends GeneratorCommand
{
    protected $name = 'lantern:make-action';
    protected $description = 'Create a new Lantern action class';
    protected $type = 'Action';

    protected function getStub()
    {
        return __DIR__.'/stubs/action.stub';
    }

    protected function getDefaultNamespace($rootNamespace)
    {
        return $rootNamespace.'\Features';
    }

    protected function buildClass($name)
    {
        $replace = [];

        $replace['{{ id }}'] = $this->getIdFromClassName($name);

        return str_replace(
            array_keys($replace),
            array_values($replace),
            parent::buildClass($name)
        );
    }

    protected function getIdFromClassName($name)
    {
        $className = class_basename($name);
        $className = str_replace('Action', '', $className);
        
        // Get the namespace parts after Features
        $parts = explode('\\', str_replace($this->rootNamespace().'Features\\', '', $name));
        array_pop($parts); // Remove the class name
        
        // Convert namespace parts to kebab case
        $parts = array_map(function ($part) {
            return strtolower(preg_replace('/(?<!^)[A-Z]/', '-$0', $part));
        }, $parts);
        
        // Add the class name in kebab case
        $parts[] = strtolower(preg_replace('/(?<!^)[A-Z]/', '-$0', $className));
        
        return implode('-', $parts);
    }

    protected function getPath($name)
    {
        $name = Str::replaceFirst($this->rootNamespace(), '', $name);

        // Use the configured features path instead of app_path
        $basePath = config('lantern.features_path', app_path('Features'));
        
        // Remove 'Features' from the namespace if it exists since we're already in the Features directory
        $name = str_replace('Features\\', '', $name);

        // Ensure the name ends with 'Action' if it doesn't already
        if (!Str::endsWith($name, 'Action')) {
            $name .= 'Action';
        }

        // Convert forward slashes to directory separators
        $path = $basePath . DIRECTORY_SEPARATOR . str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $name) . '.php';

        return $path;
    }

    protected function qualifyClass($name)
    {
        $name = ltrim($name, '\\/');
        $name = str_replace('/', '\\', $name);

        $rootNamespace = $this->rootNamespace();

        if (Str::startsWith($name, $rootNamespace)) {
            return $name;
        }

        return $this->qualifyClass(
            $this->getDefaultNamespace(trim($rootNamespace, '\\')) . '\\' . $name
        );
    }

    protected function alreadyExists($rawName)
    {
        $exists = parent::alreadyExists($rawName);
        
        if ($exists) {
            $this->error('Action already exists!');
            return true;
        }
        
        return false;
    }

    /**
     * @return int
     */
    // @phpstan-ignore method.childReturnType (GeneratorCommand documents bool|null, but Command::execute() casts the return value to an int exit code)
    public function handle()
    {
        // Check if class already exists
        if ($this->isReservedName($this->getNameInput())) {
            $this->error('The name "'.$this->getNameInput().'" is reserved by PHP.');
            return 1;
        }

        // First we need to ensure that the given name is not a reserved PHP keyword
        if ($this->alreadyExists($this->getNameInput())) {
            return 1;
        }

        // If the class doesn't already exist, we will create the class and inform
        // the developer that the class was created
        $path = $this->getPath($this->qualifyClass($this->getNameInput()));
        $this->makeDirectory($path);

        $this->files->put(
            $path,
            $this->sortImports($this->buildClass($this->qualifyClass($this->getNameInput())))
        );

        $this->info('Action created successfully.');
        $this->info('Remember to add this action to a feature\'s ACTIONS array to make it available.');
        $this->info('Example:');
        $this->info("const ACTIONS = [");
        $this->info("    \\{$this->qualifyClass($this->getNameInput())}::class,");
        $this->info("];");

        return 0;
    }
}