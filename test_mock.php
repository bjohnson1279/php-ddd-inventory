<?php
require 'vendor/autoload.php';

use InventoryApp\Infrastructure\Http\RequestInterface;

class RequestMock implements RequestInterface
{
    private $body = '';

    public function __construct($body = '') {
        $this->body = $body;
    }

    public function getBody() {
        return $this->body;
    }

    public function validate(array $rules): array {
        return [];
    }

    public function query(string $key, $default = null) {
        return $default;
    }
}
$req = new RequestMock('foo');
echo $req->getBody();
