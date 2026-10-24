<?php

class Router
{
    private array $routes = [];

    public function get(string $path, callable|array $handler): void
    {
        $this->addRoute('GET', $path, $handler);
    }

    public function post(string $path, callable|array $handler): void
    {
        $this->addRoute('POST', $path, $handler);
    }

    public function put(string $path, callable|array $handler): void
    {
        $this->addRoute('PUT', $path, $handler);
    }

    public function delete(string $path, callable|array $handler): void
    {
        $this->addRoute('DELETE', $path, $handler);
    }

    private function addRoute(
        string $method,
        string $path,
        callable|array $handler
    ): void {
        $this->routes[$method][$path] = $handler;
    }

    public function dispatch(string $method, string $uri): void
    {

    $path = parse_url($uri, PHP_URL_PATH);
    
    $scriptFolder = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
    
    if (!empty($scriptFolder) && str_starts_with($path, $scriptFolder)) {
        $path = substr($path, strlen($scriptFolder));
    }
    
    $path = '/' . trim($path, '/');

    $handler = $this->routes[$method][$path] ?? null;
    
    if ($handler === null) {
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode([
            'erro' => 'Rota nao encontrada'
        ]);
        return;
    }
    
    if (is_array($handler)) {
        [$controller, $action] = $handler;
        
        $instance = new $controller();
        $instance->$action();
        return;
    }
    
        call_user_func($handler);
    }
}