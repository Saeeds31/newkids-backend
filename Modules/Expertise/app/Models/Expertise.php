<?php

namespace Modules\Expertise\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Student\Models\Student;
use Modules\Users\Models\Teacher;

// use Modules\Interest\Database\Factories\InterestFactory;

class Expertise extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'icon',
        'key',
        'description',
        'color_code',
    ];


    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
    public const COLOR_PALETTE = [
        '#FF5733', // قرمز-نارنجی
        '#33FF57', // سبز
        '#3357FF', // آبی
        '#F333FF', // صورتی
        '#FFD733', // زرد
        '#33FFF5', // فیروزه‌ای
        '#FF8333', // نارنجی
        '#8E44AD', // بنفش
        '#E74C3C', // قرمز
        '#2ECC71', // سبز روشن
        '#3498DB', // آبی روشن
        '#F39C12', // نارنجی تیره
        '#1ABC9C', // سبز آبی
        '#9B59B6', // ارغوانی
        '#34495E', // آبی تیره
        '#E67E22', // نارنجی
        '#7F8C8D', // خاکستری
        '#16A085', // سبز دریایی
        '#27AE60', // سبز
        '#2980B9', // آبی
        '#8E44AD', // بنفش
        '#2C3E50', // آبی نفتی
        '#D35400', // نارنجی سوخته
        '#C0392B', // قرمز تیره
    ];

    /**
     * دریافت لیست رنگ‌های ثابت
     */
    public static function getColorPalette(): array
    {
        return self::COLOR_PALETTE;
    }
    public static function isValidColor(string $color): bool
    {
        return in_array($color, self::COLOR_PALETTE);
    }
    public function students()
    {
        return $this->belongsToMany(Student::class, 'attribute_students');
    }
    public function teachers()
    {
        return $this->belongsToMany(Teacher::class, 'expertise_teacher');
    }
}
