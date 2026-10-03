<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;

abstract class Controller
{
    use AuthorizesRequests, ValidatesRequests;

    /**
     * يبني رد JSON موحّد مع دعم الترقيم (pagination).
     *
     * - بدون page/per_page: يرجع كل الصفوف داخل "data" (نفس الصيغة القديمة — متوافق مع الواجهة الحالية).
     * - مع page: يرجع "data" + "meta" معلومات الترقيم، بدون "links" الثقيلة.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Illuminate\Support\Collection|\Illuminate\Contracts\Pagination\LengthAwarePaginator  $items
     * @param  callable|null  $mapper
     * @param  array  $extra  حقول إضافية على مستوى الرد (مثل summary)
     */
    protected function paginatedJson(Request $request, $items, ?callable $mapper = null, array $extra = [])
    {
        $mapper ??= fn ($item) => $item;

        $perPage = (int) $request->query('per_page', 0);

        // وضع متوافق (compat): بدون معامل صفحة — يرجع الكل كما تتوقع الواجهة الحالية
        if ($perPage === 0) {
            $collection = $items instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator
                ? collect($items->items())
                : collect($items);

            return response()->json(array_merge([
                'status' => true,
                'data' => $collection->values()->map($mapper),
            ], $extra));
        }

        $perPage = max(1, min($perPage, 200)); // حد أقصى 200 سجل في الصفحة

        if ($items instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator) {
            $items->appends($request->query());
        } else {
            $page = max(1, (int) $request->query('page', 1));
            $items = new \Illuminate\Pagination\LengthAwarePaginator(
                $items->forPage($page, $perPage)->values(),
                $items->count(),
                $perPage,
                $page,
                ['path' => $request->url()]
            );
        }

        return response()->json(array_merge([
            'status' => true,
            'data' => collect($items->items())->map($mapper)->values(),
            'meta' => [
                'current_page' => $items->currentPage(),
                'per_page' => $items->perPage(),
                'last_page' => $items->lastPage(),
                'total' => $items->total(),
            ],
        ], $extra));
    }
}
