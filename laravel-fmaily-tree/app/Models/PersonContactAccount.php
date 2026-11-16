<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PersonContactAccount extends Model
{
    use HasFactory;

    public const VISIBILITY_PUBLIC = 'public';
    public const VISIBILITY_FAMILY = 'family';
    public const VISIBILITY_PRIVATE = 'private';

    protected $fillable = [
        'person_id',
        'type',
        'value',
        'visibility',
        'label',
        'sort_order',
    ];

    protected $casts = [
        'visibility' => 'string',
        'sort_order' => 'integer',
    ];

    public function person()
    {
        return $this->belongsTo(Person::class);
    }

    /**
     * الحصول على أيقونة حسب نوع الحساب
     */
    public function getIconAttribute(): string
    {
        return match($this->type) {
            'phone' => 'fa-phone',
            'whatsapp' => 'fa-whatsapp',
            'email' => 'fa-envelope',
            'facebook' => 'fa-facebook',
            'instagram' => 'fa-instagram',
            'twitter' => 'fa-twitter',
            'linkedin' => 'fa-linkedin',
            'telegram' => 'fa-telegram',
            default => 'fa-link',
        };
    }

    /**
     * الحصول على رابط حسب نوع الحساب
     */
    public function getUrlAttribute(): ?string
    {
        return match($this->type) {
            'phone' => 'tel:' . $this->value,
            'whatsapp' => $this->whatsappUrl(),
            'email' => 'mailto:' . $this->value,
            'facebook' => str_starts_with($this->value, 'http') ? $this->value : 'https://facebook.com/' . $this->value,
            'instagram' => str_starts_with($this->value, 'http') ? $this->value : 'https://instagram.com/' . $this->value,
            'twitter' => str_starts_with($this->value, 'http') ? $this->value : 'https://twitter.com/' . $this->value,
            'linkedin' => str_starts_with($this->value, 'http') ? $this->value : 'https://linkedin.com/in/' . $this->value,
            'telegram' => str_starts_with($this->value, 'http') ? $this->value : 'https://t.me/' . $this->value,
            default => $this->value,
        };
    }

    /**
     * بناء رابط واتساب حتى لو كانت القيمة مخزّنة كرابط مسبقاً.
     */
    protected function whatsappUrl(): string
    {
        $value = trim((string) $this->value);

        if (preg_match('#^https?://(wa\.me|api\.whatsapp\.com)/#i', $value)) {
            return $value;
        }

        if (preg_match('#(?:wa\.me/|phone=)(\+?\d+)#i', $value, $m)) {
            return 'https://wa.me/' . preg_replace('/\D+/', '', $m[1]);
        }

        $digits = preg_replace('/\D+/', '', $value);

        return $digits !== '' ? 'https://wa.me/' . $digits : $value;
    }
}
