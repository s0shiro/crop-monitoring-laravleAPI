<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    /**
     * Get the authenticated user with roles and permissions
     */
    public function profile(Request $request)
    {
        $user = $request->user();

        // Get basic user data without the full relationship objects
        return response()->json([
            'user' => [
                'id' => $user->id,
                'username' => $user->username,
                'name' => $user->name,
                'email' => $user->email,
                'created_at' => $user->created_at,
            ],
            'roles' => $user->getRoleNames(),
            'permissions' => $user->getAllPermissions()->pluck('name')
        ]);
    }

    /**
     * Get all users (Only for Admin)
     */
    public function index(Request $request)
    {
        $request->validate([
            'cursor' => 'nullable|integer|min:0',
        ]);

        $cursor = $request->input('cursor', 0);
        $limit = 9;

        $users = User::with('roles')
            ->skip($cursor)
            ->take($limit + 1)
            ->get()
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'username' => $user->username,
                    'name' => $user->name,
                    'email' => $user->email,
                    'created_at' => $user->created_at,
                    'roles' => $user->roles->pluck('name')
                ];
            });

        $nextCursor = $users->count() > $limit ? $cursor + $limit : null;

        return response()->json([
            'data' => $users->take($limit),
            'nextCursor' => $nextCursor
        ]);
    }

    /**
     * Create a new user (Admin only)
     */
    public function store(Request $request)
    {
        $validator = validator($request->all(), [
            'username' => 'required|string|max:255|unique:users,username',
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
            'role' => 'required|string|in:technician,coordinator',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::create([
            'username' => $request->username,
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        // Assign role
        $user->assignRole($request->role);

        return response()->json(['message' => 'User created successfully', 'user' => $user], 201);
    }

    /**
     * Update user (Admin only)
     */
    public function update(Request $request, User $user)
    {
        $request->validate([
            'username' => 'sometimes|string|max:255|unique:users,username,' . $user->id,
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $user->id,
            'password' => 'nullable|string|min:6',
            'role' => 'sometimes|string|in:technician,coordinator',
        ]);

        $user->update([
            'username' => $request->username ?? $user->username,
            'name' => $request->name ?? $user->name,
            'email' => $request->email ?? $user->email,
            'password' => $request->password ? Hash::make($request->password) : $user->password,
        ]);

        if ($request->role) {
            $user->syncRoles([$request->role]);
        }

        return response()->json(['message' => 'User updated successfully', 'user' => $user]);
    }

    /**
     * Delete a user (Admin only)
     */
    public function destroy(User $user)
    {
        $user->delete();
        return response()->json(['message' => 'User deleted successfully']);
    }

    /**
     * Get a specific user's profile with roles and permissions (Admin only)
     */
    public function show(User $user)
    {
        return response()->json([
            'user' => [
                'id' => $user->id,
                'username' => $user->username,
                'name' => $user->name,
                'email' => $user->email,
                'created_at' => $user->created_at,
            ],
            'roles' => $user->getRoleNames(),
            'permissions' => $user->getAllPermissions()->pluck('name')
        ]);
    }

    /**
     * Update a specific user's permissions (Admin only)
     */
    public function updateUserPermissions(Request $request, User $user)
    {
        \Log::info('Updating permissions for user:', $request->all());

        $validator = \Validator::make($request->all(), [
            'permissions' => 'nullable|array',
            'permissions.*' => 'exists:permissions,name',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'One or more permissions do not exist.',
                'errors' => $validator->errors()
            ], 422);
        }

        // Sync the provided direct permissions with the user.
        // This will add any new permissions and remove any not present in the array.
        $user->syncPermissions($request->permissions ?? []);

        \Log::info('Direct permissions updated for user:',
            $user->getDirectPermissions()->pluck('name')->toArray()
        );

        return response()->json([
            'message' => 'User permissions updated successfully.',
            'direct_permissions' => $user->getDirectPermissions()->pluck('name')
        ]);
    }

    /**
     * Get all technicians
     */
    public function getTechnicians()
    {
        $technicians = User::whereHas('roles', function ($query) {
            $query->where('name', 'technician');
        })->get();

        return response()->json($technicians);
    }
}
