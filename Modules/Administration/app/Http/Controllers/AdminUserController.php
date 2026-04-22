<?php

namespace Modules\Administration\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;

class AdminUserController extends Controller
{
    public function index(Request $request)
    {
        $query = User::query();

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $perPage = $request->query('per_page', 15);
        $users = $query->orderBy('created_at', 'desc')->paginate($perPage);

        $users->getCollection()->transform(function ($user) {
            $user->two_factor_enabled = $user->hasTwoFactorEnabled();
            return $user;
        });

        return response()->json($users);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'role' => 'required|in:admin,user',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
        ]);

        return response()->json($user, 201);
    }

    public function show(User $admin_user)
    {
        $admin_user->two_factor_enabled = $admin_user->hasTwoFactorEnabled();
        return response()->json($admin_user);
    }

    public function update(Request $request, User $admin_user)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($admin_user->id),
            ],
            'password' => 'nullable|string|min:8|confirmed',
            'role' => 'required|in:admin,user',
        ]);

        $admin_user->name = $validated['name'];
        $admin_user->email = $validated['email'];
        $admin_user->role = $validated['role'];

        if (!empty($validated['password'])) {
            $admin_user->password = Hash::make($validated['password']);
        }

        $admin_user->save();

        return response()->json($admin_user);
    }

    public function destroy(Request $request, User $admin_user)
    {
        $currentUser = $request->user();

        if ($admin_user->id === $currentUser->id) {
            return response()->json([
                'message' => 'You cannot delete your own account.',
            ], 403);
        }

        $adminCount = User::where('role', 'admin')->count();
        if ($adminCount <= 1) {
            return response()->json([
                'message' => 'Cannot delete the last remaining admin user.',
            ], 403);
        }

        if (class_exists(\Modules\FormBuilder\Models\FormConfig::class)
            && \Modules\FormBuilder\Models\FormConfig::where('created_by', $admin_user->id)->exists()) {
            return response()->json([
                'message' => 'Cannot delete user because they have created one or more forms. Please reassign or delete the forms first.',
            ], 403);
        }

        $admin_user->delete();

        return response()->json(['message' => 'Admin user deleted successfully.']);
    }

    public function downloadTemplate()
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->setCellValue('A1', 'Name');
        $sheet->setCellValue('B1', 'Email');
        $sheet->setCellValue('C1', 'Role');
        $sheet->setCellValue('D1', 'Password');

        $sheet->setCellValue('A2', 'John Doe');
        $sheet->setCellValue('B2', 'john.doe@example.com');
        $sheet->setCellValue('C2', 'user');
        $sheet->setCellValue('D2', 'secretpassword');

        $writer = new Xlsx($spreadsheet);
        
        $fileName = 'admin_users_template.xlsx';
        $tempFile = tempnam(sys_get_temp_dir(), 'tpl');
        $writer->save($tempFile);

        return response()->download($tempFile, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    public function bulkUpload(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
        ]);

        $file = $request->file('file');
        
        try {
            $spreadsheet = IOFactory::load($file->getPathname());
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray();
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to read the file.'], 400);
        }

        if (count($rows) <= 1) {
            return response()->json(['message' => 'The file does not contain any data.'], 400);
        }

        $successCount = 0;
        $errors = [];

        for ($i = 1; $i < count($rows); $i++) {
            $row = $rows[$i];
            
            if (empty(array_filter($row))) {
                continue;
            }

            $name = $row[0] ?? null;
            $email = $row[1] ?? null;
            $role = isset($row[2]) ? strtolower(trim($row[2])) : 'user';
            $password = $row[3] ?? null;

            $data = [
                'name' => $name,
                'email' => $email,
                'role' => $role,
                'password' => $password,
                'password_confirmation' => $password,
            ];

            $validator = Validator::make($data, [
                'name' => 'required|string|max:255',
                'email' => 'required|email|max:255|unique:users,email',
                'role' => 'required|in:admin,user',
                'password' => 'required|string|min:8',
            ]);

            if ($validator->fails()) {
                $errors[] = "Row " . ($i + 1) . ": " . implode(' ', $validator->errors()->all());
                continue;
            }

            User::create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make($password),
                'role' => $role,
            ]);

            $successCount++;
        }

        return response()->json([
            'message' => "Bulk import completed. Successfully imported {$successCount} users.",
            'success_count' => $successCount,
            'errors' => $errors,
        ]);
    }
}
