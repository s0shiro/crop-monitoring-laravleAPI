<?php

namespace App\Services;

use App\Models\Farmer;
use Illuminate\Support\Facades\Auth;
use Illuminate\Auth\Access\AuthorizationException;

class TechnicianFarmerService
{
    public function getFarmers(int $cursor = 0, int $limit = 9, ?string $search = null, ?string $association = 'all', ?string $sortBy = 'created_at', ?string $sortDirection = 'desc')
    {
        $query = Farmer::where('technician_id', Auth::id())
            ->with(['association', 'technician']);

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
        // Always assign the farmer to the current technician
        $data['technician_id'] = Auth::id();
        return Farmer::create($data);
    }

    public function updateFarmer(Farmer $farmer, array $data): Farmer
    {
        if ($farmer->technician_id !== Auth::id()) {
            throw new AuthorizationException('You can only update farmers assigned to you.');
        }

        // Remove technician_id from the data if it exists
        unset($data['technician_id']);
        
        $farmer->update($data);
        return $farmer->fresh(['association', 'technician']);
    }

    public function deleteFarmer(Farmer $farmer): bool
    {
        if ($farmer->technician_id !== Auth::id()) {
            throw new AuthorizationException('You can only delete farmers assigned to you.');
        }

        return $farmer->delete();
    }

    public function canManageFarmer(Farmer $farmer): bool
    {
        return $farmer->technician_id === Auth::id();
    }
}