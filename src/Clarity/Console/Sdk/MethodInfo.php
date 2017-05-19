<?php

namespace Clarity\Console\Sdk;


class MethodInfo
{
    public $method;
    public $path;
    public $assigned_name;
    public $action;
    public $response_type;
    public $doc_block;
    /**
     * @var \ReflectionParameter[]
     */
    public $parameters;
    public $return_type;
    public $accept_type;
}
