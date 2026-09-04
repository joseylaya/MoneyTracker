<?php

namespace App\Http\Controllers;

use App\Models\ItineraryDay;
use App\Models\ItineraryItem;
use App\Models\Tracker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ItineraryController extends Controller
{
    private const TYPES = ['activity', 'food', 'accommodation', 'transport', 'shopping', 'place', 'other'];

    public function index(Request $request, Tracker $tracker): Response
    {
        $this->authorize('view', $tracker);
        $days = $tracker->itineraryDays()->with(['items.expenses' => fn ($query) => $query->select('id', 'itinerary_item_id', 'description', 'amount_minor', 'paid_by_user_id')->with('payer:id,name')])->get();

        return Inertia::render('Itinerary/Index', [
            'tracker' => $tracker,
            'days' => $days,
            'canManage' => $request->user()->can('update', $tracker),
        ]);
    }

    public function storeDay(Request $request, Tracker $tracker): RedirectResponse
    {
        $this->authorize('update', $tracker);
        $data = $request->validate([
            'date' => ['required', 'date', Rule::unique('itinerary_days')->where('tracker_id', $tracker->id)],
            'title' => ['nullable', 'string', 'max:150'],
            'notes' => ['nullable', 'string', 'max:3000'],
            'route_mode' => ['required', Rule::in(['driving', 'walking'])],
        ]);
        $tracker->itineraryDays()->create([...$data, 'sort_order' => $tracker->itineraryDays()->max('sort_order') + 1, 'created_by' => $request->user()->id]);
        return back()->with('success', 'Itinerary day added.');
    }

    public function updateDay(Request $request, Tracker $tracker, ItineraryDay $day): RedirectResponse
    {
        $this->authorize('update', $tracker);
        $this->ensureDay($tracker, $day);
        $data = $request->validate([
            'date' => ['required', 'date', Rule::unique('itinerary_days')->where('tracker_id', $tracker->id)->ignore($day->id)],
            'title' => ['nullable', 'string', 'max:150'],
            'notes' => ['nullable', 'string', 'max:3000'],
            'route_mode' => ['required', Rule::in(['driving', 'walking'])],
        ]);
        $day->update([...$data, 'updated_by' => $request->user()->id]);
        return back()->with('success', 'Itinerary day updated.');
    }

    public function destroyDay(Request $request, Tracker $tracker, ItineraryDay $day): RedirectResponse
    {
        $this->authorize('update', $tracker);
        $this->ensureDay($tracker, $day);
        $day->delete();
        return back()->with('success', 'Itinerary day deleted.');
    }

    public function storeItem(Request $request, Tracker $tracker, ItineraryDay $day): RedirectResponse
    {
        $this->authorize('update', $tracker);
        $this->ensureDay($tracker, $day);
        $data = $this->itemData($request);
        $day->items()->create([...$data, 'sort_order' => $day->items()->max('sort_order') + 1, 'created_by' => $request->user()->id]);
        return back()->with('success', 'Itinerary item added.');
    }

    public function updateItem(Request $request, Tracker $tracker, ItineraryItem $item): RedirectResponse
    {
        $this->authorize('update', $tracker);
        $this->ensureItem($tracker, $item);
        $item->update([...$this->itemData($request), 'updated_by' => $request->user()->id]);
        return back()->with('success', 'Itinerary item updated.');
    }

    public function destroyItem(Request $request, Tracker $tracker, ItineraryItem $item): RedirectResponse
    {
        $this->authorize('update', $tracker);
        $this->ensureItem($tracker, $item);
        $item->delete();
        return back()->with('success', 'Itinerary item deleted.');
    }

    public function reorder(Request $request, Tracker $tracker): RedirectResponse
    {
        $this->authorize('update', $tracker);
        $data = $request->validate(['day_id' => ['required', 'uuid'], 'item_ids' => ['required', 'array'], 'item_ids.*' => ['uuid', 'distinct']]);
        $day = $tracker->itineraryDays()->findOrFail($data['day_id']);
        $actual = $day->items()->pluck('id')->sort()->values()->all();
        $submitted = collect($data['item_ids'])->sort()->values()->all();
        abort_unless($actual === $submitted, 422, 'Every item in the day must be included.');
        DB::transaction(fn () => collect($data['item_ids'])->each(fn ($id, $index) => ItineraryItem::whereKey($id)->update(['sort_order' => $index, 'updated_by' => $request->user()->id])));
        return back()->with('success', 'Itinerary reordered.');
    }

    private function itemData(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:3000'],
            'type' => ['required', Rule::in(self::TYPES)],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i', 'after_or_equal:start_time'],
            'location_name' => ['nullable', 'string', 'max:255'],
            'location_address' => ['nullable', 'string', 'max:500'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            'notes' => ['nullable', 'string', 'max:3000'],
        ]);
        return array_map(fn ($value) => $value === '' ? null : $value, $data);
    }

    private function ensureDay(Tracker $tracker, ItineraryDay $day): void { abort_unless($day->tracker_id === $tracker->id, 404); }
    private function ensureItem(Tracker $tracker, ItineraryItem $item): void { abort_unless($item->day()->where('tracker_id', $tracker->id)->exists(), 404); }
}
