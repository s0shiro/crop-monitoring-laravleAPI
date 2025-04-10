<?php

namespace App\Services;

use App\Models\Farmer;
use Illuminate\Support\Facades\Auth;
use Illuminate\Auth\Access\AuthorizationException;

class TechnicianFarmerService
{
    public function getFarmers(int $cursor = 0, int $limit = 9)
    {
        $farmers = Farmer::where('technician_id', Auth::id())
            ->with(['association', 'technician'])
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