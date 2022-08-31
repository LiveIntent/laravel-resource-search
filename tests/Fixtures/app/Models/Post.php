<?php

namespace LiveIntent\LaravelResourceSearch\Tests\Fixtures\App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Post extends Model
{
    use SoftDeletes;
    use HasFactory;

    protected $fillable = ['title', 'body', 'user_id'];

    protected $casts = [
        'meta' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopePublished(Builder $query)
    {
        return $query->where('publish_at', '<', Carbon::now());
    }

    public function scopePublishedAt(Builder $query, string $dateTime)
    {
        return $query->where('publish_at', $dateTime);
    }

    public function scopeWithMeta(Builder $query)
    {
        return $query->whereNotNull('meta');
    }
}
