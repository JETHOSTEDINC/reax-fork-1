<?php

namespace App\Http\Controllers;

use App\Models\Property;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PropertyController extends Controller
{
    public function index(Request $request)
    {
        $query = Property::query();

        // Search filter
        if ($request->filled('search')) {
            $query->where(function($q) use ($request) {
                $q->where('property_name', 'like', "%{$request->search}%")
                  ->orWhere('property_number', 'like', "%{$request->search}%");
            });
        }

        // Region filter
        if ($request->filled('region')) {
            $query->where('region', $request->region);
        }

        // Salesman filter
        if ($request->filled('salesman')) {
            $query->where('handler_id', $request->salesman);
        }

        // Price range filter
        if ($request->filled('price_range')) {
            [$min, $max] = explode('-', $request->price_range . '+');
            $query->when($max !== '+', function($q) use ($min, $max) {
                $q->whereBetween('total_price', [$min, $max]);
            }, function($q) use ($min) {
                $q->where('total_price', '>=', $min);
            });
        }

        // Type filter
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        // Status filter
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $properties = $query->paginate(12)->withQueryString();
        $users = User::where('company_id', auth()->user()->company_id)
                     ->orderBy('name')
                     ->get();
        $regions = ['cairo', 'giza', 'alexandria', 'north-coast']; // Add your regions

        // Calculate statistics
        $stats = [
            'total' => Property::count(),
            'available' => Property::where('status', 'available')->count(),
            'sold' => Property::where('status', 'sold')->count(),
            'rented' => Property::where('status', 'rented')->count(),
        ];

        $statuses = [
            'available',
            'reserved',
            'sold',
            'rented',
            'under_contract',
            'off_market'
        ];

        $propertyTypes = [
            'apartment',
            'villa',
            'office',
            'shop',
            'land',
            'building',
            'warehouse',
            'factory',
            'chalet',
            'farm',
            'hotel'
        ];

        return view('properties.index', compact('properties', 'users', 'regions', 'stats', 'propertyTypes', 'statuses'));
    }

    private function handleImageUrl($imagePath)
    {
        // إذا كان المسار URL كامل
        if (filter_var($imagePath, FILTER_VALIDATE_URL)) {
            return $imagePath;
        }
        
        // إذا كان المسار يبدأ بـ storage/
        if (str_starts_with($imagePath, 'storage/')) {
            return asset($imagePath);
        }
        
        // إذا كان المسار في مجلد التخزين
        return asset('storage/' . $imagePath);
    }

    private function getFormData()
    {
        return [
            'features' => [
                'balcony', 'built-in kitchen', 'private garden', 'security',
                'central ac', 'parking', 'elevator', 'maids room', 'pool', 'gym'
            ],
            'amenities' => [
                'swimming pool', 'gym', 'sauna', 'kids area', 'parking',
                'security', 'mosque', 'shopping area', 'school', 'hospital',
                'restaurant', 'cafe'
            ],
            'propertyTypes' => [
                'apartment', 'villa', 'duplex', 'penthouse', 'studio',
                'office', 'retail', 'land'
            ],
            'categories' => ['residential', 'commercial', 'administrative'],
            'statuses' => ['available', 'sold', 'rented', 'reserved'],
            'currencies' => ['EGP', 'USD', 'EUR'],
            'contactStatuses' => ['contacted', 'pending', 'no_answer'],
            'offerTypes' => ['owner', 'agent', 'company']
        ];
    }

    public function show(Property $property)
    {
        $property->load(['media', 'handler', 'project']);
        $formData = $this->getFormData();
        
        $property->media->each(function ($media) {
            $media->file_path = $this->handleImageUrl($media->file_path);
        });
        
        return view('properties.show', array_merge(
            compact('property'),
            $formData
        ));
    }

    public function create()
    {
        try {
            $users = User::where('company_id', auth()->user()->company_id)->get();
            $projects = Project::where('company_id', auth()->user()->company_id)->get();
            $formData = $this->getFormData();

            return view('properties.create', array_merge(
                compact('users', 'projects'),
                $formData
            ));
        } catch (\Exception $e) {
            // If projects table doesn't exist, continue without projects
            $users = User::where('company_id', auth()->user()->company_id)->get();
            $formData = $this->getFormData();

            return view('properties.create', array_merge(
                compact('users'),
                ['projects' => collect()],
                $formData
            ));
        }
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'property_name' => 'required|string|max:255',
            'unit_for' => 'required|in:sale,rent',
            'type' => 'required|string',
            'total_area' => 'required|numeric',
            'total_price' => 'required|numeric',
            'team_id' => 'nullable|exists:teams,id'
        ]);

        $validated['company_id'] = auth()->user()->company_id;
        $validated['created_by'] = auth()->id();

        $property = Property::create($validated);

        // Handle media uploads
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $image) {
                $path = $image->store("companies/{$property->company_id}/properties", 'public');
                $property->media()->create([
                    'type' => 'image',
                    'file_path' => $path,
                    'is_featured' => $property->media()->count() === 0
                ]);
            }
        }

        return redirect()->route('properties.show', $property)
            ->with('success', __('Property created successfully'));
    }

    public function edit(Property $property)
    {
        $property->load(['media', 'handler']); // Load relationships
        $users = User::where('company_id', auth()->user()->company_id)->get();
        $projects = Project::where('company_id', auth()->user()->company_id)->get();
        $formData = $this->getFormData();

        return view('properties.edit', array_merge(
            compact('property', 'users', 'projects'),
            $formData
        ));
    }

    public function update(Request $request, Property $property)
    {
        $validated = $request->validate([
            'property_name' => 'required|string|max:255',
            'unit_for' => 'required|in:sale,rent',
            'type' => 'required|string',
            'total_area' => 'required|numeric',
            'total_price' => 'required|numeric',
            'rooms' => 'nullable|integer',
            'bathrooms' => 'nullable|integer',
            'features' => 'nullable|array',
            'amenities' => 'nullable|array',
            'status' => 'required|string',
            'currency' => 'required|string|size:3',
            // ...other validation rules...
        ]);

        $property->update($validated);

        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $image) {
                $path = $image->store("companies/{$property->company_id}/properties", 'public');
                $property->media()->create([
                    'type' => 'image',
                    'file_path' => $path,
                    'is_featured' => false
                ]);
            }
        }

        if ($request->action === 'save_and_continue') {
            return back()->with('success', __('Property updated successfully.'));
        }

        return redirect()->route('properties.show', $property)
            ->with('success', __('Property updated successfully.'));
    }

    public function destroy(Property $property)
    {
        $property->delete();
        return redirect()->route('properties.index')->with('success', 'Property deleted successfully');
    }
}
