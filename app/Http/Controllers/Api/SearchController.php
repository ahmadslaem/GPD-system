<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Family;
use Illuminate\Http\Request;

class SearchController extends Controller
{

    /*
    Local Search
    البحث داخل مخيم المستخدم فقط
    */

    public function local(Request $request)
    {
        $request->validate([
            'keyword' => 'required|string'
        ]);

        $campId = auth()->user()->camp_id;

        $keyword = $request->keyword;

        $query = Family::with('members')
            ->where('camp_id', $campId)
            ->where(function ($q) use ($keyword) {
                $this->applyKeywordMatch($q, $keyword);
            });

        $this->applyLevelFilter($query, $request);

        $families = $query->orderByDesc('created_at')->get();

        return $this->paginatedJson($request, $families, null, ['status' => true]);
    }




    /*
    Global Search
    للمدير والأدمن فقط
    */

    public function global(Request $request)
    {
        $request->validate([
            'keyword' => 'required|string'
        ]);

        $keyword = $request->keyword;

        $query = Family::with(['members', 'camp'])
            ->where(function ($q) use ($keyword) {
                $this->applyKeywordMatch($q, $keyword);
            });

        $this->applyLevelFilter($query, $request);

        $families = $query->orderByDesc('created_at')->get();

        return $this->paginatedJson($request, $families, null, ['status' => true]);
    }

    /**
     * فلترة اختيارية بمستوى الضعف (تستفيد من فهرس vulnerability_level).
     */
    private function applyLevelFilter($query, Request $request): void
    {
        if (in_array($request->query('vulnerability_level'), ['high', 'medium', 'low'], true)) {
            $query->where('vulnerability_level', $request->query('vulnerability_level'));
        }
    }

    /**
     * تطابق آمن ضد SQL LIKE injection:
     * يهرب wildcards (% و _ و \) قبل دمجها في نمط LIKE.
     */
    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }

    /**
     * يضيف شروط البحث (national_id / head_name / phone / رقم الأسرة F-xxxx)
     * مع escaping آمن.
     */
    private function applyKeywordMatch($query, string $keyword): void
    {
        $escaped = $this->escapeLike($keyword);

        // لو المستخدم كتب رقم الأسرة بصيغة F-00004 أو حتى بدون F-
        $numericId = ltrim(str_ireplace('F-', '', $keyword), '0');

        $query->where('national_id', 'like', '%' . $escaped . '%')
            ->orWhere('head_name', 'like', '%' . $escaped . '%')
            ->orWhere('phone', 'like', '%' . $escaped . '%');

        if (is_numeric($numericId) && $numericId !== '') {
            $query->orWhere('id', (int) $numericId);
        }
    }
}
