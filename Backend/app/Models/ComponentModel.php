<?php

namespace App\Models;

use CodeIgniter\Model;

class ComponentModel extends Model
{
    protected $table = 'componentes_evaluacion';
    protected $primaryKey = 'id';
    protected $allowedFields = ['curso_id', 'actividad_catalogo_id', 'titulo', 'cantidad_actividades', 'descripcion', 'porcentaje_total', 'puntaje_total'];
    protected $returnType = 'array';
}
