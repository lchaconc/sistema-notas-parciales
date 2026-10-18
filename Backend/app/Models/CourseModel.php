<?php

namespace App\Models;

use CodeIgniter\Model;

class CourseModel extends Model
{
    protected $table = 'cursos';
    protected $primaryKey = 'id';
    protected $allowedFields = ['cuatrimestre_id', 'nombre', 'codigo', 'numero_grupo'];
    protected $returnType = 'array';
}
