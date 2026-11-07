@php($trail = \App\Services\AdminBreadcrumbs::for(request()->route()))

@if ($trail !== [])
    <nav aria-label="{{ __('Breadcrumb') }}" class="mb-4">
        <ol class="flex flex-wrap items-center gap-1.5 text-sm text-zinc-500 dark:text-zinc-400">
            @foreach ($trail as $item)
                <li class="flex items-center gap-1.5">
                    @if (! $loop->first)
                        <flux:icon name="chevron-right" variant="micro" class="size-3.5 text-zinc-400" aria-hidden="true" />
                    @endif

                    @if ($item['url'])
                        <a href="{{ $item['url'] }}" wire:navigate class="hover:text-zinc-900 hover:underline dark:hover:text-white">
                            @if (isset($item['icon']))
                                <flux:icon :name="$item['icon']" class="size-4" />
                                <span class="sr-only">{{ $item['label'] }}</span>
                            @else
                                {{ $item['label'] }}
                            @endif
                        </a>
                    @else
                        <span aria-current="page" class="font-medium text-zinc-800 dark:text-zinc-200">{{ $item['label'] }}</span>
                    @endif
                </li>
            @endforeach
        </ol>
    </nav>
@endif
