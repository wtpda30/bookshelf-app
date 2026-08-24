<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReadingPlanRequest;
use App\Http\Requests\UpdateReadingPlanRequest;
use App\Models\Book;
use App\Models\ReadingPlan;
use Illuminate\Http\Request;

class ReadingPlanController extends Controller
{
    /**
     * 読書計画一覧
     */
    public function index(Request $request)
    {
        $query = ReadingPlan::with('book')
            ->where('user_id', auth()->id());

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $readingPlans = $query
            ->orderBy('target_date')
            ->get();

        $currentStatus = $request->status;

        return view('reading-plans.index', compact(
            'readingPlans',
            'currentStatus'
        ));
    }

    /**
     * 読書計画作成画面
     */
    public function create()
    {
        $books = Book::orderBy('title')->get();

        return view('reading-plans.create', compact('books'));
    }

    /**
     * 読書計画登録
     */
    public function store(StoreReadingPlanRequest $request)
    {
        ReadingPlan::create([
            'user_id' => auth()->id(),
            'book_id' => $request->book_id,
            'target_date' => $request->target_date,
            'status' => ReadingPlan::STATUS_IN_PROGRESS,
            'completed_at' => null,
        ]);

        return redirect()
            ->route('reading-plans.index')
            ->with('success', '読書計画を作成しました');
    }

    /**
     * 読書計画編集画面
     */
    public function edit(ReadingPlan $readingPlan)
    {
        $this->authorize('update', $readingPlan);

        return view(
            'reading-plans.edit',
            compact('readingPlan')
        );
    }

    /**
     * 読書計画更新
     */
    public function update(UpdateReadingPlanRequest $request, ReadingPlan $readingPlan)
    {
        $this->authorize('update', $readingPlan);

        $data = [
            'target_date' => $request->target_date,
        ];

        if (
            $readingPlan->status->value === ReadingPlan::STATUS_EXPIRED
            && $request->target_date >= now()->toDateString()
        ) {
            $data['status'] = ReadingPlan::STATUS_IN_PROGRESS;
        }

        $readingPlan->update($data);

        return redirect()
            ->route('reading-plans.index')
            ->with('success', '読書計画を更新しました');
    }

    /**
     * 読了
     */
    public function complete(ReadingPlan $readingPlan)
    {
        $this->authorize('complete', $readingPlan);

        $readingPlan->update([
            'status' => ReadingPlan::STATUS_COMPLETED,
            'completed_at' => now(),
        ]);

        return redirect()
            ->route('reading-plans.index')
            ->with('success', '読書計画を完了しました');
    }

    /**
     * 読書計画削除
     */
    public function destroy(ReadingPlan $readingPlan)
    {
        $this->authorize('delete', $readingPlan);

        $readingPlan->user->notifications()->where('data->reading_plan_id', $readingPlan->id)->delete();

        $readingPlan->delete();

        return redirect()
            ->route('reading-plans.index')
            ->with('success', '読書計画を削除しました');
    }
}
