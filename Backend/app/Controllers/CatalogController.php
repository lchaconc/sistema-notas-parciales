<?php

namespace App\Controllers;

use App\Services\CatalogService;

class CatalogController extends ApiController
{
    public function index()
    {
        return $this->runAction(fn () => (new CatalogService())->all());
    }

    public function create()
    {
        return $this->runAction(fn () => (new CatalogService())->create($this->body()), 201);
    }

    public function update(int $id)
    {
        return $this->runAction(fn () => (new CatalogService())->update($id, $this->body()));
    }

    public function delete(int $id)
    {
        return $this->runAction(fn () => (new CatalogService())->delete($id));
    }
}
