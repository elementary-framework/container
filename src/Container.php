<?php

namespace Elementary\Container;

use Exception;
use Psr\Container\ContainerInterface;
use ReflectionClass;
use ReflectionException;

class Container implements ContainerInterface
{
    const DEFAULT_SERVICE_RESOLVER_LOCATION = __DIR__ . '/config.php';
    const DEFAULT_SERVICE_RESOLVER_LOGS_LOCATION = __DIR__ . '/container.log';

    /** @var ContainerInterface */
    static $instance = null;

    /**
     * @var array
     */
    protected $resolved = [];

    /**
     * @var array
     */
    protected $serviceResolver = [];

    /**
     * @var string
     */
    protected $containerLogs;


    /**
     * Container constructor.
     * @param null $serviceResolver
     * @param null $containerLogs
     */
    protected function __construct($serviceResolver = null, $containerLogs = null)
    {
        $this->containerLogs = $containerLogs ?? self::DEFAULT_SERVICE_RESOLVER_LOGS_LOCATION;
        $serviceResolver = $serviceResolver ?? self::DEFAULT_SERVICE_RESOLVER_LOCATION;
        $this->boot($serviceResolver);
        return $this;
    }

    /**
     * @param null $serviceResolver
     * @param null $containerLogs
     * @return Container
     */
    public static function getInstance($serviceResolver = null, $containerLogs = null)
    {
        if (!self::$instance) {
            self::$instance = new Container($serviceResolver, $containerLogs);
        }
        return self::$instance;
    }

    /**
     * @param $id
     * @return bool
     */
    public function has($id)
    {
        return array_key_exists($id, $this->resolved);
    }

    /**
     * @param $id
     * @return object
     * @throws Exception
     */
    public function get($id)
    {
        if ($this->has($id)) {
            $concrete = $this->resolved[$id];
        } else {
            $concrete = $this->resolve($id);
            $this->resolved[$id] = $concrete;
        }

        return $concrete;
    }

    /**
     * @param $id
     * @return mixed
     * @throws Exception
     */
    protected function resolve($id)
    {
        if (array_key_exists($id, $this->serviceResolver)) {
            return $this->create($this->serviceResolver[$id]);
        } else if(class_exists($id)) {
            return $this->create($id);
        }
    }

    /**
     * @param $serviceResolver
     */
    protected function boot($serviceResolver)
    {
        if (file_exists($serviceResolver)) {
            $this->serviceResolver = require_once $serviceResolver;
        }
    }

    /**
     * @param $id
     * @return object
     * @throws ReflectionException
     */
    public function create($id)
    {
        if (is_array($id) && class_exists($id['concrete'])) {
            return $this->createServiceInstance($id['concrete'], $id['constructor_params']);
        } else if (is_string($id) && class_exists($id) && $reflection = new ReflectionClass($id)) {
            return $this->createServiceInstance($id);
        }
        throw new Exception('No service found!');
    }

    /**
     * @param $concrete
     * @param array $constructorParams
     * @return object
     * @throws ReflectionException
     */
    public function createServiceInstance($concrete, $constructorParams = [])
    {
        $reflection = new ReflectionClass($concrete);
        if ($reflection->isInstantiable()) {
            $constructor = $reflection->getConstructor();
            if ($constructor) {
                $parameters = $constructor->getParameters();
                $params = [];
                foreach ($parameters as $parameter) {
                    if ($constructorParams[$parameter->name]) {
                        if (!$parameter->getType() || $parameter->getType()->isBuiltin()) {
                            $params[$parameter->name] = $constructorParams[$parameter->name];
                        } else if ($parameter->getType() && $parameter->getType()->allowsNull()) {
                            $params[$parameter->name] = null;
                        } else {
                            $params[$parameter->name] = $this->resolve($constructorParams[$parameter->name]);
                        }
                    } else {
                        throw new Exception('Couldn\'t resolve parameter ' . $parameter->name . ' of class ' . $reflection->getName());
                    }
                }
            } else {
                $params = [];
            }
            return $reflection->newInstanceArgs($params);
        }
    }

}