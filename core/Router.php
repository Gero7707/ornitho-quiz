<?php

class Router {
    private $routes = [];

    public function add($method, $url, $controller, $action) {
        $this->routes[] = [
            'method'     => strtoupper($method),
            'url'        => $url,
            'controller' => $controller,
            'action'     => $action
        ];
    }

    public function dispatch($url) {
        $httpMethod = $_SERVER['REQUEST_METHOD'];
        $url = parse_url($url, PHP_URL_PATH);
        $url = rtrim($url, '/') ?: '/';

        foreach ($this->routes as $route) {
            $pattern = preg_replace('/:([a-z]+)/', '([^/]+)', $route['url']);
            if (
                $route['method'] === $httpMethod &&
                preg_match('#^' . $pattern . '$#', $url, $matches)
            ) {
                array_shift($matches);
                $controllerName = $route['controller'];
                $actionName     = $route['action'];

                if (!class_exists($controllerName)) {
                    http_response_code(500);
                    echo "Controller introuvable : $controllerName";
                    return;
                }

                $controller = new $controllerName();

                if (!method_exists($controller, $actionName)) {
                    http_response_code(500);
                    echo "Méthode introuvable : $actionName";
                    return;
                }

                return $controller->$actionName(...$matches);
            }
        }

        http_response_code(404);
        echo "Page non trouvée : " . htmlspecialchars($url);
    }
}