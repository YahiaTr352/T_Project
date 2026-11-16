<?php

namespace App\Http\Requests\Admin\Products;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProductRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name'                   => 'required|string|max:255',
            'description'            => 'nullable|string',
            'features'               => 'nullable',
            'price'                  => 'required|numeric|min:0',
            'contact_phone'          => 'nullable|string|max:20',
            'contact_whatsapp'       => 'nullable|string|max:20',
            'contact_email'          => 'nullable|email|max:255',
            'contact_instagram'      => 'nullable|string|max:255',
            'contact_facebook'       => 'nullable|string|max:255',
            'website_url'            => 'nullable|url|max:255',
            'location_url'           => 'nullable|url|max:255',
            'main_image'             => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
            'product_category_id'    => 'required|exists:product_categories,id',
            'product_subcategory_id' => 'nullable|exists:product_subcategories,id',
            'owner_id'               => 'nullable|exists:persons,id',
            'location_id'            => 'nullable|exists:locations,id',
            'is_active'              => 'nullable|boolean',
            'is_rental'              => 'nullable|boolean',
            'available_all_week'     => 'nullable|boolean',
            'all_week_start_time'    => 'nullable|date_format:H:i',
            'all_week_end_time'      => 'nullable|date_format:H:i',
            'availability_slots'     => 'nullable|array',
            'availability_slots.*.id'          => 'nullable|exists:product_availability_slots,id',
            'availability_slots.*.day_of_week' => 'required_with:availability_slots|in:saturday,sunday,monday,tuesday,wednesday,thursday,friday',
            'availability_slots.*.start_time'  => 'required_with:availability_slots|date_format:H:i',
            'availability_slots.*.end_time'    => 'required_with:availability_slots|date_format:H:i',
            'availability_slots.*.is_active'   => 'nullable|boolean',
            'delete_slots'           => 'nullable|array',
            'delete_slots.*'         => 'exists:product_availability_slots,id',
            'images.*'               => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
            'video_file'             => 'nullable|mimetypes:video/mp4,video/mpeg,video/quicktime|max:20480',
            'youtube_url'            => 'nullable|url',
            'delete_media'           => 'nullable|array',
            'delete_media.*'         => 'exists:product_media,id',
        ];
    }

    /**
     * Custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name'                   => 'اسم المنتج',
            'description'            => 'الوصف',
            'features'               => 'المميزات',
            'price'                  => 'السعر',
            'contact_phone'          => 'رقم الهاتف',
            'contact_whatsapp'       => 'رقم الواتساب',
            'contact_email'          => 'البريد الإلكتروني',
            'contact_instagram'      => 'حساب إنستغرام',
            'contact_facebook'       => 'حساب فيسبوك',
            'website_url'            => 'رابط الموقع',
            'location_url'           => 'رابط خرائط جوجل',
            'main_image'             => 'الصورة الرئيسية',
            'product_category_id'    => 'الفئة الرئيسية',
            'product_subcategory_id' => 'الفئة الفرعية',
            'owner_id'               => 'صاحب المنتج',
            'location_id'            => 'الموقع',
            'all_week_start_time'    => 'وقت بداية العمل',
            'all_week_end_time'      => 'وقت نهاية العمل',
            'images.*'               => 'صور المعرض',
            'video_file'             => 'ملف الفيديو',
            'youtube_url'            => 'رابط يوتيوب',
        ];
    }
}
