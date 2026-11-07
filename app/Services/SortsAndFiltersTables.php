<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Url;
use Throwable;

/**
 * Column sorting and a from/to date range for admin Livewire tables.
 *
 * Sort keys are whitelisted by each component, so a hand-edited URL cannot sort by an arbitrary column.
 * The range is inclusive on both days. If the two dates are reversed they are swapped.
 */
trait SortsAndFiltersTables
{
    #[Url(as: 'sort', except: '')]
    public string $sort = '';

    #[Url(as: 'dir', except: 'desc')]
    public string $direction = 'desc';

    #[Url(as: 'from', except: '')]
    public string $dateFrom = '';

    #[Url(as: 'to', except: '')]
    public string $dateTo = '';

    /**
     * Click a column header. A new column sorts ascending first; the same column flips direction.
     */
    public function sortBy(string $column): void
    {
        if ($this->sort === $column) {
            $this->direction = $this->direction === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sort = $column;
            $this->direction = 'asc';
        }

        $this->resetPage();
    }

    public function updatedDateFrom(): void
    {
        $this->resetPage();
    }

    public function updatedDateTo(): void
    {
        $this->resetPage();
    }

    public function clearDates(): void
    {
        $this->reset('dateFrom', 'dateTo');
        $this->resetPage();
    }

    /**
     * Sort by a whitelisted key. With no column chosen, sort by the fallback key (newest first by default).
     *
     * @param  array<string, string>  $columns  Map of sort key to database column.
     */
    protected function applyTableSort(Builder $query, array $columns, string $fallback = 'created_at', string $fallbackDirection = 'desc'): Builder
    {
        if ($this->sort !== '' && isset($columns[$this->sort])) {
            $direction = $this->direction === 'asc' ? 'asc' : 'desc';

            return $query->orderBy($columns[$this->sort], $direction)->orderBy($query->getModel()->getQualifiedKeyName(), $direction);
        }

        return $query->orderBy($columns[$fallback] ?? $query->getModel()->getQualifiedKeyName(), $fallbackDirection);
    }

    /**
     * Keep rows whose $column falls between the two chosen days.
     */
    protected function applyDateRange(Builder $query, string $column): Builder
    {
        [$from, $to] = $this->dateBounds();

        return $query
            ->when($from !== null, fn (Builder $query) => $query->where($column, '>=', $from))
            ->when($to !== null, fn (Builder $query) => $query->where($column, '<=', $to));
    }

    /**
     * @return array{0: ?Carbon, 1: ?Carbon}
     */
    protected function dateBounds(): array
    {
        $from = $this->parseDay($this->dateFrom)?->startOfDay();
        $to = $this->parseDay($this->dateTo)?->endOfDay();

        if ($from !== null && $to !== null && $from->greaterThan($to)) {
            [$from, $to] = [$to->startOfDay(), $from->endOfDay()];
        }

        return [$from, $to];
    }

    /**
     * Ignore anything that is not a date, such as a hand-edited URL.
     */
    private function parseDay(string $value): ?Carbon
    {
        if ($value === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (Throwable) {
            return null;
        }
    }
}
