<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * Identitas & kontak kafe yang ditampilkan di landing page, header, footer,
 * dan metadata SEO. Disimpan sebagai satu baris tunggal (singleton).
 */
class SiteSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'brand_name', 'brand_tagline', 'logo_path', 'favicon_path',
        'description', 'address', 'phone', 'whatsapp_number', 'email',
        'instagram_url', 'tiktok_url', 'facebook_url',
        'google_maps_embed_url', 'opening_hours', 'footer_text', 'updated_by',
    ];

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function logoUrl(): ?string
    {
        return $this->logo_path ? Storage::disk('public')->url($this->logo_path) : null;
    }

    public function faviconUrl(): ?string
    {
        return $this->favicon_path ? Storage::disk('public')->url($this->favicon_path) : null;
    }

    /**
     * Nomor WhatsApp dalam format internasional tanpa tanda plus, siap dipakai
     * pada tautan wa.me.
     */
    public function whatsappLink(): ?string
    {
        $number = preg_replace('/\D+/', '', (string) $this->whatsapp_number);

        if ($number === '' || $number === null) {
            return null;
        }

        if (str_starts_with($number, '0')) {
            $number = '62'.substr($number, 1);
        }

        return 'https://wa.me/'.$number;
    }
}
