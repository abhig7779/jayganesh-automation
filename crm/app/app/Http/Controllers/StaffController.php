<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\User;
use App\Support\Permissions;
use App\Support\Phone;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StaffController extends Controller
{
    public function index()
    {
        $staff = User::query()->with(['roles', 'branch'])->orderByDesc('is_active')->orderBy('name')->get();

        return view('staff.index', compact('staff'));
    }

    public function create(Request $request)
    {
        return view('staff.form', [
            'user' => new User(['is_active' => true, 'branch_id' => $request->user()->branch_id]),
            'roles' => $this->assignableRoles($request->user()),
            'branches' => Branch::query()->where('is_active', true)->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'],
            'password' => $data['password'],
            'branch_id' => $data['branch_id'],
            'is_active' => true,
            'created_by' => $request->user()->id,
        ]);
        $user->syncRoles([$data['role']]);
        $this->syncExtraAccess($request, $user);

        return redirect()->route('staff.index')->with('status', "{$user->name} can now sign in.");
    }

    public function edit(Request $request, User $staff)
    {
        $this->guardOwner($request->user(), $staff);

        return view('staff.form', [
            'user' => $staff,
            'roles' => $this->assignableRoles($request->user()),
            'branches' => Branch::query()->where('is_active', true)->get(),
        ]);
    }

    public function update(Request $request, User $staff)
    {
        $this->guardOwner($request->user(), $staff);
        $data = $this->validated($request, $staff);

        if ($staff->is($request->user()) && ! ($data['is_active'] ?? true)) {
            return back()->withErrors(['is_active' => "You can't switch off your own login."]);
        }

        $staff->fill([
            'name' => $data['name'],
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'],
            'branch_id' => $data['branch_id'],
            'is_active' => (bool) ($data['is_active'] ?? false),
        ]);
        if (! empty($data['password'])) {
            $staff->password = $data['password'];
        }
        $staff->save();

        if (! $staff->is($request->user())) {
            $staff->syncRoles([$data['role']]);
            $this->syncExtraAccess($request, $staff);
        }

        return redirect()->route('staff.index')->with('status', 'Saved.');
    }

    private function validated(Request $request, ?User $user = null): array
    {
        $request->merge([
            'phone' => Phone::normalize($request->input('phone')),
            'email' => $request->filled('email') ? Str::lower($request->input('email')) : null,
        ]);

        return $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'phone' => ['required', 'regex:/^\+91[6-9]\d{9}$/', Rule::unique('users', 'phone')->ignore($user)],
            'email' => ['nullable', 'email', 'max:120', Rule::unique('users', 'email')->ignore($user)],
            'role' => ['required', Rule::in(array_keys($this->assignableRoles($request->user())))],
            'branch_id' => ['required', 'exists:branches,id'],
            'password' => [$user ? 'nullable' : 'required', 'confirmed', Password::min(8)],
            'is_active' => ['nullable', 'boolean'],
            'extra' => ['nullable', 'array'],
            'extra.*' => ['string', Rule::in(array_keys(Permissions::EXTRA_ACCESS))],
        ], [
            'phone.regex' => 'Enter a 10-digit Indian mobile number.',
        ]);
    }

    /**
     * Extra access (owner rule, 5 Nov 2026): the Owner can switch on single permissions for one
     * person on top of their role, e.g. leads for one counter boy. Stored as spatie direct
     * permissions. Only someone with settings.manage (the Owner) can change them; anyone else
     * saving the form leaves them as they were. Permissions the role already has are not stored.
     */
    private function syncExtraAccess(Request $request, User $user): void
    {
        if (! $request->user()->can('settings.manage')) {
            return;
        }
        $role = $user->roles()->first();
        $fromRole = $role ? $role->permissions->pluck('name')->all() : [];
        $wanted = array_values(array_diff(
            array_intersect((array) $request->input('extra', []), array_keys(Permissions::EXTRA_ACCESS)),
            $fromRole,
        ));
        $user->syncPermissions($wanted);
    }

    /** Only the Owner can create or edit Owners and Admins. */
    private function assignableRoles(User $actor): array
    {
        $labels = Permissions::ROLE_LABELS;

        return $actor->hasRole('owner') ? $labels : array_diff_key($labels, array_flip(['owner', 'admin']));
    }

    private function guardOwner(User $actor, User $target): void
    {
        abort_if($target->hasAnyRole(['owner', 'admin']) && ! $actor->hasRole('owner') && ! $actor->is($target), 403);
    }
}
