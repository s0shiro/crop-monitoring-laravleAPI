<?php

namespace App\Services;

use App\Models\Farmer;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AdminFarmerService
{
    public function getFarmers(int $cursor = 0, int $limit = 9, ?string $search = null, ?string $association = 'all', ?string $sortBy = 'created_at', ?string $sortDirection = 'desc')
    {
        $query = Farmer::with(['association', 'technician']);
        
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('rsbsa', 'like', "%{$search}%")
                  ->orWhere('barangay', 'like', "%{$search}%")
                  ->orWhere('municipality', 'like', "%{$search}%");
            });
        }

        if ($association !== 'all') {
            $query->where('association_id', $association);
        }

        // Handle sorting
        $sortBy = in_array($sortBy, ['name', 'created_at', 'barangay', 'municipality']) ? $sortBy : 'created_at';
        $sortDirection = in_array($sortDirection, ['asc', 'desc']) ? $sortDirection : 'desc';
        
        $query->orderBy($sortBy, $sortDirection);

        $farmers = $query->skip($cursor)
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