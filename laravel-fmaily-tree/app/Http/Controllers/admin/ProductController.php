<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Products\StoreProductRequest;
use App\Http\Requests\Admin\Products\UpdateProductRequest;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductSubcategory;
use App\Models\Location;
use App\Models\ProductMedia;
use App\Models\ProductAvailabilitySlot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ProductController extends Controller
{
    /**
     * Display a listing of products with statistics and filters.
     */
    public function index(Request $request): View
    {
        $categoryId = $request->query('category_id');
        $subcategoryId = $request->query('subcategory_id');
        $search = trim((string) $request->query('search', ''));
        $type = $request->query('type', 'all'); // all, rental, normal
        $status = $request->query('status', 'all'); // all, active, inactive

        $query = Product::query()
            ->with([
                'category:id,name',
                'subcategory:id,name,product_category_id',
                'owner.parent.parent.parent.parent.parent',
                'location:id,name',
                'media',
            ]);

        // Filter by rental/normal type
        if ($type === 'rental') {
            $query->where('is_rental', true);
        } elseif ($type === 'normal') {
            $query->where('is_rental', false);
        }

        // Filter by active/inactive status
        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }

        // Filter by category & subcategory
        if ($categoryId) {
            $query->where('product_category_id', $categoryId);
        }

        if ($subcategoryId) {
            $query->where('product_subcategory_id', $subcategoryId);
        }

        // Comprehensive search (Product name, description, or Owner's full name & lineage)
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhereHas('owner', function ($ownerQuery) use ($search) {
                      $ownerQuery->searchByFullName($search);
                  });
            });
        }

        $products = $query->latest('id')->paginate(15)->appends($request->query());
        $categories = ProductCategory::active()->ordered()->get();
        $subcategories = $categoryId
            ? ProductSubcategory::where('product_category_id', $categoryId)->ordered()->get()
            : ProductSubcategory::ordered()->get();

        $stats = [
            'total'    => Product::count(),
            'active'   => Product::active()->count(),
            'inactive' => Product::where('is_active', false)->count(),
            'rental'   => Product::where('is_rental', true)->count(),
            'normal'   => Product::where('is_rental', false)->count(),
        ];

        return view('dashboard.products.index', compact(
            'products',
            'categories',
            'subcategories',
            'categoryId',
            'subcategoryId',
            'search',
            'stats',
            'type',
            'status'
        ));
    }

    /**
     * Show the form for creating a new product.
     * Note: High-performance design - we do NOT load all family members into the DOM.
     * The owner is selected on demand via Select2 AJAX.
     */
    public function create(): View
    {
        $categories = ProductCategory::active()->ordered()->get();
        $locations = Location::ordered()->get();

        return view('dashboard.products.create', compact('categories', 'locations'));
    }

    /**
     * Store a newly created product in storage.
     */
    public function store(StoreProductRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $data = $request->only([
            'name', 'description', 'price',
            'contact_phone', 'contact_whatsapp', 'contact_email',
            'contact_instagram', 'contact_facebook', 'website_url', 'location_url',
            'product_category_id', 'product_subcategory_id', 'owner_id', 'location_id',
        ]);

        // Process features from array or multi-line string
        if ($request->filled('features')) {
            $rawFeatures = is_array($request->features) ? $request->features : explode("\n", (string) $request->features);
            $features = array_values(array_filter(array_map('trim', $rawFeatures)));
            $data['features'] = !empty($features) ? $features : null;
        }

        // Store main image
        if ($request->hasFile('main_image')) {
            $data['main_image'] = $request->file('main_image')->store('products', 'public');
        }

        $lastOrder = Product::max('sort_order') ?? 0;
        $data['sort_order'] = $lastOrder + 1;
        $data['is_active'] = $request->boolean('is_active', true);
        $data['is_rental'] = $request->boolean('is_rental', false);
        $data['available_all_week'] = $request->boolean('available_all_week', false);
        $data['all_week_start_time'] = $data['available_all_week'] ? $request->input('all_week_start_time') : null;
        $data['all_week_end_time'] = $data['available_all_week'] ? $request->input('all_week_end_time') : null;

        $product = Product::create($data);

        // Process specific availability slots (only if not available all week)
        if (!$data['available_all_week'] && $request->has('availability_slots')) {
            $sortOrder = 0;
            foreach ($request->availability_slots as $slot) {
                if (!empty($slot['day_of_week']) && !empty($slot['start_time']) && !empty($slot['end_time'])) {
                    $product->availabilitySlots()->create([
                        'day_of_week' => $slot['day_of_week'],
                        'start_time'  => $slot['start_time'],
                        'end_time'    => $slot['end_time'],
                        'is_active'   => isset($slot['is_active']) && (bool) $slot['is_active'],
                        'sort_order'  => $sortOrder++,
                    ]);
                }
            }
        }

        // Process additional gallery images
        if ($request->hasFile('images')) {
            $mediaOrder = 0;
            foreach ($request->file('images') as $image) {
                $path = $image->store('products/gallery', 'public');
                $product->media()->create([
                    'media_type' => 'image',
                    'file_path'  => $path,
                    'sort_order' => $mediaOrder++,
                ]);
            }
        }

        // Process local video
        if ($request->hasFile('video_file')) {
            $path = $request->file('video_file')->store('products/videos', 'public');
            $product->media()->create([
                'media_type' => 'video',
                'file_path'  => $path,
            ]);
        }

        // Process YouTube video link
        if ($request->filled('youtube_url')) {
            $product->media()->create([
                'media_type'  => 'youtube',
                'youtube_url' => $request->youtube_url,
            ]);
        }

        return redirect()->route('products.index')
            ->with('success', 'تم إضافة المنتج بنجاح إلى المتجر');
    }

    /**
     * Show the form for editing the specified product.
     */
    public function edit(Product $product): View
    {
        // Eager load only needed relationships for this specific product
        $product->load([
            'availabilitySlots',
            'media',
            'owner.parent.parent.parent.parent.parent',
            'category',
            'subcategory'
        ]);

        $categories = ProductCategory::active()->ordered()->get();
        $subcategories = $product->product_category_id
            ? ProductSubcategory::where('product_category_id', $product->product_category_id)->ordered()->get()
            : collect();
        $locations = Location::ordered()->get();

        return view('dashboard.products.edit', compact('product', 'categories', 'subcategories', 'locations'));
    }

    /**
     * Update the specified product in storage.
     */
    public function update(UpdateProductRequest $request, Product $product): RedirectResponse
    {
        $validated = $request->validated();

        $data = $request->only([
            'name', 'description', 'price',
            'contact_phone', 'contact_whatsapp', 'contact_email',
            'contact_instagram', 'contact_facebook', 'website_url', 'location_url',
            'product_category_id', 'product_subcategory_id', 'owner_id', 'location_id',
        ]);

        // Process features
        if ($request->filled('features')) {
            $rawFeatures = is_array($request->features) ? $request->features : explode("\n", (string) $request->features);
            $features = array_values(array_filter(array_map('trim', $rawFeatures)));
            $data['features'] = !empty($features) ? $features : null;
        } else {
            $data['features'] = null;
        }

        // Update main image if new one is uploaded
        if ($request->hasFile('main_image')) {
            if ($product->main_image && Storage::disk('public')->exists($product->main_image)) {
                Storage::disk('public')->delete($product->main_image);
            }
            $data['main_image'] = $request->file('main_image')->store('products', 'public');
        }

        $data['is_active'] = $request->boolean('is_active', false);
        $data['is_rental'] = $request->boolean('is_rental', false);
        $data['available_all_week'] = $request->boolean('available_all_week', false);
        $data['all_week_start_time'] = $data['available_all_week'] ? $request->input('all_week_start_time') : null;
        $data['all_week_end_time'] = $data['available_all_week'] ? $request->input('all_week_end_time') : null;

        // Reset subcategory if category changed and subcategory does not belong to new category
        if ($request->has('product_category_id') && $request->product_category_id != $product->product_category_id) {
            if ($request->has('product_subcategory_id') && $request->product_subcategory_id) {
                $subExists = ProductSubcategory::where('id', $request->product_subcategory_id)
                    ->where('product_category_id', $request->product_category_id)
                    ->exists();
                if (!$subExists) {
                    $data['product_subcategory_id'] = null;
                }
            } else {
                $data['product_subcategory_id'] = null;
            }
        }

        $product->update($data);

        // Delete requested media items
        if ($request->filled('delete_media')) {
            $mediaItems = ProductMedia::whereIn('id', $request->delete_media)
                ->where('product_id', $product->id)
                ->get();

            foreach ($mediaItems as $media) {
                if ($media->file_path && Storage::disk('public')->exists($media->file_path)) {
                    Storage::disk('public')->delete($media->file_path);
                }
                $media->delete();
            }
        }

        // Add newly uploaded gallery images
        if ($request->hasFile('images')) {
            $lastOrder = $product->media()->max('sort_order') ?? 0;
            foreach ($request->file('images') as $image) {
                $path = $image->store('products/gallery', 'public');
                $product->media()->create([
                    'media_type' => 'image',
                    'file_path'  => $path,
                    'sort_order' => ++$lastOrder,
                ]);
            }
        }

        // Process local video upload (replace or create)
        if ($request->hasFile('video_file')) {
            $existingVideo = $product->media()->where('media_type', 'video')->first();
            if ($existingVideo) {
                if ($existingVideo->file_path && Storage::disk('public')->exists($existingVideo->file_path)) {
                    Storage::disk('public')->delete($existingVideo->file_path);
                }
                $existingVideo->delete();
            }

            $path = $request->file('video_file')->store('products/videos', 'public');
            $product->media()->create([
                'media_type' => 'video',
                'file_path'  => $path,
            ]);
        }

        // Process YouTube video link
        if ($request->filled('youtube_url')) {
            $product->media()->updateOrCreate(
                ['product_id' => $product->id, 'media_type' => 'youtube'],
                ['youtube_url' => $request->youtube_url]
            );
        } elseif ($request->has('youtube_url') && empty($request->youtube_url)) {
            $product->media()->where('media_type', 'youtube')->delete();
        }

        // Manage availability slots
        if ($request->filled('delete_slots')) {
            ProductAvailabilitySlot::whereIn('id', $request->delete_slots)
                ->where('product_id', $product->id)
                ->delete();
        }

        if ($data['available_all_week']) {
            $product->availabilitySlots()->delete();
        } elseif ($request->has('availability_slots')) {
            $existingSlotIds = [];
            $sortOrder = 0;

            foreach ($request->availability_slots as $slot) {
                if (!empty($slot['day_of_week']) && !empty($slot['start_time']) && !empty($slot['end_time'])) {
                    if (isset($slot['id']) && $slot['id']) {
                        $existingSlot = ProductAvailabilitySlot::where('id', $slot['id'])
                            ->where('product_id', $product->id)
                            ->first();

                        if ($existingSlot) {
                            $existingSlot->update([
                                'day_of_week' => $slot['day_of_week'],
                                'start_time'  => $slot['start_time'],
                                'end_time'    => $slot['end_time'],
                                'is_active'   => isset($slot['is_active']) && (bool) $slot['is_active'],
                                'sort_order'  => $sortOrder++,
                            ]);
                            $existingSlotIds[] = $existingSlot->id;
                        }
                    } else {
                        $newSlot = $product->availabilitySlots()->create([
                            'day_of_week' => $slot['day_of_week'],
                            'start_time'  => $slot['start_time'],
                            'end_time'    => $slot['end_time'],
                            'is_active'   => isset($slot['is_active']) && (bool) $slot['is_active'],
                            'sort_order'  => $sortOrder++,
                        ]);
                        $existingSlotIds[] = $newSlot->id;
                    }
                }
            }

            // Remove slots that were omitted in update
            $product->availabilitySlots()
                ->whereNotIn('id', $existingSlotIds)
                ->delete();
        }

        return redirect()->route('products.index')
            ->with('success', 'تم تحديث بيانات المنتج بنجاح');
    }

    /**
     * Remove the specified product and all its assets from storage.
     */
    public function destroy(Request $request, Product $product)
    {
        // Delete main image
        if ($product->main_image && Storage::disk('public')->exists($product->main_image)) {
            Storage::disk('public')->delete($product->main_image);
        }

        // Delete all gallery and video media files
        foreach ($product->media as $media) {
            if ($media->file_path && Storage::disk('public')->exists($media->file_path)) {
                Storage::disk('public')->delete($media->file_path);
            }
        }

        $product->delete();

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'تم حذف المنتج بنجاح',
            ]);
        }

        return redirect()->route('products.index')
            ->with('success', 'تم حذف المنتج بنجاح');
    }

    /**
     * Toggle the active status of the specified product.
     * Supports both instantaneous AJAX updates and fallback HTTP redirects.
     */
    public function toggle(Request $request, Product $product)
    {
        $product->update([
            'is_active' => !$product->is_active,
        ]);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success'   => true,
                'is_active' => $product->is_active,
                'message'   => $product->is_active ? 'تم تفعيل المنتج بنجاح' : 'تم تعطيل المنتج بنجاح',
            ]);
        }

        return back()->with('success', $product->is_active ? 'تم تفعيل المنتج' : 'تم تعطيل المنتج');
    }
}
