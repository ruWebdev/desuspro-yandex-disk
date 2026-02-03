# Cache Usage Guide for Developers

## Overview
This application uses Laravel's caching system to improve performance by reducing database queries for static or rarely-changing data.

## Current Cache Implementation

### Cached Data

#### 1. Brands List
```php
// Cache key: 'brands_list' or 'brands_list_full'
// TTL: 3600 seconds (1 hour)
// Invalidated: When any Brand is saved or deleted

$brands = cache()->remember('brands_list', 3600, function () {
    return Brand::query()->orderBy('name')->get(['id','name']);
});
```

**Usage**: Task creation forms, brand selection dropdowns

#### 2. Task Types List
```php
// Cache key: 'task_types_list'
// TTL: 3600 seconds (1 hour)
// Invalidated: When any TaskType is saved or deleted

$taskTypes = cache()->remember('task_types_list', 3600, function () {
    return TaskType::query()->orderBy('name')->get(['id','name','prefix']);
});
```

**Usage**: Task creation forms, task type filters

#### 3. Performers List
```php
// Cache key: 'performers_list'
// TTL: 300 seconds (5 minutes)
// Invalidated: When any User is saved or deleted

$performers = cache()->remember('performers_list', 300, function () {
    return User::role('Performer')->get(['id','name','is_blocked']);
});
```

**Usage**: Task assignment dropdowns, performer filters
**Note**: Shorter TTL because `is_blocked` status can change frequently

## Automatic Cache Invalidation

Cache is automatically cleared when data changes:

### Brand Model
```php
// app/Models/Brand.php
protected static function booted(): void
{
    static::saved(function () {
        Cache::forget('brands_list');
        Cache::forget('brands_list_full');
    });

    static::deleted(function () {
        Cache::forget('brands_list');
        Cache::forget('brands_list_full');
    });
}
```

### TaskType Model
```php
// app/Models/TaskType.php
protected static function booted(): void
{
    static::saved(function () {
        Cache::forget('task_types_list');
    });

    static::deleted(function () {
        Cache::forget('task_types_list');
    });
}
```

### User Model
```php
// app/Models/User.php
protected static function booted(): void
{
    static::saved(function () {
        Cache::forget('performers_list');
    });

    static::deleted(function () {
        Cache::forget('performers_list');
    });
}
```

## Adding New Cached Data

### Step 1: Implement Caching in Controller
```php
public function index()
{
    // Choose an appropriate cache key and TTL
    $data = cache()->remember('your_cache_key', 3600, function () {
        return YourModel::query()->get();
    });
    
    return view('your.view', compact('data'));
}
```

### Step 2: Add Cache Invalidation to Model
```php
// app/Models/YourModel.php
use Illuminate\Support\Facades\Cache;

protected static function booted(): void
{
    static::saved(function () {
        Cache::forget('your_cache_key');
    });

    static::deleted(function () {
        Cache::forget('your_cache_key');
    });
}
```

## Cache TTL Guidelines

Choose appropriate TTL based on data volatility:

| Data Type | Recommended TTL | Reason |
|-----------|----------------|--------|
| Static configuration | 3600s (1 hour) | Rarely changes |
| User lists | 300s (5 minutes) | Status can change |
| Real-time data | Don't cache | Changes frequently |
| Computed results | 600s (10 minutes) | Balance freshness/performance |

## Manual Cache Management

### Clear All Cache
```bash
php artisan cache:clear
```

### Clear Specific Cache Key
```php
Cache::forget('cache_key_name');
```

### Check if Cache Exists
```php
if (Cache::has('cache_key_name')) {
    // Cache exists
}
```

### Get Cache Value
```php
$value = Cache::get('cache_key_name');
```

### Set Cache Value Manually
```php
Cache::put('cache_key_name', $value, 3600); // 3600 seconds
```

## Best Practices

### ✅ DO
- Cache static or rarely-changing data
- Use descriptive cache keys
- Set appropriate TTL based on data volatility
- Implement automatic cache invalidation
- Document cache keys in code comments

### ❌ DON'T
- Cache user-specific data without user ID in key
- Cache sensitive information without encryption
- Use very long TTL for frequently-changing data
- Forget to invalidate cache when data changes
- Cache large objects (>1MB) without consideration

## Debugging Cache Issues

### Problem: Stale Data
**Symptom**: Changes not reflected in UI
**Solution**: 
```bash
php artisan cache:clear
```
Check if cache invalidation is properly implemented in model.

### Problem: Cache Not Working
**Symptom**: No performance improvement
**Solution**:
1. Verify cache driver is configured: `config/cache.php`
2. Check cache is enabled: `.env` file
3. Test cache manually:
```php
Cache::put('test', 'value', 60);
dd(Cache::get('test')); // Should output 'value'
```

### Problem: Memory Issues
**Symptom**: High memory usage
**Solution**:
- Review cached data size
- Reduce TTL for large datasets
- Consider using Redis instead of file/database cache

## Cache Drivers

### Current Setup (File Cache)
```php
// .env
CACHE_DRIVER=file
```

### Recommended for Production (Redis)
```php
// .env
CACHE_DRIVER=redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

**Benefits of Redis**:
- Faster than file cache
- Better for multiple servers
- Supports advanced features (tags, atomic operations)

## Performance Monitoring

### Check Cache Hit Rate
```php
// Add to a monitoring endpoint
$hits = Cache::get('cache_hits', 0);
$misses = Cache::get('cache_misses', 0);
$hitRate = $hits / ($hits + $misses) * 100;

// Log or display hit rate
Log::info("Cache hit rate: {$hitRate}%");
```

### Monitor Cache Size
```bash
# For file cache
du -sh storage/framework/cache

# For Redis
redis-cli info memory
```

## Common Pitfalls

### 1. Forgetting to Invalidate Cache
```php
// ❌ BAD: No cache invalidation
public function update(Request $request, Brand $brand)
{
    $brand->update($request->validated());
    return back();
}

// ✅ GOOD: Automatic invalidation via model events
// (Already implemented in Brand model)
```

### 2. Caching User-Specific Data
```php
// ❌ BAD: Same cache for all users
$tasks = cache()->remember('user_tasks', 3600, function () {
    return auth()->user()->tasks;
});

// ✅ GOOD: Include user ID in cache key
$tasks = cache()->remember("user_tasks_{$userId}", 3600, function () use ($userId) {
    return User::find($userId)->tasks;
});
```

### 3. Not Handling Cache Failures
```php
// ✅ GOOD: Graceful fallback
try {
    $brands = cache()->remember('brands_list', 3600, function () {
        return Brand::all();
    });
} catch (\Exception $e) {
    Log::error('Cache error: ' . $e->getMessage());
    $brands = Brand::all(); // Fallback to direct query
}
```

## Testing with Cache

### Disable Cache in Tests
```php
// tests/TestCase.php
protected function setUp(): void
{
    parent::setUp();
    Cache::flush(); // Clear cache before each test
}
```

### Test Cache Invalidation
```php
public function test_brand_cache_is_cleared_on_update()
{
    $brand = Brand::factory()->create();
    
    // Populate cache
    cache()->remember('brands_list', 3600, fn() => Brand::all());
    
    // Update brand (should clear cache)
    $brand->update(['name' => 'Updated']);
    
    // Verify cache is cleared
    $this->assertFalse(Cache::has('brands_list'));
}
```

## Questions?

For more information:
- Laravel Cache Documentation: https://laravel.com/docs/cache
- Redis Documentation: https://redis.io/documentation
- See `OPTIMIZATION_REPORT.md` for implementation details

---

**Last Updated**: October 20, 2025  
**Maintained By**: Development Team
