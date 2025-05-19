<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum'); // Ensure the user is authenticated
    }

    /**
     * List all users with optional search functionality.
     */
    public function index(Request $request)
    {
        $query = User::query();

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%$search%")
                  ->orWhere('email', 'like', "%$search%");
            });
        }

        return $query->paginate($request->get('per_page', 10));
    }

    /**
     * Show user details along with the last 3 transactions (borrowed books).
     */
    public function show(User $user)
    {
        try {
            $user->load(['transactions' => function ($query) {
                $query->with('book')->orderBy('created_at', 'desc')->limit(3);
            }]);

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'transactions' => $user->transactions->map(function ($transaction) {
                        return [
                            'id' => $transaction->id,
                            'book' => $transaction->book ? [
                                'title' => $transaction->book->title,
                                'id' => $transaction->book->id
                            ] : null,
                            'borrowed_date' => $transaction->borrowed_at,
                            'due_date' => $transaction->due_date,
                            'returned_date' => $transaction->returned_at,
                            'status' => $transaction->status
                        ];
                    })
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch user details',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the profile of a user.
     */
    public function update(Request $request, User $user)
    {
        // Validate input fields
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|string|email|max:255|unique:users,email,' . $user->id,
        ]);

        // Update user details
        $user->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully',
            'data' => $user
        ]);
    }

    /**
     * Delete a user.
     */
    public function destroy(User $user)
    {
        // Perform any necessary checks before deleting, like ensuring no active transactions
        $user->delete();
        return response()->json(['message' => 'User deleted successfully']);
    }

    /**
     * Change the user's password.
     */
    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        $user = $request->user();

        // Check if current password matches the stored password
        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Current password is incorrect'
            ], 401);
        }

        // Update the password
        $user->password = Hash::make($request->new_password);
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Password changed successfully'
        ]);
    }

    /**
     * List of borrowed books for the authenticated user.
     */
    public function borrowedBooks(Request $request)
    {
        try {
            $user = $request->user();

            // Get all borrowed books
            $borrowedBooks = $user->transactions()
                ->with('book')
                ->where('status', 'borrowed')
                ->orderBy('borrowed_at', 'desc')
                ->get()
                ->filter(fn($transaction) => $transaction->book) // Remove null books
                ->map(function ($transaction) {
                    return [
                        'id' => $transaction->book->id,
                        'title' => $transaction->book->title,
                        'author' => $transaction->book->author,
                        'publisher' => $transaction->book->publisher,
                        'borrowed_at' => optional($transaction->borrowed_at)->toDateTimeString(),
                        'due_date' => optional($transaction->due_date)->toDateTimeString(),
                        'available_copies' => $transaction->book->available_copies,
                        'total_copies' => $transaction->book->total_copies
                    ];
                })->values();

            return response()->json([
                'success' => true,
                'data' => $borrowedBooks
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch borrowed books',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Fetch user profile information for the authenticated user.
     */
    public function profile(Request $request)
    {
        try {
            $user = $request->user();

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch profile information',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Helper function to handle standard success response.
     */
    protected function successResponse($data = null, string $message = '', int $status = 200)
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $status);
    }

    /**
     * Helper function to handle standard error response.
     */
  
}
