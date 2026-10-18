<?php

namespace App\Models;

use CodeIgniter\Model;

class GradeModel extends Model
{
    protected $table = 'calificaciones';
    protected $primaryKey = 'id';
    protected $allowedFields = ['matricula_id', 'actividad_individual_id', 'puntos_obtenidos', 'observacion'];
    protected $returnType = 'array';
}
