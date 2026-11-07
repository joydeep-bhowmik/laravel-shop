@props(['product'])

@php
    use App\Services\MoneyFormatter;

    $variant = $product->defaultVariant;
    $compareAt = $variant?->compare_at_price_paise;
@endphp

<article class="group flex flex-col" wire:key="card-{{ $product->id }}">
    <a href="{{ route('product', $product->slug) }}" wire:navigate class="block overflow-hidden rounded-lg bg-zinc-100 dark:bg-zinc-800">
        <img
            src="{{ $product->getFirstMediaUrl('gallery', 'thumb') ?: asset('images/placeholder-product.svg') }}"
            alt="{{ $product->name }}"
            loading="lazy"
            width="320"
            height="320"
            class="aspect-square w-full object-cover transition duration-300 group-hover:scale-105"
        >
    </a>

    <div class="mt-3 flex flex-1 flex-col gap-1">
        @if ($product->brand)
            <p class="text-xs font-medium uppercase tracking-wide text-zinc-500">{{ $product->brand->name }}</p>
        @endif

        <h3 class="font-medium leading-snug">
            <a href="{{ route('product', $product->slug) }}" wire:navigate class="hover:underline">{{ $product->name }}</a>
        </h3>

        @if ($variant)
            <p class="mt-auto flex flex-wrap items-baseline gap-2">
                <span class="font-semibold">{{ MoneyFormatter::format($variant->price_paise) }}</span>
                @if ($compareAt && $compareAt > $variant->price_paise)
                    <span class="text-sm text-zinc-500 line-through">{{ MoneyFormatter::format($compareAt) }}</span>
                @endif
            </p>
        @endif

        @if ($product->rating_count > 0)
            <p class="flex items-center gap-1 text-xs text-zinc-500">
                <flux:icon name="star" variant="solid" class="size-3.5 text-amber-500" />
                {{ number_format((float) $product->rating_average, 1) }} ({{ $product->rating_count }})
            </p>
        @endif
    </div>
</article>
