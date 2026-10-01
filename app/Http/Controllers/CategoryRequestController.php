<?php

namespace App\Http\Controllers;

use App\Models\CategoryRequest;
use App\Services\SafeMetadataService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class CategoryRequestController extends Controller
{
    public function create(): View
    {
        return view('marketplace.category-request');
    }

    public function store(Request $request, SafeMetadataService $metadata): RedirectResponse
    {
        $data = $request->validate([
            'requested_name' => ['required', 'string', 'max:100'],
            'reason' => ['nullable', 'string', 'max:2000'],
            'example_url' => ['nullable', 'string', 'max:500'],
            'requester_email' => ['nullable', 'email:rfc', 'max:254'],
            'website' => ['nullable', 'max:0'],
        ]);

        if (filled($data['example_url'] ?? null)) {
            try {
                $normalized = $metadata->normalize((string) $data['example_url']);
                $data['example_url'] = $normalized['url'];
            } catch (Throwable $error) {
                return back()->withInput()->withErrors(['example_url' => $error->getMessage()]);
            }
        }

        CategoryRequest::query()->create($data);

        return redirect()->route('category-requests.create')->with('success', 'Category request received.');
    }
}
