<?php

namespace App\Models;

use App\Enums\RoleEnum;
use App\Support\MojibakeFixer;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    protected $perPage = 12;

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'username',
        'slug',
        'password',
        'fullname',
        'role',
        'role_id',
        'created_by',
        'created_date',
        'user',
        'image',
        'banner_image',
        'about',
        'email',
        'phone',
        'location',
        'facebook',
        'twitter',
        'linkedin',
        'org',
        'api_secret_key',
        'api_secret_key_rotated_at',
    ];

    /**
     * Generate or rotate API secret key for corporate institute.
     */
    public function generateApiSecretKey(): string
    {
        $key = 'en_sec_' . bin2hex(random_bytes(16));
        $this->update([
            'api_secret_key' => $key,
            'api_secret_key_rotated_at' => now(),
        ]);
        return $key;
    }

    /**
     * Ensure the user has an API secret key.
     */
    public function ensureApiSecretKey(): string
    {
        if (!empty($this->api_secret_key)) {
            return $this->api_secret_key;
        }
        return $this->generateApiSecretKey();
    }

    /**
     * Ensure the user has a slug.
     */
    public function ensureSlug(): string
    {
        if (!empty($this->slug)) {
            return $this->slug;
        }

        $source = $this->username ?: $this->org ?: $this->fullname ?: 'institute';
        $slug = static::generateUniqueSlug($source, $this->id);
        $this->update(['slug' => $slug]);
        return $slug;
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    // Required for JWT
    public function getJWTCustomClaims()
    {
        return [];
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'role_id' => 'integer',
            'added_by' => 'integer',
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function doubts()
    {
        return $this->hasMany(Doubt::class, 'org_id');
    }

    public function answerSheets()
    {
        return $this->hasMany(AnswerSheet::class);
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function publishedInstituteReviews()
    {
        return $this->hasMany(InstituteReview::class, 'institute_id')->where('is_published', true);
    }

    public function isTeacher()
    {
        return $this->role->name == RoleEnum::TEACHER->value;
    }

    public function teacherExams()
    {
        return $this->hasMany(Exam::class);
    }

    public function instituteStudents()
    {
        return $this->hasMany(InstituteStudent::class, 'institute_id');
    }

    public function instituteReviews()
    {
        return $this->hasMany(InstituteReview::class, 'institute_id');
    }

    public function classes()
    {
        return $this->hasMany(\App\Models\Corporate\Classroom::class, 'institute_id');
    }

    public function isAdmin()
    {
        return $this->role->name == RoleEnum::ADMIN->value;
    }

    public function getFullnameAttribute(?string $value): ?string
    {
        return MojibakeFixer::fix($value);
    }

    public function logoUrl(): ?string
    {
        return $this->resolveStoredFileUrl($this->image);
    }

    public function bannerUrl(): ?string
    {
        return $this->resolveStoredFileUrl($this->banner_image);
    }

    private function resolveStoredFileUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        if (Str::startsWith($path, ['http://', 'https://'])) {
            return $path;
        }

        return Storage::disk('public')->url($path);
    }

    /**
     * Build a URL-safe slug from $source, unique across all users.
     * Used for public institute links (examsnepal.com/institute/{slug}).
     */
    public static function generateUniqueSlug(string $source, ?int $ignoreId = null): string
    {
        $base = Str::slug($source) ?: 'institute';
        $slug = $base;
        $suffix = 2;

        while (
            static::where('slug', $slug)
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
