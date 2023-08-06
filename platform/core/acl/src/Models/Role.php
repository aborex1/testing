<?php

namespace Botble\ACL\Models;

use Botble\ACL\Traits\PermissionTrait;
use Botble\Base\Casts\SafeContent;
use Botble\Base\Facades\BaseHelper;
use Botble\Base\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class Role extends BaseModel
{
    use PermissionTrait;

    protected $table = 'roles';

    protected $fillable = [
        'name',
        'slug',
        'permissions',
        'description',
        'is_default',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'permissions' => 'json',
        'name' => SafeContent::class,
        'description' => SafeContent::class,
    ];

    public function delete(): bool|null
    {
        if ($this->exists) {
            $this->users()->detach();
        }

        return parent::delete();
    }

    public function users(): BelongsToMany
    {
        return $this
            ->belongsToMany(User::class, 'role_users', 'role_id', 'user_id')
            ->withTimestamps();
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withDefault();
    }

    public function getAvailablePermissions(): array
    {
        $permissions = [];

        $types = ['core', 'packages', 'plugins'];

        foreach ($types as $type) {
            foreach (BaseHelper::scanFolder(platform_path($type)) as $module) {
                $configuration = config(strtolower($type . '.' . $module . '.permissions'));
                if (! empty($configuration)) {
                    foreach ($configuration as $config) {
                        $permissions[$config['flag']] = $config;
                    }
                }
            }
        }

        return $permissions;
    }

    public static function createSlug(string $name, int|string $id): string
    {
        $slug = Str::slug($name);
        $index = 1;
        $baseSlug = $slug;
        while (self::query()->where('slug', $slug)->where('id', '!=', $id)->exists()) {
            $slug = $baseSlug . '-' . $index++;
        }

        if (empty($slug)) {
            $slug = time();
        }

        return $slug;
    }
}
