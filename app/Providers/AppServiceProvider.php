<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Blade; // Pastikan ini diimpor
use App\Models\HierarchyLevel; // Pastikan ini diimpor jika diperlukan di boot()

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // ... kode boot() lainnya jika ada ...

        // Definisi Blade Directive kustom untuk merender opsi hirarki
        Blade::directive('renderHierarchyParentOptions', function ($expression) {
            return "<?php
                // Fungsi bantuan ini sekarang didefinisikan satu kali dalam direktif
                if (!function_exists('__render_hierarchy_options_recursive')) {
                    function __render_hierarchy_options_recursive(\$allHierarchyLevels, \$parentId = null, \$level = 0, \$selectedParentCode = null, \$excludeCode = null) {
                        \$indent = str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;', \$level);
                        
                        foreach (\$allHierarchyLevels->where('parent_code', \$parentId)->sortBy('order') as \$levelItem) {
                            if (\$excludeCode && \$levelItem->code === \$excludeCode) {
                                continue;
                            }

                            \$isSelected = (\$selectedParentCode === \$levelItem->code) ? 'selected' : '';
                            echo \"<option value=\\\"\$levelItem->code\\\" \$isSelected>\$indent{\$levelItem->name} ({\$levelItem->code})</option>\";
                            
                            __render_hierarchy_options_recursive(\$allHierarchyLevels, \$levelItem->code, \$level + 1, \$selectedParentCode, \$excludeCode);
                        }
                    }
                }
                // Panggil fungsi secara langsung dengan ekspresi yang diberikan oleh Blade.
                // Blade akan menangani parsing variabel di dalam \$expression.
                __render_hierarchy_options_recursive($expression);
            ?>";
        });
    }
}