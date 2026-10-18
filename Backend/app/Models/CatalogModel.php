<?php

namespace App\Models;

use CodeIgniter\Model;

class CatalogModel extends Model
{
    protected $table = 'catalogo_actividades';
    protected $primaryKey = 'id';
    protected $allowedFields = ['nombre'];
    protected $returnType = 'array';
}
