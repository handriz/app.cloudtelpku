<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HierarchyLevel extends Model
{
    use HasFactory;

    // Pastikan properti $table ini ada dan benar
    protected $table = 'hierarchy_levels'; 

    protected $fillable = [
        'code',
        'name',
        'parent_code',
        'order',
        'is_active',
    ];

    /**
     * Get the children hierarchy levels for the hierarchy level.
     */
    public function children()
    {
        return $this->hasMany(HierarchyLevel::class, 'parent_code', 'code')->orderBy('order');
    }

    /**
     * Get the parent hierarchy level that owns the hierarchy level.
     */
    public function parent()
    {
        return $this->belongsTo(HierarchyLevel::class, 'parent_code', 'code');
    }

    /**
     * Get all descendant hierarchy codes including the current one.
     * Digunakan oleh HierarchyScope untuk filtering.
     *
     * @param string $startCode The starting hierarchy code.
     * @param array $descendantCodes The array to populate with descendant codes.
     * @return void
     */
    public static function getDescendantCodes(string $startCode, array &$descendantCodes)
    {
        if (!in_array($startCode, $descendantCodes)) {
            $descendantCodes[] = $startCode;
        }

        $children = HierarchyLevel::where('parent_code', $startCode)->get();
        foreach ($children as $child) {
            self::getDescendantCodes($child->code, $descendantCodes);
        }
    }
}
