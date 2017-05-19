<?php

namespace Clarity\Console\Sdk;

use Clarity\Console\Lists\RoutesCommand;
use Clarity\Facades\Route;

abstract class Sdk extends RoutesCommand
{
    protected $name = "sdk";
    protected $description = "Generate SDK for API";

    /**
     * An function that will be called on every providers.
     *
     * @return void
     */
    public function slash()
    {
        foreach (di()->getServices() as $service) {
            if (! method_exists($def = $service->getDefinition(), 'afterModuleRun')) {
                continue;
            }

            $def->afterModuleRun();
        }
        $this->generate();
    }

    /**
     * @return \ReflectionClass[]
     */
    protected function getInterfaces()
    {
        return array_map(function ($file) {
            return new \ReflectionClass('App\Main\Interfaces\\' . pathinfo(
                    config()->path->app . 'Main/Interfaces/' . $file,
                    PATHINFO_FILENAME
                ));
        }, array_values(array_filter(scandir(config()->path->app . 'Main/Interfaces'), function ($item) {
            return $item !== '.' && $item != '..';
        })));
    }

    protected function getRoutes()
    {
        $routes = $this->extractRoutes(Route::getRoutes());
        return array_filter($routes, function ($item) {
            return $item !== null;
        });
    }

    /**
     * @param \ReflectionClass $class
     * @return PropertyInfo[]
     */
    protected function getInterfaceData(\ReflectionClass $class)
    {
        $properties = $class->getProperties(\ReflectionProperty::IS_PUBLIC);
        $property_info = array_map(function($property){
            return $this->getInterfacePropertyInfo($property);
        }, $properties);
        return $property_info;
    }

    protected function getInterfacePropertyInfo(\ReflectionProperty $property)
    {
        $property_info = new PropertyInfo($property);
        return $property_info->process();
    }

    protected function dirRecursiveDel($dir)
    {
        $files = array_diff(scandir($dir), array('.', '..'));

        foreach ($files as $file) {
            (is_dir("$dir/$file")) ? $this->dirRecursiveDel("$dir/$file") : unlink("$dir/$file");
        }
        return rmdir($dir);
    }

    public function generate()
    {
        throw new \Exception('Implement generate Method');
    }

    /**
     * @param $controller_data
     * @return MethodInfo
     */
    protected function getApiMethodInformation($controller_data)
    {
        // todo: encapsulate this to a separate class
        $method_info = new MethodInfo();
        $method_info->action = $controller_data["action"];
        $method_info->method = $controller_data["method"];
        $method_info->path = $controller_data["path"];
        $method_info->assigned_name = $controller_data["assigned_name"];
        $method = (new \ReflectionClass(config()->sdk->default_controller_namespace . $controller_data['controller']))->getMethod($controller_data['action']);
        $method_info->doc_block = $method->getDocComment();
        $method_info->parameters = $method->getParameters();
        if(PHP_MAJOR_VERSION >= 7) {
            $method_info->return_type = $method->getReturnType();
        }
        preg_match_all('/@[aA]piResponse (.+)(?:\n)/', $method_info->doc_block, $matches);
        $method_info->response_type = end($matches)[0];

        preg_match_all('/@[aA]piBody (.+)(?:\n)/', $method_info->doc_block, $matches);
        $last = end($matches);
        if(array_key_exists(0, $last)){
            $method_info->accept_type = $last[0];
        }
        return $method_info;
    }
}
