<?php

namespace App\Services;

use App\Exceptions\ApiError;
use App\Models\CatalogModel;
use App\Support\Input;

class CatalogService
{
    private CatalogModel $catalog;

    public function __construct()
    {
        $this->catalog = new CatalogModel();
    }

    public function all(): array
    {
        $rows = db_connect()->table('catalogo_actividades ca')
            ->select('ca.id, ca.nombre')
            ->select('EXISTS (SELECT 1 FROM componentes_evaluacion c WHERE c.actividad_catalogo_id = ca.id) AS en_uso', false)
            ->orderBy('ca.nombre')->get()->getResultArray();
        return array_map(static fn (array $row): array => [...$row, 'en_uso' => (bool) $row['en_uso']], $rows);
    }

    public function create(array $data): array
    {
        $name = Input::text($data, 'nombre', 255);
        if ($this->catalog->where('nombre', $name)->first()) {
            throw new ApiError('La actividad ya existe en el catálogo.', 409);
        }
        return [...$this->catalog->find($this->catalog->insert(['nombre' => $name], true)), 'en_uso' => false];
    }

    public function update(int $id, array $data): array
    {
        $this->available($id);
        $name = Input::text($data, 'nombre', 255);
        $duplicate = $this->catalog->where('nombre', $name)->where('id !=', $id)->first();
        if ($duplicate) {
            throw new ApiError('La actividad ya existe en el catálogo.', 409);
        }
        $this->catalog->update($id, ['nombre' => $name]);
        return [...$this->catalog->find($id), 'en_uso' => false];
    }

    public function delete(int $id): array
    {
        $this->available($id);
        $this->catalog->delete($id);
        return ['eliminado' => true];
    }

    private function available(int $id): void
    {
        if (!$this->catalog->find($id)) {
            throw new ApiError('Actividad no encontrada.', 404);
        }
        if (db_connect()->table('componentes_evaluacion')->where('actividad_catalogo_id', $id)->countAllResults() > 0) {
            throw new ApiError('La actividad está asociada a un componente y no puede modificarse.', 409);
        }
    }
}
