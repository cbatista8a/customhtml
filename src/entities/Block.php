<?php

namespace CubaDevOps\CustomHtml\entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

if (!defined('_PS_VERSION_')) {
    exit;
}

class Block extends Model
{
    protected $table = 'customhtml_blocks';
    protected $fillable = ['name', 'hook', 'classes', 'active'];

    protected $casts = [
        'active' => 'int',
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
    ];

    public function contents(): HasMany
    {
        return $this->hasMany(BlockLang::class, 'block_id', 'id');
    }

    protected $appends = ['slug'];

    public function getSlugAttribute(): string
    {
        return \Tools::link_rewrite($this->name);
    }
}
