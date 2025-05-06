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
            'search' => 'nullable|string',
            'role' => 'nullable|string|in:technician,coordinator,all',
            'sortBy' => 'nullable|string|in:name,email,created_at',
            'sortDirection' => 'nullable|string|in:asc,desc',
        ]);

        $cursor = $request->input('cursor', 0);
        $limit = 9;
        $search = $request->input('search');
        $role = $request->input('role');
        $sortBy = $request->input('sortBy', 'created_at');
        $sortDirection = $request->input('sortDirection', 'desc');

        $query = User::with(['roles', 'coordinator']);

        // Apply search filter
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('username', 'like', "%{$search}%");
            });
        }

        // Apply role filter
        if ($role && $role !== 'all') {
            $query->whereHas('roles', function($q) use ($role) {
                $q->where('name', $role);
            });
        }

        // Apply sorting
        $query->orderBy($sortBy, $sortDirection);

        $users = $query->skip($cursor)
            ->take($limit + 1)
            ->get()
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'username' => $user->username,
                    'name' => $user->name,
                    'email' => $user->email,
                    'created_at' => $user->created_at,
                    'roles' => $user->roles->pluck('name'),
                    'coordinator' => $user->coordinator ? [
                        'id' => $user->coordinator->id,
                        'name' => $user->coordinator->name
                    ] : null
                ];
            });

        $nextCursor = $users->count() > $limit ? $cursor + $limit : null;

        return response()->json([
            'data' => $users->take($limit),
            'nextCursor' => $nextCursor,
            'total' => User::count() // Add total for pagination info
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
            'coordinator_id' => 'required_if:role,technician|exists:users,id|nullable',
        ], [
            'coordinator_id.required_if' => 'A technician must be assigned to a coordinator.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // If role is technician, ensure coordinator_id is provided
        if ($request->role === 'technician' && !$request->coordinator_id) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => ['coordinator_id' => ['A technician must be assigned to a coordinator']]
            ], 422);
        }

        // Verify coordinator exists and has coordinator role if coordinator_id is provided
        if ($request->coordinator_id) {
            $coordinator = User::findOrFail($request->coordinator_id);
            if (!$coordinator->hasRole('coordinator')) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => ['coordinator_id' => ['Selected user is not a coordinator']]
                ], 422);
            }
        }

        $user = User::create([
            'username' => $request->username,
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'coordinator_id' => $request->role === 'technician' ? $request->coordinator_id : null,
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
            'coordinator_id' => 'required_if:role,technician|exists:users,id|nullable',
        ], [
            'coordinator_id.required_if' => 'A technician must be assigned to a coordinator.',
        ]);

        // If role is being changed to technician, ensure coordinator_id is provided
        if ($request->role === 'technician' && !$request->coordinator_id) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => ['coordinator_id' => ['A technician must be assigned to a coordinator']]
            ], 422);
        }

        // Verify coordinator exists and has coordinator role if coordinator_id is provided
        if ($request->coordinator_id) {
            $coordinator = User::findOrFail($request->coordinator_id);
            if (!$coordinator->hasRole('coordinator')) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => ['coordinator_id' => ['Selected user is not a coordinator']]
                ], 422);
            }
        }

        $updateData = [
            'username' => $request->username ?? $user->username,
            'name' => $request->name ?? $user->name,
            'email' => $request->email ?? $user->email,
        ];

        if ($request->password) {
            $updateData['password'] = Hash::make($request->password);
        }

        // Update coordinator_id based on role
        if ($request->role) {
            if ($request->role === 'technician') {
                $updateData['coordinator_id'] = $request->coordinator_id;
            } else {
                $updateData['coordinator_id'] = null; // Remove coordinator if user becomes a coordinator
            }
        }

        $user->update($updateData);

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

    /**
     * Get all coordinators
     */
    public function getCoordinators()
    {
        $coordinators = User::whereHas('roles', function ($query) {
            $query->where('name', 'coordinator');
        })->get(['id', 'name', 'email']);

        return response()->json($coordinators);
    }

    /**
     * Get technicians assigned to the logged-in coordinator
     */
    public function getMyTechnicians(Request $request)
    {
        $request->validate([
            'cursor' => 'nullable|integer|min:0',
            'search' => 'nullable|string',
        ]);

        $user = auth()->user();
        
        if (!$user->hasRole('coordinator')) {
            return response()->json(['message' => 'Unauthorized. Only coordinators can access this endpoint.'], 403);
        }

        $cursor = $request->input('cursor', 0);
        $limit = 9;
        $search = $request->input('search');

        $query = $user->technicians()->with('roles');

        // Apply search filter if provided
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('username', 'like', "%{$search}%");
            });
        }

        // Apply pagination
        $technicians = $query->orderBy('created_at', 'desc')
            ->skip($cursor)
            ->take($limit + 1)
            ->get()
            ->map(function ($technician) {
                return [
                    'id' => $technician->id,
                    'username' => $technician->username,
                    'name' => $technician->name,
                    'email' => $technician->email,
                    'created_at' => $technician->created_at,
                    'roles' => $technician->roles->pluck('name')
                ];
            });

        $nextCursor = $technicians->count() > $limit ? $cursor + $limit : null;

        return response()->json([
            'data' => $technicians->take($limit),
            'nextCursor' => $nextCursor,
            'total' => $user->technicians()->count() // Add total count
        ]);
    }
}
