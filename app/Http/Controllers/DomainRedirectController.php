<?php
// app/Http/Controllers/DomainRedirectController.php

namespace App\Http\Controllers;

use App\Models\DomainRedirect;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class DomainRedirectController extends Controller
{
    /**
     * Display a listing of the domain redirects.
     */
    public function index()
    {
        $domainRedirects = DomainRedirect::latest()->get();
        
        return response()->json([
            'success' => true,
            'domain_redirects' => $domainRedirects
        ]);
    }

    /**
     * Store a newly created domain redirect.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'url' => 'required|url|max:255',
            'status' => 'sometimes|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $data = [
            'url' => $request->url,
            'status' => $request->status ?? true
        ];

        $domainRedirect = DomainRedirect::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Domain redirect created successfully',
            'domain_redirect' => $domainRedirect
        ], 201);
    }

    /**
     * Display the specified domain redirect.
     */
    public function show(DomainRedirect $domainRedirect)
    {
        return response()->json([
            'success' => true,
            'domain_redirect' => $domainRedirect
        ]);
    }

    /**
     * Update the specified domain redirect.
     */
    public function update(Request $request, DomainRedirect $domainRedirect)
    {
        $validator = Validator::make($request->all(), [
            'url' => 'sometimes|url|max:255',
            'status' => 'sometimes|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $data = [];

        if ($request->has('url')) {
            $data['url'] = $request->url;
        }

        if ($request->has('status')) {
            $data['status'] = $request->status;
        }

        $domainRedirect->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Domain redirect updated successfully',
            'domain_redirect' => $domainRedirect
        ]);
    }

    /**
     * Remove the specified domain redirect.
     */
    public function destroy(DomainRedirect $domainRedirect)
    {
        $domainRedirect->delete();

        return response()->json([
            'success' => true,
            'message' => 'Domain redirect deleted successfully'
        ]);
    }

    /**
     * Toggle domain redirect status.
     */
    public function toggleStatus(DomainRedirect $domainRedirect)
    {
        $domainRedirect->status = !$domainRedirect->status;
        $domainRedirect->save();

        return response()->json([
            'success' => true,
            'message' => 'Domain redirect status updated successfully',
            'status' => $domainRedirect->status
        ]);
    }

    /**
     * Get active domain redirects (public endpoint).
     */
    public function getActiveRedirects()
    {
        $domainRedirects = DomainRedirect::active()->latest()->get();

        return response()->json([
            'success' => true,
            'domain_redirects' => $domainRedirects
        ]);
    }
}