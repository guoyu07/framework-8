<?php

namespace Clarity\Console\Sdk;

class TypeScriptSdk extends Sdk
{
    protected $name = "sdk:typescript";
    protected $description = "Generate TypeScript SDK for API";

    public function generate()
    {
        $this->cleanOutDir();
        $this->initOutDir();
        $this->makePackageFile();
        $this->makeTypeScriptConfigFile();
        $this->makeNpmIgnoreFile();
        $this->dumpInterfaces();
        $this->makeBaseApi();
        $this->prepareMiddlewares();
        $this->dumpControllers();
    }

    protected function makeTypeScriptConfigFile()
    {
        file_put_contents(
            config()->path->root . "/build/TypeScript/tsconfig.json",
            file_get_contents(__DIR__ . "/Resources/tsconfig.json.stub")
        );
    }

    protected function makeNpmIgnoreFile()
    {
        file_put_contents(
            config()->path->root . "/build/TypeScript/.npmignore",
            file_get_contents(__DIR__ . "/Resources/.npmignore.stub")
        );
    }

    protected function cleanOutDir()
    {
        $this->dirRecursiveDel(config()->path->root . "/build");
    }

    protected function initOutDir()
    {
        mkdir(config()->path->root . "/build/TypeScript/src/Middlewares", 0777, true);
    }

    protected function dumpInterfaces()
    {
        $interfaces = $this->getInterfaces();
        $interface_definition_data = file_get_contents(__DIR__ . '/Resources/interfaces.ts.stub');
        // delete interface file first
        foreach ($interfaces as $interface) {
            $interface_string = str_replace("{interface}", $interface->getShortName(), $interface_definition_data);
            $interface_string = str_replace("{definition}", $this->getTypeScriptInterfaceProperties($interface), $interface_string);
            file_put_contents(config()->path->root . "/build/TypeScript/src/Api.ts", $interface_string, FILE_APPEND);
        }
    }

    /**
     * @param \ReflectionClass $interface
     * @return string
     */
    protected function getTypeScriptInterfaceProperties(\ReflectionClass $interface)
    {
        $interface_data = $this->getInterfaceData($interface);
        $interface_string = "";
        foreach ($interface_data as $datum) {
            $interface_string .= "  " . $datum->name . (($datum->is_optional) ? "?: " : ": ") . $this->transformType($datum->type) . ",\n";
        }
        return $interface_string;
    }

    /**
     * @param $type string type of data
     * @return string type which typescript represents $type
     */
    protected function transformType($type)
    {
        switch ($type) {
            case "int":
            case "integer":
                return "number";
            default:
                return $type;
        }
    }

    protected function makePackageFile()
    {
        $package_data = file_get_contents(__DIR__ . '/Resources/package.json.stub');
        $package_data = str_replace("{NAME}", env("APP_NAME", "slayer"), $package_data);
        $package_data = str_replace("{API_VERSION}", env("API_VERSION", "0.0.1"), $package_data);
        file_put_contents(config()->path->root . "/build/TypeScript/package.json", $package_data);
    }

    private function makeBaseApi()
    {
        $baseApi = file_get_contents(__DIR__ . "/Resources/BaseApi.ts.stub");
        $api_path = env("SERVE_HOST", "localhost") . ":" . env("SERVE_PORT", "8082");
        $baseApi = str_replace("{BASE_URL}", $api_path, $baseApi);
        file_put_contents(config()->path->root . "/build/TypeScript/src/Api.ts", $baseApi, FILE_APPEND);
    }

    private function prepareMiddlewares()
    {
        $middleware = file_get_contents(__DIR__ . "/Resources/Middlewares/Json.ts.stub");
        file_put_contents(config()->path->root . "/build/TypeScript/src/Middlewares/Json.ts", $middleware);
    }

    private function dumpControllers()
    {
        $controllers = $this->array_group($this->getRoutes(), "controller");
        foreach ($controllers as $key => $controller) {
            $this->makeApi($key, $controller);
        }
    }

    private function array_group($array, $group_by)
    {
        $result = array();
        foreach ($array as $data) {
            $item = $data[$group_by];
            if (isset($result[$item])) {
                $result[$item][] = $data;
            } else {
                $result[$item] = array($data);
            }
        }
        return $result;
    }

    /**
     * @param $controller_methods array
     * @param $controller_name string
     */
    private function makeApi($controller_name, $controller_methods)
    {
        $method_stub = [];
        foreach ($controller_methods as $method) {
            $method_stub[] = $this->getApiMethodDefinition($this->getApiMethodInformation($method));
        }
        $apiStub = file_get_contents(__DIR__ . '/Resources/api.ts.stub');
        $apiStub = str_replace(["{API}", "{METHODS}"], [$controller_name, implode("\n", $method_stub)], $apiStub);
        file_put_contents(config()->path->root . "/build/TypeScript/src/Api.ts", $apiStub, FILE_APPEND);
    }

    /**
     * @param $methodInfo MethodInfo
     * @return bool|mixed|string
     */
    private function getApiMethodDefinition($methodInfo) {
        $methodStub = file_get_contents(__DIR__ . "/Resources/method.stub");
        $methodStub = str_replace(["{ACTION_NAME}", "{RESPONSE_TYPE}", "{URL}", "{METHOD}"], [$methodInfo->action, $methodInfo->response_type, preg_replace("/({\\w+})/", "$$1", $methodInfo->path), $methodInfo->method], $methodStub);
        if($methodInfo->accept_type) {
            $paramData = $this->getParams($methodInfo);
            $methodStub = str_replace(["{PARAMS}", "{BODY}"], ["data: " . $methodInfo->accept_type . ($paramData?", ":"") . $paramData , ", body: JSON.stringify(data)"], $methodStub);
        } else {
            $methodStub = str_replace(["{PARAMS}", "{BODY}"], [$this->getParams($methodInfo), ""], $methodStub);
        }
        return $methodStub;
        // check what params does controller expect
        // URL variables both ":" and "{}" format
    }

    private function getParams(MethodInfo $methodInfo)
    {
        $data = [];
        foreach($methodInfo->parameters as $parameter){
            $data[] = $parameter->name . ": " . $this->transformType($parameter->getType());
        }
        return implode(", ", $data);
    }
}
