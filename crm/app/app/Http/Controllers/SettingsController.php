<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Support\SettingsSchema;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function edit()
    {
        $groups = SettingsSchema::groups();
        $values = collect(SettingsSchema::fields())->map(fn ($f, $key) => Setting::get($key))->all();

        return view('settings.edit', compact('groups', 'values'));
    }

    public function update(Request $request)
    {
        $fields = SettingsSchema::fields();
        $rules = [];
        $input = [];
        foreach ($fields as $key => $field) {
            $name = str_replace('.', '__', $key);   // dots in form names would nest the input
            $rules[$name] = $field['rules'];
            $input[$name] = $field['type'] === 'checkbox' ? $request->boolean($name) : $request->input($name);
        }

        $data = validator($input, $rules, [], collect($fields)->mapWithKeys(fn ($f, $k) => [str_replace('.', '__', $k) => $f['label']])->all())->validate();

        foreach ($fields as $key => $field) {
            $value = $data[str_replace('.', '__', $key)] ?? null;
            if ($field['type'] === 'number' && $value !== null) {
                $value = $value + 0;
            }
            if (Setting::get($key) !== $value) {
                Setting::put($key, $value, null, $request->user()->id);
            }
        }

        return redirect()->route('settings.edit')->with('status', 'Settings saved.');
    }
}
