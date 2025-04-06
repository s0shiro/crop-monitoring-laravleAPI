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
    public function index()
    {
        $users = User::with('roles')->get()->map(function ($user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'created_at' => $user->created_at,
                'roles' => $user->roles->pluck('name')
            ];
        });

        return response()->json($users);
    }

    /**
     * Create a new user (Admin only)
     */
    public function store(Request $request)
    {
        $validator = validator($request->all(), [
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
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $user->id,
            'password' => 'nullable|string|min:6',
            'role' => 'sometimes|string|in:technician,coordinator',
        ]);

        $user->update([
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
}
