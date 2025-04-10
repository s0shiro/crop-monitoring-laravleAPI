<?php

namespace App\Services;

use App\Models\Farmer;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AdminFarmerService
{
    public function getFarmers(int $cursor = 0, int $limit = 9)
    {
        $farmers = Farmer::with(['association', 'technician'])
            ->orderBy('id', 'desc')
            ->skip($cursor)
            ->take($limit + 1)
            ->get();

        return [
            'data' => $farmers->take($limit),
            'nextCursor' => $farmers->count() > $limit ? $cursor + $limit : null,
        ];
    }

    public function createFarmer(array $data): Farmer
    {
        if (!isset($data['technician_id'])) {
            throw ValidationException::withMessages([
                'technician_id' => ['A technician must be assigned when creating a farmer.']
            ]);
        }

        return Farmer::create($data);
    }

    public function updateFarmer(Farmer $farmer, array $data): Farmer
    {
        if (!isset($data['technician_id'])) {
            throw ValidationException::withMessages([
                'technician_id' => ['A technician must be specified when updating a farmer.']
            ]);
        }

        $farmer->update($data);
        return $farmer->fresh(['association', 'technician']);
    }

    public function deleteFarmer(Farmer $farmer): bool
    {
        return $farmer->delete();
    }
}