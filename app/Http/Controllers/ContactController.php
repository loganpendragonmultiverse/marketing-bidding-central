<?php

namespace App\Http\Controllers;

use App\Models\ContactMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ContactController extends Controller
{
    public function create(): View
    {
        return view('marketplace.contact');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'email' => ['required', 'email', 'max:190'],
            'subject' => ['required', 'string', 'max:160'],
            'message' => ['required', 'string', 'max:12000'],
            'address' => ['nullable', 'max:0'],
        ]);
        unset($data['address']);
        ContactMessage::query()->create($data);

        return redirect()->route('contact')->with('success', 'Your message was received.');
    }

    public function index(): View
    {
        return view('admin.messages', ['messages' => ContactMessage::query()->latest()->paginate(30)]);
    }

    public function resolve(ContactMessage $message): RedirectResponse
    {
        $message->forceFill(['resolved_at' => now()])->save();

        return back()->with('success', 'Message marked resolved.');
    }
}
